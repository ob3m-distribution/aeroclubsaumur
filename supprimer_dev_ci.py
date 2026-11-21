"""
Suppression de l'espace de developpement sur l'espace SFTP IONOS.

L'espace dev (dev.aeroclub-saumur.fr) n'est plus deploye : ce script
retire ses dossiers du serveur pour liberer la place. Appele par
.github/workflows/supprimer-espace-dev.yml, a la demande uniquement.

Usage :
    python supprimer_dev_ci.py mesurer     # n'efface rien, affiche les tailles
    python supprimer_dev_ci.py supprimer   # efface l'espace dev

Ce qui est vise, a partir de DEPLOY_PATH (dossier dev) :
  - <dev>, <dev>_old, <dev>_new, <dev>_failed
  - logs-php/<dev>.log et ses copies en attente de lecture

La production n'est jamais visee : le script s'arrete avant toute
suppression si un chemin vise recoupe DEPLOY_PATH_PROD.

Variables d'environnement : DEPLOY_HOST, DEPLOY_USER, DEPLOY_PASSWORD,
DEPLOY_PATH (dev), DEPLOY_PATH_PROD (prod).
"""
import os
import posixpath
import stat as stat_module
import sys

from deploy_ci import connecter, existe, supprimer_recursif

SUFFIXES = ["", "_old", "_new", "_failed"]


def normaliser(chemin):
    return chemin.strip().rstrip("/")


def recoupe(a, b):
    """Vrai si a et b designent le meme chemin ou si l'un contient l'autre."""
    a, b = a.strip("/"), b.strip("/")
    return a == b or a.startswith(b + "/") or b.startswith(a + "/")


def verifier_cibles(dev, prod):
    """Arrete tout si la moindre cible touche a la production."""
    if not dev:
        sys.exit("DEPLOY_PATH est vide — secret GitHub manquant, rien a faire.")
    if not prod:
        sys.exit("DEPLOY_PATH_PROD est vide — impossible de verifier que la "
                 "production n'est pas visee. Abandon.")
    if "developpement" not in dev.lower():
        sys.exit("DEPLOY_PATH ne ressemble pas au dossier de developpement "
                 "(« developpement » absent du nom). Abandon.")
    proteges = [prod + s for s in SUFFIXES]
    for cible in [dev + s for s in SUFFIXES]:
        for p in proteges:
            if recoupe(cible, p):
                sys.exit("Une cible recoupe le dossier de production. Abandon, "
                         "rien n'a ete supprime.")


def taille(sftp, chemin):
    """(octets, nombre de fichiers) d'un fichier ou d'un dossier distant."""
    attr = sftp.stat(chemin)
    if not stat_module.S_ISDIR(attr.st_mode):
        return attr.st_size or 0, 1
    octets = fichiers = 0
    for e in sftp.listdir_attr(chemin):
        if stat_module.S_ISDIR(e.st_mode):
            o, f = taille(sftp, f"{chemin}/{e.filename}")
            octets, fichiers = octets + o, fichiers + f
        else:
            octets, fichiers = octets + (e.st_size or 0), fichiers + 1
    return octets, fichiers


def lisible(octets):
    return f"{octets / 1048576:,.1f} Mo".replace(",", " ")


def cibles_presentes(sftp, dev, prod):
    cibles = [dev + s for s in SUFFIXES if existe(sftp, dev + s)]

    parent = posixpath.dirname(dev) or "."
    dossier_logs = f"{parent}/logs-php"
    nom_log = posixpath.basename(dev) + ".log"
    nom_log_prod = posixpath.basename(prod) + ".log"
    if existe(sftp, dossier_logs):
        for nom in sorted(sftp.listdir(dossier_logs)):
            if nom.startswith(nom_log_prod):
                continue
            if nom == nom_log or nom.startswith(nom_log + "."):
                cibles.append(f"{dossier_logs}/{nom}")
    return cibles


def afficher_occupation(sftp, dev):
    """Taille de chaque element a cote du dossier dev (lecture seule)."""
    parent = posixpath.dirname(dev) or "."
    print(f"Occupation de l'espace SFTP ({parent}) :")
    total = 0
    for e in sorted(sftp.listdir_attr(parent), key=lambda x: x.filename):
        try:
            o, f = taille(sftp, f"{parent}/{e.filename}")
        except (PermissionError, OSError) as err:
            print(f"  {'?':>12}  {e.filename}  (illisible : {err})")
            continue
        total += o
        print(f"  {lisible(o):>12}  {f:>6} fichier(s)  {e.filename}")
    print(f"  {lisible(total):>12}  au total\n")


def main(mode):
    dev = normaliser(os.environ.get("DEPLOY_PATH", ""))
    prod = normaliser(os.environ.get("DEPLOY_PATH_PROD", ""))
    verifier_cibles(dev, prod)

    t, sftp = connecter()
    try:
        afficher_occupation(sftp, dev)

        cibles = cibles_presentes(sftp, dev, prod)
        if not cibles:
            print("Aucun element de l'espace dev sur le serveur : rien a supprimer.")
            return

        print("Elements de l'espace dev :")
        total = 0
        for c in cibles:
            o, f = taille(sftp, c)
            total += o
            print(f"  {lisible(o):>12}  {f:>6} fichier(s)  {c}")
        print(f"  {lisible(total):>12}  au total\n")

        if mode == "mesurer":
            print("Mode mesure : rien n'a ete supprime.")
            return

        for c in cibles:
            print(f"  suppression de {c}")
            if stat_module.S_ISDIR(sftp.stat(c).st_mode):
                supprimer_recursif(sftp, c)
            else:
                sftp.remove(c)
        print(f"\nEspace dev supprime : {lisible(total)} liberes.")
    finally:
        t.close()


if __name__ == "__main__":
    if len(sys.argv) != 2 or sys.argv[1] not in ("mesurer", "supprimer"):
        sys.exit("Usage : python supprimer_dev_ci.py [mesurer|supprimer]")
    main(sys.argv[1])
