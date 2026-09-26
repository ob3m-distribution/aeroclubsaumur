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
4. Healthcheck : le workflow vérifie que `http://dev.aeroclub-saumur.fr/`
   répond bien (200 ou 302). En HTTP simple, pas HTTPS : le certificat SSL
   de ce sous-domaine a été réaffecté au domaine principal lors de la mise
   en production (23/09/2026), sur décision du club — pas besoin de SSL
   pour un espace de test protégé par mot de passe.
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
| `DEPLOY_PATH_PROD` | `Aeroclub Saumur - Production` |
| `DEV_BASICAUTH_USER` | `sac` (protection de l'espace dev) |
| `DEV_BASICAUTH_PASSWORD` | le mot de passe Basic Auth de l'espace dev |
| `PROD_DB_HOST` | hôte MySQL/MariaDB de la base de production |
| `PROD_DB_PORT` | port (3306) |
| `PROD_DB_NAME` | nom de la base de production |
| `PROD_DB_USER` | utilisateur de la base de production |
| `PROD_DB_PASSWORD` | mot de passe de la base de production |
| `PROD_STRIPE_PK` | clé publique Stripe live (`pk_live_...`) |
| `PROD_STRIPE_SK` | clé secrète Stripe live (`sk_live_...`) |
| `PROD_STRIPE_WHSEC` | secret de signature du webhook Stripe live (`whsec_...`) |

`DEPLOY_HOST`/`DEPLOY_USER`/`DEPLOY_PASSWORD` sont partagés (même compte
SFTP) — seul le chemin de destination change entre dev et prod. Le
healthcheck prod ne prend pas de Basic Auth : `aeroclub-saumur.fr` doit
être librement accessible.

Les 8 secrets `PROD_DB_*`/`PROD_STRIPE_*` alimentent la génération
automatique de `inc/config-local.php` sur la prod (voir section
suivante) — ils ne servent pas pour le dev, qui garde son
`config-local.php` géré à la main sur le serveur.

⚠️ **Les deux secrets `DEV_BASICAUTH_*` sont indispensables au healthcheck
dev.** S'ils sont absents ou faux, le healthcheck reçoit un 401 et
déclenche un rollback à chaque déploiement — même si le déploiement
lui-même s'est bien passé.

## `inc/config-local.php` en prod : généré, pas déposé à la main

Sur le job prod, `deploy_ci.py` reçoit `GENERER_CONFIG_LOCAL=1` : au lieu
de reporter un fichier existant (comme pour `.htpasswd` ou comme le fait
le dev), il **régénère `inc/config-local.php` à chaque déploiement** à
partir des 8 secrets `PROD_DB_*`/`PROD_STRIPE_*`. Avantages :
- jamais besoin de déposer ce fichier à la main sur le serveur ;
- changer une clé Stripe ou un mot de passe de base = mettre à jour le
  secret GitHub, le déploiement suivant s'en charge ;
- pas de risque de fichier resté périmé après une rotation de secret.

Le dev, lui, garde son `config-local.php` géré à la main sur le serveur
(comportement historique, inchangé) — `GENERER_CONFIG_LOCAL` n'est pas
défini sur son job.

## Bootstrap du dossier de production

Le dossier prod n'existe pas tant que personne ne l'a créé. Avant le tout
premier push après ajout de `DEPLOY_PATH_PROD` :

1. Les 8 secrets `PROD_DB_*`/`PROD_STRIPE_*` doivent être renseignés —
   tant que `PROD_STRIPE_SK` (ou un autre) est absent, `config-local.php`
   sera généré avec une valeur vide pour ce champ : le dossier sera bien
   créé, mais les fonctionnalités concernées (paiement, base de données)
   ne marcheront pas tant que le secret manquant n'est pas ajouté — sans
   gravité tant qu'aucun domaine ne pointe encore dessus.
2. Le domaine `aeroclub-saumur.fr` doit pointer vers ce dossier (panneau
   IONOS) pour que le healthcheck puisse le joindre.

Une fois ces deux points faits, le pipeline gère tout le reste
automatiquement, dev et prod en parallèle, à chaque push.

## Récupération quotidienne des logs du serveur

`.github/workflows/logs.yml` tourne chaque jour à 4h UTC (et à la
demande via *Run workflow*) et exécute `logs_ci.py`, qui réutilise la
connexion SFTP du déploiement :

- **Erreurs PHP** : sans réglage, `error_log()` écrit dans le log
  d'erreurs Apache d'IONOS, illisible pour le client.
  `site/inc/journal-erreurs.php` (inclus par `config.php` et `db.php`)
  les redirige vers `logs-php/<dossier du site>.log`, **à côté** du
  dossier du site : hors de la racine web, et pas écrasé par la bascule
  de déploiement. Le script le renomme, le rapatrie puis le supprime —
  le serveur ne garde que les erreurs pas encore lues.
- **Logs d'accès Apache** : dossier `logs/` fourni par IONOS (IP
  anonymisées, rotation gérée par IONOS). Les fichiers des 3 derniers
  jours sont rapatriés ; les réponses 5xx de la veille sont comptées.
  Si le compte SFTP n'a pas accès à ce dossier, le résumé l'indique.

Tout est archivé comme artifact `logs-serveur` du workflow (30 jours).
S'il y a au moins une erreur PHP ou une réponse 5xx, un résumé part par
email via `taches/notifier.php` (secret `PROD_OPS_SECRET`). Aucun
nouveau secret à configurer.

## Sauvegarde nocturne de la base

`.github/workflows/sauvegarde.yml` tourne chaque nuit à 1h UTC (et à la
demande) et exécute `sauvegarde_ci.py` :

1. export de la base de production par
   `https://aeroclub-saumur.fr/taches/export-db.php?what=db` (le port
   MySQL est fermé chez IONOS), secret `PROD_OPS_SECRET` passé en en-tête ;
2. refus du dump si le marqueur de fin `-- END EXPORT OK` manque
   (export tronqué) — dans ce cas rien n'est écrit ni supprimé ;
3. envoi compressé dans `sauvegardes/base-prod-<date>.sql.gz`, **à côté**
   du dossier du site (hors racine web, `.htaccess` qui refuse tout,
   non touché par les déploiements) ;
4. rotation : seules les 14 dernières sauvegardes sont conservées.

En cas d'échec, un email part via `taches/notifier.php` (GitHub envoie
aussi sa propre notification d'échec de workflow).

**Restaurer** : télécharger le `.sql.gz` voulu par SFTP, le décompresser
et l'importer via phpMyAdmin (panneau IONOS). Le dump commence par
`DROP TABLE IF EXISTS` : il remplace les tables existantes.

**Limites** : les sauvegardes sont sur le même hébergement que le site
— elles protègent d'une erreur (suppression, migration ratée), pas
d'une perte totale du compte IONOS. Les fichiers déposés par les
adhérents (`docs-inscriptions/`, `docs-adherents/`, `uploads/`) ne
sont pas inclus : seule la base l'est.
