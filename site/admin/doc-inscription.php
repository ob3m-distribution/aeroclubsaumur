<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/inscription.php';
exiger_droit('membres.gerer');

$id  = (int) ($_GET['i'] ?? 0);
$docId = (int) ($_GET['d'] ?? 0);

if ($docId > 0) {
    // Document additionnel (table inscription_documents).
    $s = db()->prepare('SELECT fichier FROM inscription_documents WHERE id = ?');
    $s->execute([$docId]);
    $rel = (string) ($s->fetchColumn() ?: '');
} else {
    $t = (string) ($_GET['t'] ?? '');
    $type = $t === 'medicale' ? 'visite_medicale_fichier' : ($t === 'photo' ? 'photo' : 'licence_fichier');
    $s = db()->prepare('SELECT ' . $type . ' AS f FROM inscriptions WHERE id = ?');
    $s->execute([$id]);
    $rel = (string) ($s->fetchColumn() ?: '');
}

// Chemin relatif attendu (membreId/…, demandes/…, photos/…). Aucun ../, aucun chemin absolu.
if ($rel === '' || strpos($rel, '..') !== false || !preg_match('#^(\d+|demandes|photos)/[\w.\-]+$#', $rel)) {
    http_response_code(404);
    exit('Document introuvable.');
}

$chemin = __DIR__ . '/../docs-inscriptions/' . $rel;
if (!is_file($chemin)) {
    http_response_code(404);
    exit('Document introuvable.');
}

$ext = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
$mime = [
    'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png', 'webp' => 'image/webp', 'heic' => 'image/heic',
][$ext] ?? 'application/octet-stream';

journaliser('inscription.doc_vu', 'inscription#' . $id, $type);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($chemin));
header('Content-Disposition: inline; filename="' . basename($chemin) . '"');
header('X-Content-Type-Options: nosniff');
readfile($chemin);
