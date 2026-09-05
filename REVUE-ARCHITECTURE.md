# Revue d'architecture et critique des choix techniques

**Saumur Air Club — 19 juillet 2026**

Revue faite sur le code réel : mesure des dépendances, recherche de duplication,
examen des choix de fond. Ce document dit aussi ce que je referais autrement.

---

## Le chiffre d'abord

| | |
|---|---|
| PHP | ~4 700 lignes (noyau 2 000, pages 2 000, back-office 1 800) |
| CSS | ~1 700 lignes |
| JavaScript | ~330 lignes |
| Dépendances externes | **zéro** |
| Tables | 6 |

Pour un site vitrine avec vente en ligne et back-office complet, c'est **compact**.
À titre de comparaison, l'ancien WordPress embarquait plusieurs dizaines de milliers
de lignes et une trentaine de dépendances pour en faire moins.

---

## Ce qui est bien conçu

**La séparation noyau / pages.** Toute la logique vit dans `inc/`, les pages ne font
qu'assembler. On peut changer une page sans risquer de casser le paiement.

**Le paiement est correct sur les points qui comptent.** Le montant est calculé côté
serveur, jamais lu depuis le formulaire. Le webhook fait foi, pas le retour du navigateur.
`bon_marquer_paye()` est idempotent grâce à une transaction et un verrou de ligne : le
retour navigateur et le webhook peuvent arriver ensemble sans créer deux bons. Ce sont
précisément les trois endroits où la plupart des intégrations se plantent.

**Les autorisations sont déclaratives.** Les rôles vivent dans un tableau PHP lisible
d'un coup d'œil, pas dans une table de jointure. Impossible d'avoir un rôle orphelin,
et la barrière `exiger_droit()` en tête de page est difficile à oublier.

**Les contenus modifiables ont une valeur par défaut dans le code.** Si la base est vide
ou tombe, le site s'affiche normalement. Un champ vidé rétablit l'original. Il n'existe
aucun état où le site se retrouve blanc.

**Le mode dev/production est déduit du nom d'hôte.** Pas d'interrupteur à basculer, donc
pas d'oubli possible le jour de la mise en ligne.

---

## Ce que j'ai corrigé pendant cette revue

**Une inversion de dépendance.** `auth.php` — le module d'authentification — dépendait de
`bon-cadeau.php`, le module de vente. C'était à l'envers : l'authentification est un socle,
elle ne doit rien savoir du métier. En réalité elle ne cherchait que `session_demarrer()`
et les jetons CSRF, qui n'avaient rien à faire là.

Ces fonctions vivent maintenant dans `inc/session.php`, sans aucune dépendance.

> **Cette correction a immédiatement révélé un second défaut** : cinq pages du back-office
> utilisaient `STATUTS_BON` et `bon_par_id()` **sans jamais les inclure** — elles comptaient
> sur `auth.php` pour les tirer au passage. Le site fonctionnait par accident. Chaque
> fichier déclare désormais ce qu'il utilise.

**Une duplication dangereuse.** La logique de filtrage des bons cadeaux existait en double :
une fois dans l'affichage, une fois dans l'export CSV. Modifier l'une sans l'autre aurait
produit **un export ne correspondant pas à ce qui est à l'écran** — le genre d'écart qu'on
ne remarque qu'en comptabilité. Une seule fonction `filtre_bons()` désormais.

Les libellés de statut étaient également recopiés dans quatre fichiers. Ils sont
maintenant dans une constante unique.

---

## Critique des choix techniques

### PHP sans framework — imposé, et le bon choix

**Ce n'était pas un choix**, mais une contrainte : l'hébergement IONOS n'offre que du SFTP,
sans shell. Pas de composer, pas de Node, donc pas de Symfony ni de Laravel.

Avec le recul, c'était **le bon compromis** pour ce projet. Un framework aurait apporté
routage, injection de dépendances et migrations, mais pour 8 pages et un formulaire, le
coût d'entrée aurait dépassé le bénéfice. Et surtout : un site sans dépendance ne se met
pas à jour, ne casse pas au bout de deux ans, et n'a aucune faille à corriger en urgence.

**Ce qu'on perd, honnêtement** : pas de chargement automatique des classes, pas de moteur
de gabarits, et toute la sécurité écrite à la main. C'est tenable ici parce que le projet
est petit. **Au-delà de trois ou quatre fois cette taille, ce choix deviendrait pénible.**

### Un client Stripe écrit à la main — le point à surveiller

Sans composer, impossible d'utiliser la bibliothèque officielle. J'ai donc écrit les
deux appels nécessaires : créer une intention de paiement, vérifier la signature d'un webhook.

