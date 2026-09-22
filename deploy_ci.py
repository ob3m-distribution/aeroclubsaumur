"""
Deploiement CI/CD vers IONOS par SFTP, avec bascule par renommage de
dossier et rollback en cas d'echec du healthcheck.

Le serveur n'a pas de shell : tout se fait par operations SFTP (upload,
mkdir, rename, remove), jamais de commande executee a distance. C'est le
script appele par .github/workflows/deploy.yml — pour un deploiement
manuel depuis un poste local, utiliser deploy.py.

Usage :
    python deploy_ci.py deploy
    python deploy_ci.py rollback

Variables d'environnement requises : DEPLOY_HOST, DEPLOY_USER,
DEPLOY_PASSWORD, DEPLOY_PATH (chemin du dossier actuellement en ligne,
ex. "Aeroclub Saumur - Espace developpement").
"""
import os
import socket
import stat as stat_module
import sys
import tempfile

import paramiko

LOCAL = os.path.join(os.path.dirname(os.path.abspath(__file__)), "site")

IGNORER_FICHIERS = {".DS_Store", "Thumbs.db", ".htpasswd"}
IGNORER_DOSSIERS = {".git", "__pycache__", "node_modules", "_originaux"}

# Fichiers/dossiers qui ne sont jamais dans git (secrets, donnees membres —
# voir .gitignore) mais qui existent sur le serveur et doivent survivre a
# chaque deploiement, sans quoi le site neuf n'a ni secrets ni donnees
# reelles. A tenir synchronise avec .gitignore si la liste change la-bas.
PERSISTANTS_FICHIERS = [".htpasswd", "inc/config-local.php"]
PERSISTANTS_DOSSIERS = ["docs-inscriptions", "docs-adherents", "uploads"]


def connecter():
    # Timeout explicite : sans lui, une connexion qui se fige (coupure
    # reseau cote IONOS) bloque le job indefiniment plutot que d'echouer.
    sock = socket.create_connection((os.environ["DEPLOY_HOST"], 22), timeout=30)
    t = paramiko.Transport(sock)
    t.banner_timeout = 30
    t.auth_timeout = 30
    t.connect(username=os.environ["DEPLOY_USER"], password=os.environ["DEPLOY_PASSWORD"])
    sftp = paramiko.SFTPClient.from_transport(t)
    sftp.get_channel().settimeout(60)
    return t, sftp


def existe(sftp, chemin):
    try:
        sftp.stat(chemin)
        return True
    except FileNotFoundError:
        return False


def supprimer_recursif(sftp, chemin):
    """rm -rf, en SFTP."""
    for entree in sftp.listdir_attr(chemin):
        sous_chemin = f"{chemin}/{entree.filename}"
        if stat_module.S_ISDIR(entree.st_mode):
            supprimer_recursif(sftp, sous_chemin)
        else:
            sftp.remove(sous_chemin)
    sftp.rmdir(chemin)


def rendre_dossier(sftp, chemin):
    """mkdir -p, en SFTP."""
    morceaux, courant = chemin.strip("/").split("/"), ""
    for m in morceaux:
        courant += "/" + m
        if not existe(sftp, courant):
            sftp.mkdir(courant)


def copier_distant(sftp, source, dest):
    """Copie un fichier distant vers un autre chemin distant, via un
    buffer local temporaire — le SFTP n'a pas de copie serveur-a-serveur."""
    with tempfile.NamedTemporaryFile(delete=False) as tmp:
        chemin_tmp = tmp.name
    try:
        sftp.get(source, chemin_tmp)
        sftp.put(chemin_tmp, dest)
    finally:
        os.remove(chemin_tmp)


def copier_dossier_distant(sftp, source, dest):
    rendre_dossier(sftp, dest)
    for entree in sftp.listdir_attr(source):
        s, d = f"{source}/{entree.filename}", f"{dest}/{entree.filename}"
        if stat_module.S_ISDIR(entree.st_mode):
            copier_dossier_distant(sftp, s, d)
        else:
            copier_distant(sftp, s, d)


def reporter_persistants(sftp, chemin_live, staging):
    """Reporte dans `staging` les fichiers/dossiers qui vivent uniquement
    sur le serveur (secrets, donnees membres) avant la bascule."""
    for f in PERSISTANTS_FICHIERS:
        src = f"{chemin_live}/{f}"
        if existe(sftp, src):
            print(f"  fichier persistant reporte : {f}")
            copier_distant(sftp, src, f"{staging}/{f}")
        else:
            print(f"  ATTENTION : {f} absent de la version en ligne actuelle, ignore")

    for d in PERSISTANTS_DOSSIERS:
        src = f"{chemin_live}/{d}"
        if existe(sftp, src):
            print(f"  dossier persistant reporte : {d}")
            copier_dossier_distant(sftp, src, f"{staging}/{d}")


