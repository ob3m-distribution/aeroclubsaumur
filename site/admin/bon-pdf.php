<?php
declare(strict_types=1);

/* Bon cadeau en PDF, à imprimer au club (bon réglé sur place). */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/bon-cadeau.php';
require_once __DIR__ . '/../inc/pdf-bon.php';
exiger_droit('bons.voir');

$bon = bon_par_id((int) ($_GET['id'] ?? 0));
if (!$bon || !in_array($bon['statut'], ['paye', 'utilise'], true) || !numero_du_bon($bon)) {
    $_SESSION['message_erreur'] = 'Seul un bon payé a un PDF.';
    header('Location: /admin/bons.php', true, 302);
    exit;
}

$pdf = pdf_bon_cadeau($bon);
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($pdf));
header('Content-Disposition: inline; filename="bon-cadeau-' . rawurlencode((string) numero_du_bon($bon)) . '.pdf"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
echo $pdf;
