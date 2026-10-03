-- Mises à jour de documents déposées par les membres depuis leur espace
-- adhérents (licence pilote, certificat médical), avec historique.
CREATE TABLE IF NOT EXISTS membre_documents (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  membre_id INT UNSIGNED NOT NULL,
  type      VARCHAR(20)  NOT NULL,
  fichier   VARCHAR(255) NOT NULL,
  validite  DATE         NOT NULL,
  cree_le   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_membre_type (membre_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