def envoyer_contenu(sftp, cible):
    """Envoie tout le contenu de LOCAL vers le dossier `cible` (deja cree)."""
    envoyes = 0
    for racine, dossiers, fichiers in os.walk(LOCAL):
        dossiers[:] = [d for d in dossiers if d not in IGNORER_DOSSIERS]
        rel = os.path.relpath(racine, LOCAL).replace("\\", "/")
        dest = cible if rel == "." else f"{cible}/{rel}"
        rendre_dossier(sftp, dest)
        for f in sorted(fichiers):
            if f in IGNORER_FICHIERS:
                continue
            sftp.put(os.path.join(racine, f), f"{dest}/{f}")
            envoyes += 1
    return envoyes


def deployer():
    if not os.path.isdir(LOCAL):
        sys.exit(f"Dossier introuvable : {LOCAL}")

    chemin_live = os.environ["DEPLOY_PATH"]
    if not chemin_live:
        # Secret GitHub absent : la variable existe mais est vide. Sans
        # ce garde-fou on manipulerait des chemins du type "_new"/"_old"
        # a la racine du compte SFTP.
        sys.exit("DEPLOY_PATH est vide — secret GitHub manquant ou non configure.")
    staging = f"{chemin_live}_new"
    backup = f"{chemin_live}_old"

    t, sftp = connecter()
    try:
        if existe(sftp, staging):
            print(f"  nettoyage d'un dossier de build precedent : {staging}")
            supprimer_recursif(sftp, staging)

        print(f"  upload vers {staging}")
        rendre_dossier(sftp, staging)
        envoyes = envoyer_contenu(sftp, staging)
        print(f"  {envoyes} fichier(s) envoye(s)")

        reporter_persistants(sftp, chemin_live, staging)

        if not existe(sftp, chemin_live):
            # Premier deploiement sur cet environnement (ex. bootstrap d'un
            # dossier de production tout neuf) : rien a basculer, on installe
            # directement.
            print(f"  premier deploiement : creation directe de {chemin_live}")
            sftp.rename(staging, chemin_live)
            print("Deploiement termine (premiere installation, pas de sauvegarde).")
            return

        if existe(sftp, backup):
            print(f"  suppression de l'ancienne sauvegarde : {backup}")
            supprimer_recursif(sftp, backup)

        print(f"  bascule : {chemin_live} -> {backup}")
        sftp.rename(chemin_live, backup)
        try:
            print(f"  bascule : {staging} -> {chemin_live}")
            sftp.rename(staging, chemin_live)
        except Exception:
            print("  echec de la bascule, restauration de la version precedente")
            sftp.rename(backup, chemin_live)
            raise

        print(f"Deploiement termine. Ancienne version conservee dans {backup}")
    finally:
        t.close()


def rollback():
    chemin_live = os.environ["DEPLOY_PATH"]
    if not chemin_live:
        sys.exit("DEPLOY_PATH est vide — secret GitHub manquant ou non configure.")
    backup = f"{chemin_live}_old"

    t, sftp = connecter()
    try:
        if not existe(sftp, backup):
            # Pas de version precedente (ex. tout premier deploiement sur un
            # environnement neuf) : rien a restaurer, on retire juste la
            # version defaillante plutot que de la laisser en ligne.
            echoue = f"{chemin_live}_failed"
            if existe(sftp, echoue):
                supprimer_recursif(sftp, echoue)
            print(f"  pas de sauvegarde : mise de cote de {chemin_live} -> {echoue}")
            sftp.rename(chemin_live, echoue)
            print(f"Rollback termine (premier deploiement) : plus rien en ligne sur ce chemin. Version en echec conservee dans {echoue}.")
            return

        echoue = f"{chemin_live}_failed"
        if existe(sftp, echoue):
            supprimer_recursif(sftp, echoue)

        print(f"  mise de cote de la version en echec : {chemin_live} -> {echoue}")
        sftp.rename(chemin_live, echoue)
        print(f"  restauration : {backup} -> {chemin_live}")
        sftp.rename(backup, chemin_live)
        print(f"Rollback termine. Version en echec conservee dans {echoue} pour analyse.")
    finally:
        t.close()


if __name__ == "__main__":
    if len(sys.argv) != 2 or sys.argv[1] not in ("deploy", "rollback"):
        sys.exit("Usage : python deploy_ci.py [deploy|rollback]")
    (deployer if sys.argv[1] == "deploy" else rollback)()
