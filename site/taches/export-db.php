<?php
declare(strict_types=1);

/* ==================================================================
   Export de sauvegarde à distance — appelé UNIQUEMENT par le cron de
   sauvegarde sur le VPS Hostinger (le port MySQL 3306 est fermé côté
   IONOS, donc pas de mysqldump distant possible).

   Triple protection : (1) HTTP Basic Auth du dev, (2) secret dédié
   comparé en temps constant, (3) HTTPS obligatoire. Ne renvoie jamais
   rien sans le bon secret.

     ?what=db       -> dump SQL complet de la base
     ?what=secrets  -> contenu de inc/config-local.php (secrets)
   ================================================================== */

require_once __DIR__ . '/../inc/db.php';   // charge aussi config-local.php

$secret = defined('SAUMUR_EXPORT_SECRET') ? SAUMUR_EXPORT_SECRET : '';
$fourni = (string) ($_GET['k'] ?? ($_SERVER['HTTP_X_EXPORT_SECRET'] ?? ''));

if ($secret === '' || !hash_equals($secret, $fourni)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("forbidden\n");
}
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(400);
    exit("https required\n");
}

$what = $_GET['what'] ?? 'db';
header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($what === 'secrets') {
    // Sauvegarde des secrets (config-local.php) — fichier quasi statique.
    readfile(__DIR__ . '/../inc/config-local.php');
    echo "\n-- END EXPORT OK\n";
    exit;
}

/* ---- Dump SQL complet ------------------------------------------- */
@set_time_limit(120);
$pdo = db();
echo "-- Saumur Air Club — export base " . date('c') . "\n";
echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_ASSOC);
    $sqlCreate = $create['Create Table'] ?? ($create['Create View'] ?? null);
    if ($sqlCreate === null) continue;
    echo "\nDROP TABLE IF EXISTS `$t`;\n" . $sqlCreate . ";\n";

    $rows = $pdo->query("SELECT * FROM `$t`");
    while ($r = $rows->fetch(PDO::FETCH_ASSOC)) {
        $cols = implode(',', array_map(fn($c) => "`$c`", array_keys($r)));
        $vals = implode(',', array_map(
            fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
            array_values($r)
        ));
        echo "INSERT INTO `$t` ($cols) VALUES ($vals);\n";
    }
}
echo "SET FOREIGN_KEY_CHECKS=1;\n";
echo "-- END EXPORT OK\n";   // marqueur d'intégrité vérifié côté VPS
