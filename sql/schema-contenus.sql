-- ==================================================================
--  Contenus modifiables depuis le site
-- ==================================================================

CREATE TABLE IF NOT EXISTS contenus (
  cle         VARCHAR(100) PRIMARY KEY,
  valeur      TEXT         NULL,
  type        ENUM('texte','texte_long','image') NOT NULL DEFAULT 'texte',
  modifie_le  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  modifie_par INT UNSIGNED NULL,
  KEY idx_modifie (modifie_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Le role complet est deja dans schema-bo.sql depuis le 23/09/2026 (une
-- install fraiche n'a donc plus besoin de cet ALTER) ; conserve, rendu
-- idempotent avec la liste complete, pour les bases qui l'ont deja
-- execute avec l'ancienne liste (sans 'adherent').
ALTER TABLE membres
  MODIFY COLUMN role ENUM('superadmin','administrateur','secretariat','instructeur','lecture','adherent')
  NOT NULL DEFAULT 'lecture';
