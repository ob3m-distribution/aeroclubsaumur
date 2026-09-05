-- ==================================================================
--  Back-office — membres, rôles, paramètres
-- ==================================================================

CREATE TABLE IF NOT EXISTS membres (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prenom            VARCHAR(80)  NOT NULL,
  nom               VARCHAR(80)  NOT NULL,
  email             VARCHAR(180) NOT NULL,
  mot_de_passe_hash VARCHAR(255) NOT NULL,

  -- Le rôle porte les autorisations. Elles sont définies en PHP
  -- (inc/auth.php) : plus simple à lire qu'une table de jointure.
  role ENUM('administrateur','secretariat','instructeur','lecture')
       NOT NULL DEFAULT 'lecture',

  actif             TINYINT(1)   NOT NULL DEFAULT 1,
  derniere_connexion DATETIME    NULL,
  -- Anti-force brute : on compte les échecs et on bloque temporairement.
  echecs_connexion  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  bloque_jusqua     DATETIME     NULL,
  cree_le           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uniq_email (email),
  KEY idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Paramètres généraux : clé/valeur. Sert aussi aux contenus éditables.
CREATE TABLE IF NOT EXISTS parametres (
  cle        VARCHAR(80) PRIMARY KEY,
  valeur     TEXT        NULL,
  modifie_le DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  modifie_par INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Journal des actions : qui a fait quoi. Utile en cas de doute.
CREATE TABLE IF NOT EXISTS journal (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  membre_id  INT UNSIGNED NULL,
  action     VARCHAR(60)  NOT NULL,
  cible      VARCHAR(120) NULL,
  detail     TEXT         NULL,
  ip         VARBINARY(16) NULL,
  cree_le    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_membre (membre_id),
  KEY idx_date (cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
