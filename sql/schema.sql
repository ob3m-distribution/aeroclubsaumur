-- ==================================================================
--  Saumur Air Club — schéma de la base
--  MySQL 8.0 / utf8mb4
-- ==================================================================

CREATE TABLE IF NOT EXISTS bons_cadeaux (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- Référence attribuée dès la demande, pour le suivi (SAC-2026-A7K2).
  reference          VARCHAR(16)  NOT NULL,
  -- Code du bon cadeau : rempli seulement une fois le paiement confirmé.
  code               VARCHAR(16)  NULL,

  acheteur_prenom    VARCHAR(80)  NOT NULL,
  acheteur_nom       VARCHAR(80)  NOT NULL,
  acheteur_email     VARCHAR(180) NOT NULL,
  acheteur_telephone VARCHAR(30)  NOT NULL,
  message            TEXT         NULL,

  -- En centimes. Jamais de décimal pour de l'argent.
  montant_cents      INT UNSIGNED NOT NULL,

  statut ENUM('en_attente_paiement','paye','utilise','expire','annule')
         NOT NULL DEFAULT 'en_attente_paiement',

  -- Rempli par Stripe le jour où le paiement sera branché.
  -- UNIQUE : garantit qu'un même paiement ne crée jamais deux bons.
  stripe_payment_intent_id VARCHAR(120) NULL,

  cgv_acceptees_le   DATETIME     NOT NULL,
  ip_creation        VARBINARY(16) NULL,   -- INET6_ATON, pour la limitation de débit

  cree_le            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paye_le            DATETIME     NULL,
  expire_le          DATE         NULL,
  utilise_le         DATETIME     NULL,
  pdf_envoye_le      DATETIME     NULL,

  UNIQUE KEY uniq_reference (reference),
  UNIQUE KEY uniq_code (code),
  UNIQUE KEY uniq_stripe_pi (stripe_payment_intent_id),
  KEY idx_statut (statut),
  KEY idx_email (acheteur_email),
  KEY idx_cree_le (cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Agenda interne : le club y place le bénéficiaire une fois la date convenue.
CREATE TABLE IF NOT EXISTS agenda_vols (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bon_cadeau_id     INT UNSIGNED NOT NULL,

  date_vol          DATE         NOT NULL,
  heure_vol         TIME         NULL,

  -- Le bénéficiaire n'est connu qu'ici : à l'achat, personne ne le connaît.
  passager_nom      VARCHAR(120) NULL,
  passager_poids_kg SMALLINT UNSIGNED NULL,

  avion             VARCHAR(40)  NULL,
  pilote            VARCHAR(80)  NULL,
  notes             TEXT         NULL,

  statut ENUM('planifie','effectue','annule') NOT NULL DEFAULT 'planifie',
  cree_le           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY idx_date (date_vol),
  KEY idx_bon (bon_cadeau_id),
  CONSTRAINT fk_agenda_bon FOREIGN KEY (bon_cadeau_id)
    REFERENCES bons_cadeaux(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les comptes du back-office sont dans `membres` (voir schema-bo.sql).
