-- ==================================================================
--  Agenda — refonte
--  Un vol n'est pas forcément lié à un bon cadeau : l'équipe doit
--  pouvoir planifier tout type de vol. La table étant vide, on la
--  recrée plutôt que d'enchaîner des ALTER fragiles.
-- ==================================================================

DROP TABLE IF EXISTS agenda_vols;

CREATE TABLE agenda_vols (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- NULL possible : vol d'instruction, essai, vol privé…
  bon_cadeau_id INT UNSIGNED NULL,

  type ENUM('bon_cadeau','vol_decouverte','vol_initiation','instruction','autre')
       NOT NULL DEFAULT 'bon_cadeau',

  date_vol      DATE         NOT NULL,
  heure_debut   TIME         NOT NULL,
  duree_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,

  passager_nom       VARCHAR(120) NULL,
  passager_telephone VARCHAR(30)  NULL,
  passager_poids_kg  SMALLINT UNSIGNED NULL,

  avion         VARCHAR(40)  NULL,
  pilote        VARCHAR(80)  NULL,
  notes         TEXT         NULL,

  statut ENUM('planifie','confirme','effectue','annule','reporte')
         NOT NULL DEFAULT 'planifie',

  cree_par      INT UNSIGNED NULL,
  cree_le       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modifie_le    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  KEY idx_date (date_vol, heure_debut),
  KEY idx_bon (bon_cadeau_id),
  KEY idx_statut (statut),
  CONSTRAINT fk_agenda_bon FOREIGN KEY (bon_cadeau_id)
    REFERENCES bons_cadeaux(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
