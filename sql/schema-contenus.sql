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

-- Le rôle « superadmin » s'ajoute à la liste existante.
ALTER TABLE membres
  MODIFY COLUMN role ENUM('superadmin','administrateur','secretariat','instructeur','lecture')
  NOT NULL DEFAULT 'lecture';
