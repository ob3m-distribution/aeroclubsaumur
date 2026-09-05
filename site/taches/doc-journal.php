<?php
declare(strict_types=1);

/* ==================================================================
   Journal des développements — alimenté AUTOMATIQUEMENT par le hook
   d'auto-commit (à chaque nouveau dev/commit). Enregistre une entrée
   horodatée (résumé + fichiers touchés). Consulté dans le B.O. →
   Documentation site.

   Protégé par le secret d'ops. POST : k=<secret>, resume=..., detail=...
   ================================================================== */

require_once __DIR__ . '/../inc/db.php';   // charge config-local (secret)

$secret = defined('SAUMUR_EXPORT_SECRET') ? SAUMUR_EXPORT_SECRET : '';
$fourni = (string) ($_POST['k'] ?? ($_SERVER['HTTP_X_EXPORT_SECRET'] ?? ''));
header('Content-Type: text/plain; charset=utf-8');
if ($secret === '' || !hash_equals($secret, $fourni)) {
    http_response_code(403);
    exit("forbidden\n");
}

$resume = trim((string) ($_POST['resume'] ?? ''));
$detail = trim((string) ($_POST['detail'] ?? ''));
if ($resume === '') { http_response_code(400); exit("resume requis\n"); }

db()->exec("CREATE TABLE IF NOT EXISTS site_doc_journal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resume VARCHAR(255) NOT NULL,
    detail MEDIUMTEXT NULL,
    source VARCHAR(40) NOT NULL DEFAULT 'auto',
    cree_le TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_date (cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

db()->prepare('INSERT INTO site_doc_journal (resume, detail, source) VALUES (?,?,?)')
    ->execute([mb_substr($resume, 0, 255), mb_substr($detail, 0, 8000), 'auto']);

echo "ok\n";
