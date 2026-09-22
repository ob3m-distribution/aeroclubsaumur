# Déploiement automatique (CI/CD)

## Ce qui a changé

Chaque push sur `main` déclenche désormais un déploiement automatique vers
**deux environnements distincts**, en parallèle, via GitHub Actions
(`.github/workflows/deploy.yml`) :

- **dev** (`dev.aeroclub-saumur.fr`) — dossier `Aeroclub Saumur - Espace
  developpement`, conservé pour tester les développements avant la prod.
- **prod** (`aeroclub-saumur.fr`) — dossier séparé, dédié à la production
  (ses propres secrets : vraies clés Stripe, pas de protection par mot de
  passe, pas de fausses données de test).

Ce sont deux dossiers différents sur le même compte SFTP, chacun avec ses
propres fichiers persistants (secrets, données membres) et son propre
historique de sauvegarde. Un échec de déploiement sur l'un ne bloque pas
l'autre (jobs et `concurrency` séparés).

**Le déploiement manuel avec `deploy.py` reste possible** et n'a pas changé
— c'est un filet de sécurité si la CI est indisponible, mais il ne cible
que le dossier dev historique.

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

Un seul niveau de sauvegarde est conservé par environnement (le
déploiement précédent) — pas un historique complet, ce n'est pas du
versionnement.

**Premier déploiement sur un dossier qui n'existe pas encore** (cas du
tout premier déploiement sur le dossier prod) : `deploy_ci.py` le détecte
et installe directement, sans bascule ni sauvegarde (il n'y a rien à
sauvegarder). Un rollback sur un premier déploiement en échec retire
simplement le dossier au lieu de restaurer une version précédente
inexistante.

## Comment `.htaccess` s'adapte tout seul entre dev et prod

`site/.htaccess` est déployé **identique** sur les deux dossiers. Les deux
blocs propres au dev (mot de passe Basic Auth, interdiction d'indexer)
sont conditionnés au nom d'hôte avec `<If "%{HTTP_HOST} == '...'">` :
Apache ne les applique que sur `dev.aeroclub-saumur.fr`, jamais sur
`aeroclub-saumur.fr`. Pas besoin de maintenir deux fichiers `.htaccess`
différents.

## Secrets GitHub à configurer

Dans **Settings → Secrets and variables → Actions** du dépôt :

| Secret | Valeur |
|---|---|
| `DEPLOY_HOST` | `home168617917.1and1-data.host` |
| `DEPLOY_USER` | `acc2123289695` |
| `DEPLOY_PASSWORD` | le mot de passe SFTP du compte de déploiement |
| `DEPLOY_PATH` | `Aeroclub Saumur - Espace developpement` (dossier **dev**) |
| `DEPLOY_PATH_PROD` | dossier **prod** (ex. `Aeroclub Saumur - Production`) |
| `DEV_BASICAUTH_USER` | `sac` (protection de l'espace dev) |
| `DEV_BASICAUTH_PASSWORD` | le mot de passe Basic Auth de l'espace dev |

`DEPLOY_HOST`/`DEPLOY_USER`/`DEPLOY_PASSWORD` sont partagés (même compte
SFTP) — seul le chemin de destination change entre dev et prod. Le
healthcheck prod ne prend pas de Basic Auth : `aeroclub-saumur.fr` doit
être librement accessible.

⚠️ **Les deux secrets `DEV_BASICAUTH_*` sont indispensables au healthcheck
dev.** S'ils sont absents ou faux, le healthcheck reçoit un 401 et
déclenche un rollback à chaque déploiement — même si le déploiement
lui-même s'est bien passé.

## Bootstrap du dossier de production

Le dossier prod n'existe pas tant que personne ne l'a créé. Avant le tout
premier push après ajout de `DEPLOY_PATH_PROD` :

1. `inc/config-local.php` doit être déposé une fois manuellement dans le
   futur dossier prod, avec les **vraies clés Stripe live** — sans lui, le
   premier déploiement prod se terminera comme le tout premier test dev
   (500, rollback automatique) faute de secrets.
2. Le domaine `aeroclub-saumur.fr` doit pointer vers ce dossier (panneau
   IONOS) pour que le healthcheck puisse le joindre.

Une fois ces deux points faits, le pipeline gère tout le reste
automatiquement, dev et prod en parallèle, à chaque push.
