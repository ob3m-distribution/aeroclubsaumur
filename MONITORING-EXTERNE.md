# Filet de surveillance externe — à activer à la mise en PROD

## Pourquoi
La surveillance interne (Sentinelle sur le VPS, alertes par e-mail IONOS) ne peut **pas**
prévenir quand le site est **totalement** hors service, car l'e-mail dépend d'IONOS
lui-même. Un pinger **externe** interroge le site depuis ses propres serveurs et alerte
par **son propre canal** (indépendant d'IONOS) : c'est le seul moyen d'être prévenu
d'une panne totale. C'est un **filet de secours**, il complète (ne remplace pas)
la Sentinelle, la sauvegarde et l'audit déjà en place.

## Service recommandé
**UptimeRobot** — https://uptimerobot.com — plan **gratuit** (50 moniteurs, intervalle 5 min).
Alternatives équivalentes : BetterStack (Better Uptime), HetrixTools, Pingdom.

## Réglages exacts (à saisir dans UptimeRobot)
1. Créer un compte (adresse : ob3m.distribution@gmail.com) et valider l'e-mail.
2. **Add New Monitor** :
   - **Monitor Type** : *Keyword* (mieux que « HTTP simple » : détecte aussi une page
     qui répond 200 mais cassée).
   - **URL** : `https://www.aeroclub-saumur.fr/`  *(⚠ mettre le domaine public réel retenu)*
   - **Keyword to find** : `Saumur Air Club`  *(present dans le pied de page)*
   - **Alert if keyword** : *not exists*
   - **Monitoring Interval** : `5 minutes`
3. **Alert Contacts** :
   - E-mail : `ob3m.distribution@gmail.com`
   - (optionnel) appli mobile UptimeRobot pour une notif push / SMS.
4. **Advanced** :
   - *Send alerts after* : **2** échecs consécutifs (évite les faux positifs).
   - Aucune authentification requise en prod (le Basic Auth du dev aura été retiré).

## Optionnel (2e moniteur)
- **URL** : `https://www.aeroclub-saumur.fr/admin` — type *HTTP(s)*, alerte si code ≠ 200/302.
  Vérifie que le back-office reste joignable.

## Important
- Ne PAS pointer sur le **dev** (`dev.aeroclub-saumur.fr`) : il est derrière Basic Auth,
  le moniteur verrait un 401 permanent. Le filet externe n'a de sens que sur la **prod**.
- Une fois le domaine public confirmé, l'activation prend ~2 minutes.
- Aucune clé/secret à stocker : ce service est autonome et externe.
