## Workflow git (toute session Claude Code, cloud ou locale)

Après chaque développement fonctionnel (fonctionnalité ou correction terminée, testée si possible) :
1. Commit avec un message `type(scope): description courte` en français, dans le style de l'historique existant (`feat`, `fix`, `refactor`, `chore`, `docs`, `test`).
2. Push automatique vers la branche de travail en cours — sans attendre de confirmation à chaque fois.
3. Un message d'une ligne suffit pour informer l'utilisateur (pas besoin d'attendre son accord avant de committer).

Exception : ne PAS auto-committer/push sans demander d'abord si le changement touche à la production, à la sécurité, ou à un système avec un effet de bord réel (email envoyé, argent déplacé, données clients modifiées...) — dans ce cas, présenter le changement et attendre validation explicite.
