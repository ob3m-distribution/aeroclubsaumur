# Ce qu'il reste à faire avant la mise en ligne

**Saumur Air Club — état au 18 juillet 2026**
Espace de développement : https://dev.aeroclub-saumur.fr (identifiant `sac`, mot de passe `Saumur-EnVol-2026`)

---

## En un coup d'œil

Le site est **terminé et testé**. Ce qui reste ne dépend plus du développement, mais de
trois décisions du club et de quelques vérifications.

| | Sujet | Qui | Bloquant ? |
|---|---|---|---|
| 1 | Compte Stripe | Le club | **Oui** — rien ne s'encaisse sans lui |
| 2 | CGV et confidentialité | Le club | **Oui** — obligation légale |
| 3 | Test des emails | Cyrille | **Oui** — à vérifier en réel |
| 4 | Choix du domaine | Le club | **Oui** — décide de l'adresse finale |
| 5 | Contenus manquants | Le club | Non — le site fonctionne sans |
| 6 | Vérifier l'ancien WordPress | Cyrille | Non, mais **avant suppression** |

---

## 1. Ouvrir le compte Stripe — le plus long

**C'est le seul vrai frein du projet.** Tout le code de paiement est écrit, déployé et testé :
il ne manque que les clés du compte.

À prévoir : le **SIRET de l'association** et son **RIB**. Comptez quelques jours de validation.

Une fois le compte ouvert, il me faudra :
- la clé publique (`pk_live_…`)
- la clé secrète (`sk_live_…`)
- le secret de signature du webhook (`whsec_…`), obtenu en déclarant l'adresse
  `https://VOTRE-DOMAINE/webhook/stripe.php` dans le tableau de bord Stripe,
  avec les événements `payment_intent.succeeded` et `payment_intent.payment_failed`

Je les renseigne, et le paiement s'active — **sans aucune autre modification**.
On testera d'abord avec les cartes d'essai de Stripe, puis avec un vrai paiement de 130 €
qu'on remboursera.

---

## 2. Rédiger les CGV et la politique de confidentialité

**Obligatoire avant d'encaisser le moindre euro.** Les deux pages existent déjà sur le site,
avec la liste détaillée de ce qu'elles doivent contenir. Il ne manque que les décisions du club.

**Pour les CGV**, les questions qui comptent vraiment :
- Que devient un bon cadeau **non utilisé au bout d'un an** ? Perdu, prolongeable, remboursé ?
- En cas de **mauvaise météo**, combien de reports accepte-t-on ? Et si aucune date ne convient ?
- Applique-t-on le **droit de rétractation de 14 jours** ? Selon quelles modalités ?
- **Âge minimum** et **poids maximal** du passager ?

Il faudra aussi désigner un **médiateur de la consommation** — c'est une obligation légale
pour toute vente en ligne aux particuliers.

**Pour la confidentialité** : durée de conservation des données, et à qui s'adresser pour
les faire supprimer. Bonne nouvelle, le site n'utilise **ni Google Analytics ni police
distante**, ce qui allège beaucoup le sujet.

---

## 3. Tester les emails en conditions réelles

Le site envoie deux messages à chaque commande : le bon cadeau à l'acheteur, une notification
au club. **Je n'ai aucun moyen de vérifier qu'ils arrivent.**

Les hébergements mutualisés finissent souvent en indésirables.

**À faire** : une vraie commande avec votre adresse, puis vérifier
- que le message arrive, et **dans quel dossier** ;
- que le club reçoit bien sa notification sur `voler@saumurairclub.fr`.

Si ça coince, il faudra configurer SPF et DKIM sur le domaine. À noter : l'adresse d'expédition
doit appartenir au domaine réellement hébergé, sinon les messages sont rejetés d'office.

---

## 4. Trancher le nom de domaine

Le club possède **deux domaines** :
- `aeroclub-saumur.fr` — où tourne le WordPress
- `saumurairclub.fr` — celui des adresses email du club

**Il faut choisir lequel portera le site.** Le second correspond au nom officiel
(« Saumur Air Club ») et aux emails existants ; le premier est celui que les gens
ont peut-être en favori.

Le site fonctionne avec l'un comme avec l'autre : il détecte tout seul son adresse.
Mais la décision doit être prise **avant** la bascule, car elle conditionne les mentions
légales, les liens et le référencement.

---

## 5. Contenus manquants (non bloquant)

Le site est complet et affiche des contenus réels. Il manque seulement :

- les **horaires d'ouverture** du club et du secrétariat ;
- les **noms et rôles** (président, secrétaire, instructeurs) ;
- une **photo extérieure du Cessna 172** et du **DR400**, avec leurs immatriculations ;
- la version du DR400 à confirmer : la grille tarifaire dit **180**, la photo montre un **120** ;
- les liens **Facebook, Instagram, YouTube** ;
- une photo de la **terrasse du club house**.

**Vous pourrez tout saisir vous-même** depuis le back-office, section « Contenus du site » :
209 textes et 27 photos sont modifiables en cliquant directement dessus.

Le **numéro SIRET** des mentions légales est aussi à corriger : celui repris de l'ancien site
(`302864913`) ne compte que 9 chiffres — c'est un SIREN, il en faut 14.

---

## 6. Vérifier l'ancien WordPress avant de le supprimer

Deux extensions y sont installées : **WooCommerce** et **Forminator**.

S'il existe des **commandes passées** ou des **demandes de contact reçues**, ce sont des
données de personnes réelles — à récupérer avant toute suppression.

Si c'est vide, le sujet est clos.

---

## Le jour de la bascule

À faire dans cet ordre :

1. **Renseigner les clés Stripe** en mode production et déclarer le webhook.
2. **Publier les CGV** et la politique de confidentialité.
3. **Faire pointer le domaine** vers le dossier du site (panneau IONOS).
4. **Retirer la protection par mot de passe** de l'espace : supprimer les deux premiers
   blocs du fichier `.htaccess` et le fichier `.htpasswd`.
5. **Rediriger l'ancienne page** `/aerodrome` du WordPress, qui disparaît.
6. **Supprimer le WordPress**.
7. **Changer les mots de passe** : celui du SFTP et celui de la base — ils ont circulé
   en clair pendant le développement.

> **L'indexation par Google est automatique.** Le site détecte qu'il n'est plus sur
> `dev.` et s'ouvre seul aux moteurs de recherche : `robots.txt`, plan de site et
> balises passent en mode public sans intervention. Rien à oublier de ce côté.

**Après la bascule** :
- faire un **vrai paiement de bout en bout**, puis le rembourser depuis Stripe ;
- déclarer le site dans **Google Search Console** et y soumettre le plan de site ;
- vérifier que les emails arrivent toujours depuis le nouveau domaine.

---

## Ce qui est déjà fait

Pour mémoire, et pour rassurer le club :

- **8 pages** + mentions légales, CGV et confidentialité
- **Design** validé, police Manrope, responsive
- **Formulaire de bon cadeau** testé de bout en bout, avec protection anti-robots
- **Paiement Stripe** entièrement codé — n'attend que les clés
- **Back-office** : tableau de bord, bons cadeaux, export comptable, agenda des vols,
  gestion des membres et de leurs droits
- **Agenda partageable** : chaque membre peut l'ajouter dans Google Agenda ou sur son iPhone
- **5 rôles** dont Super administrateur, réservé à Cyrille
- **Édition des contenus** directement sur le site, réservée au Super administrateur
- **Performance** : page d'accueil allégée de 793 à 443 Ko
- **Référencement** : données structurées, plan de site, balises de partage
