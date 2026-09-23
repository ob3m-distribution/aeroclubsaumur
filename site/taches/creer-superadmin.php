<?php
declare(strict_types=1);

/* ==================================================================
   TEMPORAIRE : ajoute les colonnes reset_token/reset_expire manquantes
   dans membres, cree (ou met a jour) le premier compte superadmin, et
   genere un lien de reinitialisation valide 24h. A supprimer apres usage.
   ================================================================== */

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

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

$pdo = db();
$pdo->exec("ALTER TABLE membres ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL");
$pdo->exec("ALTER TABLE membres ADD COLUMN IF NOT EXISTS reset_expire DATETIME NULL");
echo "Colonnes reset_token/reset_expire : OK\n";

$pdo->exec("ALTER TABLE membres MODIFY COLUMN role "
    . "ENUM('superadmin','administrateur','secretariat','instructeur','lecture','adherent') "
    . "NOT NULL DEFAULT 'lecture'");
echo "Role 'adherent' ajoute a l'enum : OK\n";

$email = 'ob3m.distribution@gmail.com';
$s = $pdo->prepare('SELECT id FROM membres WHERE email = ?');
$s->execute([$email]);
$id = $s->fetchColumn();

if (!$id) {
    $pdo->prepare(
        'INSERT INTO membres (prenom, nom, email, mot_de_passe_hash, role, actif) VALUES (?,?,?,?,?,1)'
    )->execute(['Admin', 'Saumur Air Club', $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'superadmin']);
    $id = (int) $pdo->lastInsertId();
    echo "Compte cree, id=$id\n";
} else {
    $id = (int) $id;
    $pdo->prepare("UPDATE membres SET role='superadmin', actif=1 WHERE id=?")->execute([$id]);
    echo "Compte existant mis a jour en superadmin, id=$id\n";
}

$token = creer_token_reset($id, 24);
echo "Lien de reinitialisation (valide 24h) :\n";
echo "https://aeroclub-saumur.fr/reinitialiser-mot-de-passe?token=" . $token . "\n";
