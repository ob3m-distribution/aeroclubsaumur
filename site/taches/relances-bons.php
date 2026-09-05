<?php
declare(strict_types=1);

/* ==================================================================
   Tâche planifiée : relances d'expiration des bons cadeaux.

   À appeler UNE FOIS PAR JOUR par le planificateur (cron IONOS) :
       wget -q -O - "https://saumurairclub.fr/taches/relances-bons.php?jeton=LE_JETON"

   Protégée par un jeton secret (paramètre `cron_jeton`, créé au 1er
   appel puis affiché une seule fois). Aucune session, aucun cookie.
   ================================================================== */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/bon-cadeau.php';

header('Content-Type: text/plain; charset=utf-8');

$jetonVrai = parametre('cron_jeton');
if ($jetonVrai === null || $jetonVrai === '') {
    // Premier appel : on génère le jeton et on l'affiche une seule fois.
    $jetonVrai = bin2hex(random_bytes(20));
    definir_parametre('cron_jeton', $jetonVrai);
    http_response_code(403);
    exit("Jeton créé. Configurez le cron avec ?jeton=$jetonVrai puis relancez.\n");
}

$jetonRecu = (string) ($_GET['jeton'] ?? '');
if (!hash_equals($jetonVrai, $jetonRecu)) {
    http_response_code(403);
    exit("Accès refusé.\n");
}

$n = envoyer_relances_bons();
journaliser('cron.relances_bons', 'bons', $n . ' email(s)');
echo "OK — $n relance(s) envoyée(s).\n";
