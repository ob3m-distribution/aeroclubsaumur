# Saumur Air Club — Cadrage et plan de déploiement

**Version du 17/07/2026 — à valider**

---

## 1. Le projet en une phrase

Un site vitrine de 8 pages pour le Saumur Air Club, dont **une seule page transactionnelle** : la vente en ligne de bons cadeaux pour un vol découverte.

---

## 2. L'hébergement — vérifié, aucune surprise

| Élément | État |
|---|---|
| Hébergeur | IONOS, contrat *Hébergement Web Premium Plus* |
| Accès | **SFTP uniquement** — aucune commande ne s'exécute sur le serveur |
| PHP | 8.4.23 ✅ |
| Extensions | `pdo_mysql`, `curl`, `openssl`, `mbstring`, `gd`, `mail()` — toutes présentes ✅ |
| Base MySQL | Disponible ✅ |
| Sous-domaine de dev | `dev.aeroclub-saumur.fr` → pointage vérifié ✅ |
| Certificat SSL | ⏳ En cours de délivrance — **bloquant pour Stripe uniquement** |

**Conséquence décisive** : sans shell, Next.js est impossible. La stack est **PHP + MySQL sans framework**, avec **Stripe** pour le paiement. Les dépendances seront préparées en local et téléversées.

Chemin réel du dossier sur le serveur :
`/kunden/homepages/12/d168617917/htdocs/AEROCLUB/Aeroclub Saumur - Espace developpement`

---

## 3. L'architecture — 8 pages

| Page | Nature | Charge |
|---|---|---|
| Accueil | Contenu | Moyenne |
| Aérodrome & Club House | Contenu | Faible |
| **Vols Découvertes** | **Transactionnelle — formulaire + paiement** | **Forte** |
| Avions | Contenu | Faible |
| Partenaires | Contenu | Faible |
| Tarifs & Inscriptions | Contenu — tarifs et modalités d'adhésion au club, **aucun paiement** | Faible |
| Adhérents | Page « ESPACE EN COURS DE CONSTRUCTION » | Très faible |
| Contact | Contenu | Faible |

Sept pages sur huit sont du contenu statique. **Tout l'effort se concentre sur Vols Découvertes.**

---

## 4. Ce qui est vendu

**Un seul produit : le bon cadeau pour un vol découverte. 100 €** (tarif provisoire, à confirmer avant la mise en production).

Le visuel existe déjà (`bonkdo.png` : « BON CADEAU POUR UN VOL DÉCOUVERTE », Evektor SportStar F-HSAU).

### Le parcours retenu

1. Le visiteur achète un bon **sans choisir de date** — c'est un cadeau.
2. Il paie par carte, **sans quitter la page**.
3. Il reçoit un bon par mail, avec un **code unique**.
4. Le bénéficiaire contacte le club quand il veut.
5. **Le club le place dans l'agenda** depuis le back-office.
6. Le jour du vol, le club marque le bon comme **utilisé**.

> ⚠️ **À confirmer** : cette lecture suppose que **l'agenda est interne au club**, non visible du public. C'est ce qui évite toute la gestion des annulations météo, des reports et des remboursements. Si le visiteur doit choisir un créneau lui-même, le projet change de nature.

---

## 5. Le plan de déploiement

### Temps 1 — Cadrage (presque terminé)

| # | Tâche | Qui | État |
|---|---|---|---|
| 1 | Architecture des pages | Cyrille | ✅ |
| 3 | Produit et tarif | Le club | ✅ |
| 4 | **Confirmer que l'agenda est interne** | Cyrille | ⏳ |
| 2 | Spécifier le formulaire | Ensemble | ⏳ |
| 21 | Cycle de vie du bon cadeau | Ensemble | ⏳ |
| 5 | Périmètre du back-office | Ensemble | ⏳ |
| 10 | Domaine définitif : `aeroclub-saumur.fr` ou `saumurairclub.fr` | Le club | ⏳ |
| 11 | Mentions légales, CGV, RGPD | Le club | ⏳ |

### Temps 2 — En parallèle, dès maintenant

| # | Tâche | Pourquoi maintenant |
|---|---|---|
| 7 | **Compte Stripe** | Le délai le plus long du projet |
| 6 | Certificat SSL | Entre les mains d'IONOS |
| 9 | Vérifier les données WooCommerce / Forminator | Données de vraies personnes |
| 12 | Rassembler les contenus | Le site ne partira pas sans |
| 8 | Sauvegarder le logo et les médias | ✅ **Fait** |

### Temps 3 — Développement

| # | Étape | Dépend de |
|---|---|---|
| 13 | Socle technique : gabarit commun, base, déploiement SFTP | — |
| 14 | Les 7 pages de contenu | 13 |
| 15 | Le formulaire, **sans paiement** — jalon exploitable | 13, 2 |
| 16 | Back-office : liste des bons, statuts | 15 |
| 22 | Agenda du back-office | 16, 4 |
| 17 | Paiement Stripe intégré à la page | 15, 7, SSL |
| 18 | Emails : bon au client, notification au club | 17 |

L'ordre n'est pas arbitraire : **le formulaire fonctionne avant qu'on branche le paiement**. Stripe arrive tard car c'est la seule brique dépendant d'un tiers.

### Temps 4 — Mise en ligne

| # | Étape |
|---|---|
| 19 | Recette complète avec les cartes de test Stripe |
| 20 | Bascule du domaine, redirection de `/aerodrome`, retrait du WordPress, passage des clés Stripe en production, **changement du mot de passe SFTP** |

---

## 6. Points de vigilance

- **Le tarif de 100 € est provisoire.** À confirmer par le club avant la production.
- **Deux domaines coexistent** : le site est sur `aeroclub-saumur.fr`, les mails du club en `@saumurairclub.fr`. À trancher.
- **Le WordPress reste en ligne** pendant tout le chantier. Il ne disparaît qu'à la bascule.
- **WooCommerce et Forminator** étaient installés : vérifier qu'aucune commande ni demande réelle n'y dort.
- **Vendre en ligne crée des obligations légales** : mentions, CGV avec conditions de remboursement, RGPD. Textes à fournir par le club.
- **Délivrabilité des mails depuis IONOS** : à vérifier (SPF/DKIM), les mutualisés finissent souvent en spam.
- **Le mot de passe SFTP a circulé en clair.** À changer à la mise en production.
