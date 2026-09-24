"""
Sauvegarde nocturne de la base de production sur l'espace SFTP IONOS.

Appele par .github/workflows/sauvegarde.yml :
  1. telecharge le dump SQL via https://aeroclub-saumur.fr/taches/export-db.php
     (le port MySQL est ferme cote IONOS, pas de mysqldump distant) ;
  2. verifie le marqueur d'integrite "-- END EXPORT OK" en fin de dump ;
  3. le compresse et l'envoie dans sauvegardes/, a cote du dossier du site
     (hors racine web, pas touche par la bascule de deploiement) ;
  4. ne garde que les RETENTION_JOURS dernieres sauvegardes.

Le secret passe par l'en-tete X-Export-Secret, jamais dans l'URL : sinon
il finirait dans les logs d'acces IONOS, rapatries par logs_ci.py.

Variables d'environnement : DEPLOY_HOST, DEPLOY_USER, DEPLOY_PASSWORD,
DEPLOY_PATH_PROD, OPS_SECRET.
"""
import datetime
import gzip
import os
import posixpath
import sys
import tempfile
import urllib.request

from deploy_ci import connecter, existe

URL_EXPORT = "https://aeroclub-saumur.fr/taches/export-db.php?what=db"
MARQUEUR_FIN = b"-- END EXPORT OK"
RETENTION_JOURS = 14
PREFIXE = "base-prod-"


def telecharger_dump(secret):
    req = urllib.request.Request(URL_EXPORT, headers={
        "X-Export-Secret": secret,
        "User-Agent": "saumur-sauvegarde/1.0",
    })
    with urllib.request.urlopen(req, timeout=180) as r:
        dump = r.read()
    if not dump.rstrip().endswith(MARQUEUR_FIN):
        # Dump tronque (timeout PHP, erreur en cours d'export...) : on
        # refuse de l'enregistrer, et surtout de faire tourner la rotation
        # qui supprimerait une bonne sauvegarde au profit d'une mauvaise.
        sys.exit(f"Dump incomplet : marqueur de fin absent ({len(dump)} octets recus).")
    if b"CREATE TABLE" not in dump:
        sys.exit("Dump sans aucune table : refuse.")
    return dump


def main():
    secret = os.environ.get("OPS_SECRET", "")
    chemin_site = os.environ.get("DEPLOY_PATH_PROD", "").rstrip("/")
    if not secret or not chemin_site:
        sys.exit("OPS_SECRET ou DEPLOY_PATH_PROD vide — secrets GitHub manquants.")

    dump = telecharger_dump(secret)
    print(f"  dump recu et verifie : {len(dump)} octets")

    horodatage = datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%d-%H%M%S")
    nom = f"{PREFIXE}{horodatage}.sql.gz"
    dossier = f"{posixpath.dirname(chemin_site) or '.'}/sauvegardes"

    with tempfile.NamedTemporaryFile(suffix=".sql.gz", delete=False) as tmp:
        tmp.write(gzip.compress(dump))
        local = tmp.name

    t, sftp = connecter()
    try:
        if not existe(sftp, dossier):
            print(f"  creation de {dossier}")
            sftp.mkdir(dossier, 0o750)
            with sftp.open(f"{dossier}/.htaccess", "w") as f:
                f.write("Require all denied\n")

        # Envoi sous un nom temporaire puis renommage : une coupure en
        # plein transfert ne laisse jamais un .sql.gz tronque qui passerait
        # pour une sauvegarde valide.
        sftp.put(local, f"{dossier}/{nom}.part")
        sftp.rename(f"{dossier}/{nom}.part", f"{dossier}/{nom}")
        print(f"  sauvegarde envoyee : {dossier}/{nom} ({os.path.getsize(local)} octets)")

        sauvegardes = sorted(n for n in sftp.listdir(dossier)
                             if n.startswith(PREFIXE) and n.endswith(".sql.gz"))
        for ancien in sauvegardes[:-RETENTION_JOURS]:
            sftp.remove(f"{dossier}/{ancien}")
            print(f"  rotation : suppression de {ancien}")
        for reste in (n for n in sftp.listdir(dossier) if n.endswith(".part")):
            sftp.remove(f"{dossier}/{reste}")
        print(f"Sauvegarde terminee. {min(len(sauvegardes), RETENTION_JOURS)} sauvegarde(s) conservee(s).")
    finally:
        t.close()
        os.remove(local)


if __name__ == "__main__":
    main()