**Il faut le dire clairement : c'est du code de sécurité écrit à la main.** La vérification
de signature suit la documentation de Stripe — HMAC-SHA256, comparaison à temps constant,
tolérance de 300 secondes contre le rejeu — mais elle n'a pas la robustesse d'une
bibliothèque relue par des milliers de personnes.

**Atténuation** : le webhook vérifie aussi que le **montant encaissé correspond**. Même si
la signature était contournée, aucun bon ne s'activerait sans le bon montant.

**À faire au premier vrai paiement** : vérifier dans le tableau de bord Stripe que le
webhook est bien reçu et accepté.

### `mail()` plutôt qu'un vrai service d'envoi — le maillon faible

C'est **la faiblesse la plus concrète du projet**. Les hébergements mutualisés ont mauvaise
réputation auprès des messageries, et un bon cadeau qui atterrit en indésirables est un
bon cadeau perdu — avec un client qui a payé.

**Si le test réel montre des messages en spam**, la bonne réponse n'est pas de bricoler
les en-têtes mais de passer par un service d'envoi (Brevo, Mailjet ou le SMTP authentifié
d'IONOS). Le code est prêt : une seule fonction, `envoyer_email()`, à réécrire.

### Pas de tests automatisés — assumé, mais c'est une dette

Il n'existe **aucun test automatisé**. J'ai tout vérifié par des scripts HTTP jouant les
parcours réels, ce qui a trouvé de vrais bugs — mais ces scripts ne sont pas conservés
et ne se rejouent pas.

Pour un site de cette taille, c'est défendable. **Pour un tunnel de paiement, ça l'est moins.**
Si le projet devait évoluer, le premier investissement serait un jeu de tests sur le
parcours d'achat.

### Le schéma de base sans outil de migration

Les changements de schéma sont des fichiers `.sql` exécutés par une sonde temporaire.
Ça a déjà mordu une fois : un installateur qui affichait « schéma appliqué » sans avoir
créé la moindre table, **sans lever la moindre erreur**.

C'est corrigé, mais la méthode reste artisanale. Acceptable parce que le schéma est
stabilisé et que les évolutions seront rares.

### Le vrai talon d'Achille : le déploiement

**Il n'y a ni versionnement, ni déploiement atomique, ni retour arrière.**

`deploy.py` écrase les fichiers un par un. Si la connexion tombe au milieu, le site reste
dans un état mixte — moitié ancienne version, moitié nouvelle. Et il n'existe aucun bouton
pour revenir en arrière.

C'est **la limite structurelle de l'hébergement mutualisé**, pas du code. Deux parades
simples si vous voulez réduire le risque :

1. **Mettre le dossier `site/` sous Git en local.** Ça ne change rien au déploiement, mais
   ça donne un historique et la possibilité de redéployer une version antérieure.
2. **Déployer d'abord dans un dossier temporaire**, puis renommer — ce que le SFTP permet.
   Le basculement devient quasi instantané.

Aucune des deux n'est urgente aujourd'hui. Elles le deviendront si plusieurs personnes
interviennent sur le site.

---

## Décisions que je referais à l'identique

- **L'agenda partagé par lien iCal** plutôt qu'un accès au back-office pour chaque pilote.
  Les vols apparaissent dans l'agenda personnel de chacun, sans compte à gérer.
- **L'édition des contenus sur le site**, accessible seulement depuis le back-office.
  Aucun risque de modifier une page par mégarde en la consultant.
- **Le refus de faire un CMS complet.** Rendre modifiables les contenus qui changent, et
  figer la structure : c'est ce qui garantit que le site ne se déforme jamais.
- **Les polices auto-hébergées.** Un souci RGPD en moins, et c'est plus rapide.

## Une décision que je questionne

**Les rôles sont figés dans le code.** C'est lisible et sûr, mais si le club veut demain
un rôle « trésorier » qui voit les exports sans toucher aux bons, il faudra modifier
`auth.php`. Une table de rôles l'aurait permis depuis le back-office.

Je maintiens le choix — cinq rôles couvrent largement un club de cette taille, et une
matrice de permissions dans une interface serait plus source d'erreurs que de souplesse.
Mais **c'est un arbitrage, pas une évidence**.

---

## Synthèse

Le code est **propre, cohérent et sans dette technique majeure**. Les points sensibles —
paiement, autorisations, injection SQL, CSRF — sont traités correctement.

Les trois vraies faiblesses sont, par ordre d'importance :

1. **La délivrabilité des emails**, à tester en réel — c'est ce qui peut coûter un client.
2. **L'absence de tests** sur le parcours de paiement.
3. **Le déploiement sans retour arrière**, limite de l'hébergement.

Aucune ne bloque la mise en ligne. La première mérite d'être traitée dès les premiers
vrais paiements.
