"""
Recuperation quotidienne des logs du serveur IONOS par SFTP.

Appele par .github/workflows/logs.yml. Pour chaque environnement (prod,
dev) :
  - log d'erreurs PHP : logs-php/<dossier du site>.log, ecrit par
    site/inc/journal-erreurs.php a cote du dossier du site. Il est
    renomme avant telechargement (PHP en recree un neuf aussitot, rien
    n'est perdu entre les deux), puis supprime du serveur une fois
    rapatrie : le serveur ne garde jamais que les erreurs non encore lues.
  - logs d'acces Apache : dossier logs/ fourni par IONOS (lecture seule,
    rotation geree par IONOS). On rapatrie les fichiers des derniers jours.

Tout atterrit dans logs-recuperes/ (publie en artifact par le workflow),
avec un resume HTML. Sortie GitHub "alerte=true" s'il y a des erreurs PHP
ou des reponses 5xx la veille : le workflow envoie alors le resume par
email via taches/notifier.php.

Usage :
    python logs_ci.py

Variables d'environnement : DEPLOY_HOST, DEPLOY_USER, DEPLOY_PASSWORD,
DEPLOY_PATH (dev), DEPLOY_PATH_PROD (prod). Un chemin vide est ignore.
"""
import datetime
import gzip
import html
import os
import posixpath
import re
import stat as stat_module
import sys

from deploy_ci import connecter, ecrire_sortie_github, existe

SORTIE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "logs-recuperes")

# Emplacement du dossier logs/ d'IONOS selon la racine du compte SFTP :
# a la racine de l'espace web, donc au-dessus si le compte est cantonne
# a un sous-dossier.
CANDIDATS_LOGS_ACCES = ["logs", "/logs", "../logs", "../../logs"]
JOURS_LOGS_ACCES = 3          # fichiers d'acces modifies depuis moins de N jours
MAX_LIGNES_EMAIL = 30         # lignes d'erreur PHP reprises dans l'email

MOIS_APACHE = ["Jan", "Feb", "Mar", "Apr", "May", "Jun",
               "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"]
# Format Apache "combined" : ... [24/Sep/2026:10:00:00 +0200] "GET /x HTTP/1.1" 500 ...
RE_ACCES = re.compile(r'\[(\d{2}/\w{3}/\d{4}):[^\]]*\] "(?:\S+) (\S+)[^"]*" (\d{3}) ')


def lire_texte(chemin):
    ouvrir = gzip.open if chemin.endswith(".gz") else open
    with ouvrir(chemin, "rt", encoding="utf-8", errors="replace") as f:
        return f.read()


def recuperer_log_php(sftp, nom_env, chemin_site, horodatage):
    """Renvoie la liste des lignes d'erreur PHP rapatriees (vide si aucune)."""
    parent = posixpath.dirname(chemin_site.rstrip("/")) or "."
    distant = f"{parent}/logs-php/{posixpath.basename(chemin_site.rstrip('/'))}.log"
    dossier = posixpath.dirname(distant)
    if not existe(sftp, dossier):
        print(f"  [{nom_env}] pas encore de dossier {dossier} : aucune erreur PHP journalisee")
        return []

    # Renommage d'abord : PHP ecrit aussitot dans un fichier neuf, et un
    # echec de telechargement laisse le fichier renomme en place pour
    # le passage suivant (repris par la boucle ci-dessous).
    if existe(sftp, distant):
        sftp.rename(distant, f"{distant}.{horodatage}.lu")

    lignes = []
    for nom in sorted(sftp.listdir(dossier)):
        if not (nom.startswith(posixpath.basename(distant) + ".") and nom.endswith(".lu")):
            continue
        source = f"{dossier}/{nom}"
        local = os.path.join(SORTIE, nom_env, "php-" + nom.replace(".lu", ""))
        sftp.get(source, local)
        lignes += [l for l in lire_texte(local).splitlines() if l.strip()]
        sftp.remove(source)
    print(f"  [{nom_env}] {len(lignes)} ligne(s) d'erreur PHP rapatriee(s)")
    return lignes


def trouver_logs_acces(sftp):
    for c in CANDIDATS_LOGS_ACCES:
        try:
            if stat_module.S_ISDIR(sftp.stat(c).st_mode):
                return c
        except (FileNotFoundError, PermissionError, OSError):
            continue
    return None


