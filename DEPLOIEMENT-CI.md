# Déploiement automatique (CI/CD)

## Ce qui a changé

Chaque push sur `main` déclenche désormais un déploiement automatique vers
l'environnement de dev (`dev.aeroclub-saumur.fr`), via GitHub Actions
(`.github/workflows/deploy.yml`).

**Le déploiement manuel avec `deploy.py` reste possible** et n'a pas changé
— c'est un filet de sécurité si la CI est indisponible.

## Pourquoi ce n'est pas du SSH classique

Le serveur IONOS est en hébergement mutualisé, **SFTP uniquement, sans
shell**. Le pipeline ne peut donc pas faire de `git pull` côté serveur ni
redémarrer un process : tout passe par des opérations SFTP (upload,
renommage de dossier, suppression), exécutées par `deploy_ci.py` depuis le
runner GitHub Actions.

## Comment se passe un déploiement

1. Upload de l'intégralité de `site/` dans un dossier temporaire
   (`<dossier live>_new`), sans toucher au site en ligne.
2. **Report des fichiers/dossiers persistants** depuis le site actuellement
   en ligne vers ce dossier temporaire : `.htpasswd`, `inc/config-local.php`
   (secrets DB/Stripe/mail), `docs-inscriptions/`, `docs-adherents/`,
   `uploads/`. Ce sont des éléments qui ne sont **jamais dans git**
   (`.gitignore`) — secrets ou données réelles d'adhérents — mais qui
   existent sur le serveur et doivent survivre à chaque déploiement.
3. Bascule par renommage : le dossier actuellement en ligne devient
   `<dossier live>_old`, le nouveau prend sa place. C'est quasi instantané
   côté visiteurs.
4. Healthcheck : le workflow vérifie que `https://dev.aeroclub-saumur.fr/`
   répond bien (200 ou 302).
5. **Si le healthcheck échoue**, rollback automatique : la version
   précédente (`_old`) est remise en place, et la version défaillante est
   conservée dans `<dossier live>_failed` pour investigation.

⚠️ **La liste de l'étape 2 doit rester synchronisée avec `.gitignore`.** Si
un nouveau fichier ou dossier persistant (secret, données réelles) est
ajouté au `.gitignore` du site, il faut l'ajouter aussi dans
`PERSISTANTS_FICHIERS`/`PERSISTANTS_DOSSIERS` en tête de `deploy_ci.py`,
sans quoi il sera silencieusement absent du site après le déploiement
suivant.

Un seul niveau de sauvegarde est conservé (le déploiement précédent) — pas
un historique complet, ce n'est pas du versionnement.

## Secrets GitHub à configurer

Dans **Settings → Secrets and variables → Actions** du dépôt :

| Secret | Valeur |
|---|---|
| `DEPLOY_HOST` | `home168617917.1and1-data.host` |
| `DEPLOY_USER` | `acc2123289695` |
| `DEPLOY_PASSWORD` | le mot de passe SFTP du compte de déploiement |
| `DEPLOY_PATH` | `Aeroclub Saumur - Espace developpement` |
| `DEV_BASICAUTH_USER` | `sac` (protection de l'espace dev) |
| `DEV_BASICAUTH_PASSWORD` | le mot de passe Basic Auth de l'espace dev |

⚠️ **Les deux secrets `DEV_BASICAUTH_*` sont indispensables au healthcheck.**
S'ils sont absents ou faux, le healthcheck reçoit un 401 et déclenche un
rollback à chaque déploiement — même si le déploiement lui-même s'est bien
passé.

## À savoir pour le premier push

Le premier push qui ajoute ces fichiers déclenchera le workflow avant même
que les secrets soient configurés : il échouera proprement dès la
connexion SFTP (variables d'environnement manquantes), sans toucher au
site. C'est normal — le déploiement réel se fera au push suivant, une fois
les secrets renseignés.

## Quand le site passera en production

Ce pipeline cible aujourd'hui `dev.aeroclub-saumur.fr`. Le jour où le
domaine définitif est choisi et le site basculé en production, il faudra
retraiter cette pipeline comme un changement touchant la production (donc
avec validation explicite avant modification), pas la laisser continuer à
tourner automatiquement sans y repasser.
