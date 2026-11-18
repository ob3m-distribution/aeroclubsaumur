# Paiement en ligne avec HelloAsso

Depuis novembre 2026, le site encaisse les cotisations et les bons cadeaux
avec **HelloAsso** (à la place de Stripe).

## Comment ça marche
1. Le client clique sur « Payer » : le site crée le paiement chez HelloAsso
   (le montant vient toujours du site, jamais du navigateur) et envoie le
   client sur la page de paiement HelloAsso.
2. Après le paiement, HelloAsso le ramène sur le site (`/merci` ou
   `/merci-inscription`) et envoie une notification (`/webhook/helloasso.php`).
3. **Le site ne croit ni l'adresse de retour ni la notification** : à chacune,
   il demande à l'API HelloAsso si la commande est bien payée, pour le bon
   montant, avant de valider l'inscription ou d'envoyer le bon.
4. Chaque paiement créé est enregistré dans la table `paiements_helloasso`.

HelloAsso est gratuit pour l'association : il propose au payeur une
**contribution volontaire** (facultative, qu'il peut ramener à 0 €). Le site
le dit sur la page de paiement.

## Mise en route (une seule fois)
1. **Compte HelloAsso** : l'association doit être vérifiée et autorisée à
   encaisser (HelloAsso renvoie sinon l'erreur 409). L'API « Checkout » doit
   être activée pour le compte : à demander au support HelloAsso si la clé
   d'API ne l'autorise pas.
2. **Clés d'API** : HelloAsso > Mon compte > Intégrations et API > créer une
   clé. Noter l'identifiant client et le secret client.
3. **Identifiant de l'association (« slug »)** : la fin de l'adresse de la page
   HelloAsso de l'association (`helloasso.com/associations/<slug>`).
4. **Secrets GitHub** (dépôt > Settings > Secrets and variables > Actions) :

   | Secret | Valeur |
   |---|---|
   | `PROD_HELLOASSO_CLIENT_ID` | identifiant client |
   | `PROD_HELLOASSO_CLIENT_SECRET` | secret client |
   | `PROD_HELLOASSO_ORG_SLUG` | slug de l'association |
   | `PROD_HELLOASSO_SANDBOX` | `1` pour tester avec l'environnement de test, vide en production |

   Le déploiement suivant écrit ces valeurs dans `inc/config-local.php` de la
   production (voir `DEPLOIEMENT-CI.md`).
5. **Notifications** : HelloAsso > Mon compte > Intégrations et API >
   Notifications : URL `https://aeroclub-saumur.fr/webhook/helloasso.php`.

## Tester sans argent réel (recommandé)
HelloAsso fournit un environnement de test (`helloasso-sandbox.com`, compte et
clés distincts). Mettre les clés sandbox dans les secrets avec
`PROD_HELLOASSO_SANDBOX=1` : le site affiche « Mode test » sur les pages de
paiement. Faire un paiement de bout en bout, vérifier que la cotisation passe
« payée » et que le bon est envoyé, puis remplacer par les clés de production
et vider `PROD_HELLOASSO_SANDBOX`.

## Si le paiement ne se valide pas
- Le client revient sur le site mais voit « Paiement en cours de traitement » :
  la notification peut arriver quelques instants après ; recharger la page.
- Rien n'est validé tant que HelloAsso n'a pas confirmé : regarder dans
  HelloAsso si le paiement existe, et dans `paiements_helloasso` si la ligne
  est passée à `paye`.
- Un paiement dont le montant ne correspond plus au dossier (dossier modifié
  entre-temps) n'est **jamais** appliqué automatiquement : il est noté dans le
  journal d'erreurs, à traiter à la main.

## Remboursements
Ils se font depuis l'espace HelloAsso. Le site (fiche d'un bon) permet de les
**noter** ; il ne les déclenche pas.

## Ancien système (Stripe)
Le code Stripe est supprimé. Les colonnes `stripe_payment_intent_id` sont
conservées pour l'historique des paiements passés ; les bons payés par Stripe
restent lisibles (« Payé par Stripe »).
