# Spécification — Bon cadeau vol découverte

**Version du 17/07/2026 — à valider**
Complète [CADRAGE.md](CADRAGE.md).

---

## 1. Le formulaire (page Vols Découvertes)

Un seul produit, un seul montant, un seul écran. Pas de choix de formule, pas de date.

| Champ | Type | Obligatoire | Remarque |
|---|---|---|---|
| Prénom | texte | ✅ | |
| Nom | texte | ✅ | |
| Email | email | ✅ | **Le bon PDF y sera envoyé** — le vérifier sérieusement |
| Téléphone | tél | ✅ | Le club rappelle si besoin |
| J'accepte les CGV | case | ✅ | Horodatée et conservée en base (preuve) |
| Carte bancaire | Stripe | ✅ | Champ Stripe intégré à la page |

Bouton : **« Payer 100 € »** — le montant est affiché en clair, jamais de surprise.

**Ce qu'il n'y a pas, et pourquoi :**
- Pas de champ bénéficiaire → l'acheteur transmet le bon lui-même.
- Pas de date → c'est un cadeau, le club cale le vol plus tard.
- Pas de poids → inutile à l'achat ; le club le saisira dans l'agenda.

**Protections :** validation systématique côté serveur (jamais uniquement dans le navigateur), champ piège anti-robot, limitation du nombre de tentatives par IP, protection contre la double soumission.

---

## 2. Le cycle de vie du bon

```
  [Formulaire soumis]
          ↓
  en_attente_paiement  ──── abandon ────→  (nettoyé après 24 h)
          ↓ paiement confirmé par Stripe
        paye  ──────────── + 1 an ───────→  expire
          ↓ le club marque le vol effectué
       utilise
```

> Le passage à `paye` est déclenché **par le webhook Stripe**, jamais par le retour du navigateur. C'est le point le plus important de toute l'intégration : un client qui ferme son onglet au mauvais moment ne doit pas perdre son bon.

**Code du bon** : format `SAC-2026-A7K2`, imprononçable de travers, sans caractères ambigus (ni `0`/`O`, ni `1`/`I`). Généré uniquement après confirmation du paiement.

---

## 3. La base de données

### `bons_cadeaux`

| Colonne | Type | Remarque |
|---|---|---|
| `id` | INT AUTO_INCREMENT | |
| `code` | VARCHAR(16) UNIQUE | `NULL` tant que non payé |
| `acheteur_prenom` | VARCHAR(80) | |
| `acheteur_nom` | VARCHAR(80) | |
| `acheteur_email` | VARCHAR(180) | |
| `acheteur_telephone` | VARCHAR(30) | |
| `montant_cents` | INT | **En centimes** — jamais de décimal pour de l'argent |
| `statut` | ENUM | `en_attente_paiement` / `paye` / `utilise` / `expire` / `annule` |
| `stripe_payment_intent_id` | VARCHAR(120) UNIQUE | Garantit qu'un paiement ne compte qu'une fois |
| `cgv_acceptees_le` | DATETIME | Preuve du consentement |
| `cree_le` | DATETIME | |
| `paye_le` | DATETIME NULL | |
| `expire_le` | DATE NULL | `paye_le` + 1 an |
| `utilise_le` | DATETIME NULL | |
| `pdf_envoye_le` | DATETIME NULL | Permet de renvoyer le bon si besoin |

### `agenda_vols`

| Colonne | Type | Remarque |
|---|---|---|
| `id` | INT AUTO_INCREMENT | |
| `bon_cadeau_id` | INT | Lien vers le bon |
| `date_vol` | DATE | |
| `heure_vol` | TIME | |
| `passager_nom` | VARCHAR(120) | Le bénéficiaire, connu seulement ici |
| `passager_poids_kg` | SMALLINT NULL | Pour l'équilibrage |
| `avion` | VARCHAR(40) NULL | ex. F-HSAU |
| `pilote` | VARCHAR(80) NULL | |
| `notes` | TEXT NULL | |
| `statut` | ENUM | `planifie` / `effectue` / `annule` |

> Le bénéficiaire n'apparaît qu'ici, au moment où le club cale le vol. C'est cohérent : à l'achat, personne ne le connaît encore.

### `admins`

| Colonne | Type |
|---|---|
| `id` | INT |
| `identifiant` | VARCHAR(60) UNIQUE |
| `mot_de_passe_hash` | VARCHAR(255) |
| `cree_le` | DATETIME |

Mots de passe hachés (`password_hash`), jamais en clair.

---

## 4. Le back-office

- **Connexion** par identifiant et mot de passe.
- **Liste des bons** : code, acheteur, date, statut, montant. Filtrable par statut, recherche par code ou nom.
- **Fiche d'un bon** : le détail, le renvoi du PDF, le marquage « utilisé ».
- **Agenda** : le club place un bénéficiaire sur une date, avec avion, pilote et poids.
- **Export CSV** pour la comptabilité de l'association.

---

## 5. Les emails

| Email | Destinataire | Déclencheur | Contenu |
|---|---|---|---|
| Bon cadeau | L'acheteur | Paiement confirmé | PDF en pièce jointe, visuel `bonkdo.png` + code + validité |
| Notification | Le club | Paiement confirmé | Coordonnées de l'acheteur, code, montant |

Le PDF reprend le visuel existant, le code en grand, la date d'expiration, et les coordonnées du club pour réserver.

---

## 6. Décisions techniques

- **Montants en centimes**, jamais en décimal.
- **Le webhook fait foi**, pas le navigateur.
- **Idempotence** : le même paiement traité deux fois ne crée qu'un seul bon.
- **Clés Stripe hors du code**, dans un fichier de configuration non versionné.
- **Le montant est calculé côté serveur**, jamais lu depuis le formulaire — sinon on peut payer 1 € au lieu de 100 €.
- **Expiration** : calculée à la lecture, pas par une tâche planifiée (pas de cron fiable en mutualisé).

---

## 7. Reste en attente

- **Tarif de 100 €** à confirmer par le club.
- **Certificat SSL** en cours chez IONOS — bloquant pour Stripe.
- **Compte Stripe** à ouvrir — le délai le plus long.
- **CGV** : conditions de remboursement et d'annulation, à fournir par le club.
- **Nom, adresse et SIRET de l'association** pour les mentions légales et le PDF.
