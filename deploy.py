"""
Deploiement du site Saumur Air Club vers l'espace de dev IONOS, par SFTP.

Usage :  python deploy.py

Le serveur n'a pas de shell : deployer = deposer des fichiers, rien d'autre.
Le fichier .htpasswd vit uniquement sur le serveur et n'est JAMAIS ecrase ici.
"""
import os
import stat
import sys

import paramiko

# --- Connexion ------------------------------------------------------
HOST = "home168617917.1and1-data.host"
PORT = 22
USER = "acc2123289695"
PWD = os.environ.get("SAC_SFTP_PWD") or "100%AeroclubSAUMURAIRCLUB@@%%$$$$$$£££££££zaercesaz"

LOCAL = os.path.join(os.path.dirname(os.path.abspath(__file__)), "site")
REMOTE = "/Aeroclub Saumur - Espace developpement"

# Ne jamais envoyer / ne jamais toucher
IGNORER_FICHIERS = {".DS_Store", "Thumbs.db", ".htpasswd"}
IGNORER_DOSSIERS = {".git", "__pycache__", "node_modules", "_originaux"}

# Vestiges de la phase de test, a supprimer s'ils trainent encore
A_SUPPRIMER = [
    "index.html",
    "robots.txt",   # remplace par robots.php, genere dynamiquement
    "probe.php",
    "assets/img/bon-cadeau.png",  # remplace par bon-cadeau.jpg (2,3 Mo -> 119 Ko)
    # Saira abandonnee : le design ne garde qu'Inter (inspiration Airbus)
    "assets/fonts/Saira-latin.woff2",
    "assets/fonts/Saira-latin-ext.woff2",
    # Agenda des vols retire du B.O. (le club n'en veut pas, 26/08)
    "admin/agenda.php",
    "admin/vol.php",
    "admin/partage-agenda.php",
    "agenda-ical.php",
]


def rendre_dossier(sftp, chemin):
    """mkdir -p, en SFTP."""
    morceaux, courant = chemin.strip("/").split("/"), ""
    for m in morceaux:
        courant += "/" + m
        try:
            sftp.stat(courant)
        except FileNotFoundError:
            sftp.mkdir(courant)


def deployer():
    t = paramiko.Transport((HOST, PORT))
    t.connect(username=USER, password=PWD)
    sftp = paramiko.SFTPClient.from_transport(t)

    envoyes = ignores = 0
    for racine, dossiers, fichiers in os.walk(LOCAL):
        dossiers[:] = [d for d in dossiers if d not in IGNORER_DOSSIERS]
        rel = os.path.relpath(racine, LOCAL).replace("\\", "/")
        cible = REMOTE if rel == "." else f"{REMOTE}/{rel}"
        rendre_dossier(sftp, cible)

        for f in sorted(fichiers):
            if f in IGNORER_FICHIERS:
                ignores += 1
                print(f"  ignore  {f}")
                continue
            local = os.path.join(racine, f)
            distant = f"{cible}/{f}"
            sftp.put(local, distant)
            taille = os.path.getsize(local)
            chemin = distant[len(REMOTE) + 1:]
            print(f"  envoi   {chemin:52s} {taille:>9,} o")
            envoyes += 1

    for f in A_SUPPRIMER:
        try:
            sftp.remove(f"{REMOTE}/{f}")
            print(f"  SUPPR.  {f}  (vestige de la phase de test)")
        except FileNotFoundError:
            pass

    print(f"\n{envoyes} fichier(s) envoye(s), {ignores} ignore(s).")
    t.close()


if __name__ == "__main__":
    if not os.path.isdir(LOCAL):
        sys.exit(f"Dossier introuvable : {LOCAL}")
    deployer()
