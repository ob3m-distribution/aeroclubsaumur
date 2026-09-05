<?php
declare(strict_types=1);

/* Streamer sécurisé des documents de la bibliothèque adhérents.
   Fichiers dans /docs-adherents (accès direct interdit par .htaccess) ;
   servis uniquement à un membre connecté et autorisé sur le dossier. */

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/biblio-adherents.php';

function refuser(int $code, string $msg): never {
    http_response_code($code);
    header('Content-Type: text/plain; charset=UTF-8');
    exit($msg);
}

$membre = membre_connecte();
if (!$membre) refuser(403, 'Accès réservé aux adhérents connectés.');

$id  = (int) ($_GET['doc'] ?? 0);
$doc = $id > 0 ? biblio_doc($id) : null;
if (!$doc) refuser(404, 'Document introuvable.');

/* Contrôle d'accès par dossier. */
if (!membre_voit_tout($membre)) {
    $autorises = dossiers_effectifs($membre);
    if ($autorises !== null && !in_array((string) $doc['section_top'], $autorises, true)) {
        refuser(403, 'Accès non autorisé à ce dossier.');
    }
}

$base = realpath(__DIR__ . '/docs-adherents');
$real = realpath(__DIR__ . '/docs-adherents/' . $doc['fichier']);
if ($base === false || $real === false
    || strncmp($real, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0
    || !is_file($real)) {
    refuser(404, 'Fichier introuvable sur le serveur.');
}

$type = strtolower((string) $doc['type']);
$nomFichier = $doc['nom'] . '.' . $type;
$disp = biblio_inline($type) ? 'inline' : 'attachment';

header('Content-Type: ' . biblio_mime($type));
header('Content-Length: ' . filesize($real));
header('Content-Disposition: ' . $disp . '; filename="' . rawurlencode($nomFichier) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=600');
readfile($real);
