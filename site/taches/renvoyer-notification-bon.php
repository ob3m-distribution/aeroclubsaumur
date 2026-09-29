<?php
declare(strict_types=1);

/* ==================================================================
   Renvoie au club (EMAIL_CLUB, fixé côté serveur) la notification
   « Bon cadeau PAYÉ » d'un bon, avec ses informations à jour.

   Déclenché à la main depuis GitHub Actions (workflow
   « Renvoyer la notification d'un bon payé »). Protégé par le secret
   d'ops. Le destinataire n'est jamais fourni par l'appelant : pas de
   relais ouvert.

   POST : k=<secret>, bon=<code du bon, n° WEB-… / CLUB-…, ancien code
          BON-… ou référence SAC-…>
   ================================================================== */

require_once __DIR__ . '/../inc/bon-cadeau.php';
require_once __DIR__ . '/../inc/mail.php';

header('Content-Type: text/plain; charset=utf-8');

$secret = defined('SAUMUR_EXPORT_SECRET') ? SAUMUR_EXPORT_SECRET : '';
$fourni = (string) ($_POST['k'] ?? '');
if ($secret === '' || !hash_equals($secret, $fourni)) {
    http_response_code(403);
    exit("forbidden\n");
}

$cle = trim((string) ($_POST['bon'] ?? ''));
if ($cle === '' || strlen($cle) > 40) {
    http_response_code(400);
    exit("bon requis\n");
}

$st = db()->prepare('SELECT * FROM bons_cadeaux WHERE numero_bon = ? OR code = ? OR reference = ? LIMIT 2');
$st->execute([$cle, $cle, $cle]);
$bons = $st->fetchAll();
if (count($bons) !== 1) {
    http_response_code(404);
    exit(count($bons) ? "plusieurs bons correspondent\n" : "bon introuvable\n");
}
$bon = $bons[0];
if (!in_array($bon['statut'], ['paye', 'utilise'], true)) {
    http_response_code(409);
    exit('bon non payé (statut ' . $bon['statut'] . ")\n");
}

$ok = email_paiement_recu_club($bon, true);
// Pas d'error_log en cas de succès : le journal d'erreurs PHP est relevé
// chaque jour et toute ligne y déclenche une alerte.
if (!$ok) {
    error_log('Renvoi notification bon#' . $bon['id'] . ' : échec de l\'envoi');
    http_response_code(500);
    exit("mail_failed\n");
}
echo 'sent : Bon cadeau PAYÉ — ' . ($bon['numero_bon'] ?: $bon['reference'])
    . ' (valable jusqu\'au ' . date('d/m/Y', strtotime((string) ($bon['date_fin_validite'] ?: $bon['expire_le']))) . ")\n";
