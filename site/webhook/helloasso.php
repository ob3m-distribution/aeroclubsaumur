<?php
declare(strict_types=1);

/* ==================================================================
   Notifications HelloAsso (webhook) : déclencheur de la vérification.

   HelloAsso appelle cette page à chaque commande et à chaque paiement.
   Son contenu n'est PAS cru : on ne s'en sert que pour savoir quelle
   inscription ou quel bon vérifier, puis on interroge l'API HelloAsso
   (ha_rapprocher). Même une requête forgée ne peut donc rien valider.

   À déclarer dans HelloAsso : Mon compte > Intégrations et API >
   Notifications, URL : https://aeroclub-saumur.fr/webhook/helloasso.php
   ================================================================== */

require_once __DIR__ . '/../inc/helloasso.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit("Méthode non autorisée\n");
}

$corps = file_get_contents('php://input');
$evenement = is_string($corps) ? json_decode($corps, true) : null;
if (!is_array($evenement)) {
    http_response_code(400);
    exit("Contenu illisible\n");
}

$genre = (string) ($evenement['eventType'] ?? '');
if (!in_array($genre, ['Order', 'Payment'], true)) {
    exit("Ignoré\n");
}

// Nos métadonnées (type + id), renvoyées telles quelles par HelloAsso.
$meta = $evenement['metadata'] ?? ($evenement['data']['metadata'] ?? []);
$type = is_array($meta) ? (string) ($meta['type'] ?? '') : '';
$id   = is_array($meta) ? (int) ($meta['id'] ?? 0) : 0;
if (!in_array($type, ['inscription', 'bon'], true) || $id <= 0) {
    error_log('Webhook HelloAsso : notification sans métadonnées exploitables (' . $genre . ').');
    exit("Ignoré\n");   // 200 : inutile de réessayer
}

try {
    ha_rapprocher($type, $id);
    exit("OK\n");
} catch (Throwable $e) {
    error_log('Webhook HelloAsso : ' . $e->getMessage());
    http_response_code(500);   // HelloAsso réessaiera
    exit("Erreur interne\n");
}
