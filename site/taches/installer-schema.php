<?php
declare(strict_types=1);

/* ==================================================================
   Installation ponctuelle du schema SQL sur une base neuve (prod).
   TEMPORAIRE : ce fichier doit etre supprime juste apres usage, il ne
   doit jamais rester en ligne durablement (execute du SQL, meme
   protege par secret).

   Toutes les instructions sont en CREATE TABLE IF NOT EXISTS (ou une
   ALTER MODIFY idempotente) : sans danger a rejouer sur une base qui a
   deja ces tables (ex. le dev), mais ce fichier ne doit etre appele
   qu'une fois, sur la prod, juste apres la creation de sa base.
   ================================================================== */

require_once __DIR__ . '/../inc/db.php';

$secret = defined('INSTALLER_SECRET') ? INSTALLER_SECRET : '';
$fourni = (string) ($_GET['k'] ?? '');
if ($secret === '' || !hash_equals($secret, $fourni)) {
    http_response_code(403);
    exit("forbidden\n");
}
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(400);
    exit("https required\n");
}

header('Content-Type: text/plain; charset=utf-8');

$fichiers = [
    'schema.sql' => <<<'SQL'
CREATE TABLE IF NOT EXISTS bons_cadeaux (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference          VARCHAR(16)  NOT NULL,
  code               VARCHAR(16)  NULL,
  acheteur_prenom    VARCHAR(80)  NOT NULL,
  acheteur_nom       VARCHAR(80)  NOT NULL,
  acheteur_email     VARCHAR(180) NOT NULL,
  acheteur_telephone VARCHAR(30)  NOT NULL,
  message            TEXT         NULL,
  montant_cents      INT UNSIGNED NOT NULL,
  statut ENUM('en_attente_paiement','paye','utilise','expire','annule')
         NOT NULL DEFAULT 'en_attente_paiement',
  stripe_payment_intent_id VARCHAR(120) NULL,
  cgv_acceptees_le   DATETIME     NOT NULL,
  ip_creation        VARBINARY(16) NULL,
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

CREATE TABLE IF NOT EXISTS agenda_vols (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bon_cadeau_id     INT UNSIGNED NOT NULL,
  date_vol          DATE         NOT NULL,
  heure_vol         TIME         NULL,
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
SQL,

    'schema-bo.sql' => <<<'SQL'
CREATE TABLE IF NOT EXISTS membres (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prenom            VARCHAR(80)  NOT NULL,
  nom               VARCHAR(80)  NOT NULL,
  email             VARCHAR(180) NOT NULL,
  mot_de_passe_hash VARCHAR(255) NOT NULL,
  role ENUM('administrateur','secretariat','instructeur','lecture')
       NOT NULL DEFAULT 'lecture',
  actif             TINYINT(1)   NOT NULL DEFAULT 1,
  derniere_connexion DATETIME    NULL,
  echecs_connexion  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  bloque_jusqua     DATETIME     NULL,
  cree_le           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_email (email),
  KEY idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parametres (
  cle        VARCHAR(80) PRIMARY KEY,
  valeur     TEXT        NULL,
  modifie_le DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  modifie_par INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
SQL,

    'schema-contenus.sql' => <<<'SQL'
CREATE TABLE IF NOT EXISTS contenus (
  cle         VARCHAR(100) PRIMARY KEY,
  valeur      TEXT         NULL,
  type        ENUM('texte','texte_long','image') NOT NULL DEFAULT 'texte',
  modifie_le  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  modifie_par INT UNSIGNED NULL,
  KEY idx_modifie (modifie_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE membres
  MODIFY COLUMN role ENUM('superadmin','administrateur','secretariat','instructeur','lecture')
  NOT NULL DEFAULT 'lecture';
SQL,
];

$pdo = db();
foreach ($fichiers as $nom => $sql) {
    echo "=== $nom ===\n";
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $instruction) {
        $pdo->exec($instruction);
        echo "  OK : " . strtok($instruction, "\n") . "\n";
    }
}
echo "\nTermine.\n";