def recuperer_logs_acces(sftp):
    """Rapatrie les logs d'acces recents. Renvoie la liste des fichiers
    locaux, ou None si le dossier logs/ d'IONOS est introuvable."""
    dossier = trouver_logs_acces(sftp)
    if dossier is None:
        print("  logs d'acces : dossier logs/ introuvable depuis ce compte SFTP")
        return None
    limite = datetime.datetime.now().timestamp() - JOURS_LOGS_ACCES * 86400
    fichiers = []
    for e in sftp.listdir_attr(dossier):
        if stat_module.S_ISDIR(e.st_mode) or (e.st_mtime or 0) < limite:
            continue
        local = os.path.join(SORTIE, "acces", e.filename)
        sftp.get(f"{dossier}/{e.filename}", local)
        fichiers.append(local)
    print(f"  logs d'acces : {len(fichiers)} fichier(s) rapatrie(s) depuis {dossier}/")
    return fichiers


def erreurs_5xx(fichiers, jour):
    """Reponses 5xx du jour donne, regroupees par (code, URL)."""
    cle_jour = f"{jour.day:02d}/{MOIS_APACHE[jour.month - 1]}/{jour.year}"
    compte = {}
    for f in fichiers:
        for ligne in lire_texte(f).splitlines():
            m = RE_ACCES.search(ligne)
            if m and m.group(1) == cle_jour and m.group(3).startswith("5"):
                cle = (m.group(3), m.group(2)[:150])
                compte[cle] = compte.get(cle, 0) + 1
    return compte


def construire_resume(php, fichiers_acces, e5xx, hier):
    total_php = sum(len(l) for l in php.values())
    total_5xx = sum(e5xx.values())
    c = [f"<h2>Logs du serveur — {hier.strftime('%d/%m/%Y')}</h2>",
         "<table border='1' cellpadding='6' cellspacing='0'>",
         "<tr><th>Source</th><th>Résultat</th></tr>"]
    for env, lignes in php.items():
        c.append(f"<tr><td>Erreurs PHP ({env})</td><td>{len(lignes)} ligne(s)</td></tr>")
    if fichiers_acces is None:
        c.append("<tr><td>Réponses 5xx (veille)</td><td>logs d'accès introuvables</td></tr>")
    else:
        c.append(f"<tr><td>Réponses 5xx (veille)</td><td>{total_5xx}</td></tr>")
    c.append("</table>")

    for env, lignes in php.items():
        if lignes:
            extrait = lignes[-MAX_LIGNES_EMAIL:]
            c.append(f"<h3>Erreurs PHP — {env} ({len(extrait)} dernière(s) sur {len(lignes)})</h3>")
            c.append("<pre style='white-space:pre-wrap;font-size:12px'>"
                     + html.escape("\n".join(extrait)) + "</pre>")
    if e5xx:
        c.append("<h3>Réponses 5xx de la veille</h3><table border='1' cellpadding='4' cellspacing='0'>"
                 "<tr><th>Code</th><th>URL</th><th>Nombre</th></tr>")
        for (code, url), n in sorted(e5xx.items(), key=lambda x: -x[1])[:20]:
            c.append(f"<tr><td>{code}</td><td>{html.escape(url)}</td><td>{n}</td></tr>")
        c.append("</table>")
    c.append("<p>Logs complets : artifact « logs-serveur » du workflow GitHub Actions (30 jours).</p>")
    return "\n".join(c), total_php, total_5xx


def main():
    environnements = {
        "prod": os.environ.get("DEPLOY_PATH_PROD", ""),
        "dev": os.environ.get("DEPLOY_PATH", ""),
    }
    environnements = {k: v for k, v in environnements.items() if v}
    if not environnements:
        sys.exit("DEPLOY_PATH et DEPLOY_PATH_PROD vides — secrets GitHub manquants.")

    for d in list(environnements) + ["acces"]:
        os.makedirs(os.path.join(SORTIE, d), exist_ok=True)

    maintenant = datetime.datetime.now(datetime.timezone.utc)
    horodatage = maintenant.strftime("%Y%m%d-%H%M%S")
    hier = (maintenant - datetime.timedelta(days=1)).date()

    t, sftp = connecter()
    try:
        php = {env: recuperer_log_php(sftp, env, chemin, horodatage)
               for env, chemin in environnements.items()}
        fichiers_acces = recuperer_logs_acces(sftp)
    finally:
        t.close()

    e5xx = erreurs_5xx(fichiers_acces or [], hier)
    resume, total_php, total_5xx = construire_resume(php, fichiers_acces, e5xx, hier)
    with open(os.path.join(SORTIE, "resume.html"), "w", encoding="utf-8") as f:
        f.write(resume)

    alerte = total_php > 0 or total_5xx > 0
    print(f"Erreurs PHP : {total_php} — reponses 5xx hier : {total_5xx} — alerte : {alerte}")
    ecrire_sortie_github("alerte", "true" if alerte else "false")


if __name__ == "__main__":
    main()
