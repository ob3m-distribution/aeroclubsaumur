<?php
declare(strict_types=1);

/* ==================================================================
   Notification e-mail via IONOS — appelé par les scripts d'ops du VPS
   (sentinelle, sauvegarde, audit). L'e-mail part de noreply@aeroclub-
   saumur.fr (mail() IONOS), pas d'un service tiers.

   Protégé par le secret d'ops. Le destinataire est FIXÉ côté serveur
   (jamais fourni par l'appelant) pour ne pas devenir un relais ouvert.

   POST/GET : k=<secret>, sujet=..., corps=... (HTML autorisé)
   ================================================================== */

require_once __DIR__ . '/../inc/mail.php';   // charge config + config-local

const OPS_DESTINATAIRE = 'ob3m.distribution@gmail.com';

$secret = defined('SAUMUR_EXPORT_SECRET') ? SAUMUR_EXPORT_SECRET : '';
$fourni = (string) ($_POST['k'] ?? ($_GET['k'] ?? ($_SERVER['HTTP_X_EXPORT_SECRET'] ?? '')));
header('Content-Type: text/plain; charset=utf-8');
if ($secret === '' || !hash_equals($secret, $fourni)) {
    http_response_code(403);
    exit("forbidden\n");
}

$sujet = trim((string) ($_POST['sujet'] ?? $_GET['sujet'] ?? ''));
$corps = (string) ($_POST['corps'] ?? $_GET['corps'] ?? '');
if ($sujet === '' || $corps === '') {
    http_response_code(400);
    exit("sujet/corps requis\n");
}
$sujet = mb_substr($sujet, 0, 200);

$html  = email_gabarit($sujet, $corps);
$texte = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['<br>', '<br/>', '</tr>'], "\n", $corps))));

$ok = envoyer_email_html_pj(OPS_DESTINATAIRE, $sujet, $html, $texte);
echo $ok ? "sent\n" : "mail_failed\n";
