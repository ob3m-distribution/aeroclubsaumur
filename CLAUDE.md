## Workflow git (toute session Claude Code, cloud ou locale)

Après chaque développement fonctionnel (fonctionnalité ou correction terminée, testée si possible) :
1. Commit avec un message `type(scope): description courte` en français, dans le style de l'historique existant (`feat`, `fix`, `refactor`, `chore`, `docs`, `test`).
2. Push automatique vers la branche de travail en cours — sans attendre de confirmation à chaque fois.
3. Un message d'une ligne suffit pour informer l'utilisateur (pas besoin d'attendre son accord avant de committer).

Exception : ne PAS auto-committer/push sans demander d'abord si le changement touche à la production, à la sécurité, ou à un système avec un effet de bord réel (email envoyé, argent déplacé, données clients modifiées...) — dans ce cas, présenter le changement et attendre validation explicite.

## Infrastructure de déploiement — VPS Hostinger (à conserver)

Le serveur de production est le VPS Hostinger **72.62.238.145** (pas de Raspberry). Une session Claude ne peut pas s'y connecter en SSH directement (réseau sortant bloqué côté Claude) — tout passe par GitHub Actions, qui n'a pas cette restriction.

Avant toute action de déploiement/dépannage :
1. Vérifier l'existant d'abord : `.github/workflows/`, `DEPLOIEMENT.md` (ou équivalent), et les secrets déjà configurés (Settings → Secrets → Actions — souvent `DEPLOY_HOST`/`DEPLOY_USER`/`DEPLOY_SSH_KEY` existent déjà pour ce dépôt). Ne rien recréer qui existe déjà.
2. Si un déploiement échoue : regarder directement les logs du run GitHub Actions raté (outil `get_job_logs`) avant toute exploration manuelle du serveur.
3. Pour une action ponctuelle qui n'existe pas encore (pas un déploiement classique) : créer un workflow `on: workflow_dispatch` avec des `inputs:` pour le formulaire, réutiliser les secrets SSH existants, construire les données avec `jq` (jamais en concaténant du texte à la main) et les encoder en base64 avant l'envoi SSH (évite les bugs d'accents/guillemets). Exemple à copier : `.github/workflows/create-manual-order.yml` + `scripts/create-manual-order-remote.sh` dans `ob3mdistribution-next`.
4. **Commande root libre déjà en place** : le workflow `.github/workflows/run-root-command.yml` (dans `ob3mdistribution-next`, secret `ROOT_SSH_KEY`) exécute n'importe quelle commande shell en root sur ce VPS, déclenchable directement via l'API GitHub (`workflow_dispatch`, inputs `command` + `confirm: "OUI"`) — sans copier-coller ni accès terminal. Couvre lattesexpress-next, enfants, bourse, ob3mdistribution-next, aeroclubsaumur (tout ce qui tourne sur ce VPS). Ne couvre pas aob-diffusion (aucun serveur, tourne sur GitHub Actions) ni association-parents-eleves/formation-charlotte/formation-lilou (jamais localisés sur ce VPS).
5. **Gestion du VPS depuis l'extérieur (API Hostinger)** : le workflow `.github/workflows/hostinger-api.yml` (dans `ob3mdistribution-next`, secret `HOSTINGER_API_TOKEN`) appelle n'importe quel endpoint de l'API Hostinger (`https://developers.hostinger.com/api/...`, ex. `vps/v1/virtual-machines`) — utile pour redémarrer/éteindre le VPS, gérer sauvegardes/pare-feu, **même si le serveur ne répond plus en SSH** (contrairement à `run-root-command.yml`, qui agit *dans* l'OS). Déclenchable via `workflow_dispatch` (inputs `method`, `path`, `body` optionnel, `confirm: "OUI"`).

⚠️ Root sur ce serveur = accès à TOUS les sites qui y tournent, pas juste celui-ci — utiliser avec précaution (la confirmation `OUI` dans le formulaire GitHub est volontairement obligatoire).

⚠️ Le jeton `HOSTINGER_API_TOKEN` n'a pas d'option de portée/scope à la création (vérifié le 30/09) — il donne potentiellement accès à tout le compte Hostinger, pas seulement ce VPS. Même précaution que pour le root SSH.

⚠️ Piège connu du fichier `authorized_keys` sur ce serveur : un `cat clé.pub >> authorized_keys` sans retour à la ligne préalable colle la nouvelle clé à la fin de la ligne précédente (ligne illisible pour SSH, clé silencieusement inactive). Toujours faire précéder/suivre d'un `printf '\n'` et vérifier avec `tail` après coup.
