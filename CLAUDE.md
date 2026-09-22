## Workflow git (toute session Claude Code, cloud ou locale)

Après chaque développement fonctionnel (fonctionnalité ou correction terminée, testée si possible) :
1. Commit avec un message `type(scope): description courte` en français, dans le style de l'historique existant (`feat`, `fix`, `refactor`, `chore`, `docs`, `test`).
2. Push automatique vers la branche de travail en cours — sans attendre de confirmation à chaque fois.
3. Un message d'une ligne suffit pour informer l'utilisateur (pas besoin d'attendre son accord avant de committer).

Exception : ne PAS auto-committer/push sans demander d'abord si le changement touche à la production, à la sécurité, ou à un système avec un effet de bord réel (email envoyé, argent déplacé, données clients modifiées...) — dans ce cas, présenter le changement et attendre validation explicite.

## Serveur de production et actions à distance

Le vrai serveur de prod est le VPS Hostinger (72.62.238.145), pas de Raspberry. Avant de demander un accès SSH/terminal : la session Claude ne peut pas s'y connecter directement (réseau bloqué), donc tout passe par GitHub Actions, qui n'a pas cette restriction.

1. **D'abord vérifier l'existant** : `.github/workflows/`, `DEPLOIEMENT.md` (ou équivalent), et les secrets déjà configurés (Settings → Secrets → Actions — souvent `DEPLOY_HOST`/`DEPLOY_USER`/`DEPLOY_SSH_KEY` existent déjà). Si un déploiement échoue, regarder direct les logs du run GitHub Actions raté avant toute exploration manuelle. Ne rien recréer qui existe déjà.
2. **Pour une action ponctuelle qui n'existe pas encore** (pas un déploiement classique) : créer un workflow `on: workflow_dispatch` avec des `inputs:` pour le formulaire, réutiliser les secrets SSH existants, construire les données avec `jq` (jamais en concaténant du texte à la main) et les encoder en base64 avant l'envoi SSH (évite les bugs d'accents/guillemets). Un script déjà présent sur le serveur décode, va chercher lui-même les secrets nécessaires dans son `.env` local (jamais exposés à GitHub ni à Claude), et exécute l'action. Exemple à copier : `.github/workflows/create-manual-order.yml` + `scripts/create-manual-order-remote.sh` dans le dépôt `ob3m-distribution/ob3mdistribution-next`.
