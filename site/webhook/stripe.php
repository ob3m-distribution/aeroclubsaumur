<?php
declare(strict_types=1);

/* ==================================================================
   Webhook Stripe — LA source de vérité du paiement.

   Le retour du navigateur n'est jamais fiable : le client peut fermer
   son onglet, perdre le réseau, ou ne jamais revenir. Stripe, lui,
   appelle cette page et réessaie tant qu'elle ne répond pas 200.
   ================================================================== */

require_once __DIR__ . '/../inc/bon-cadeau.php';
require_once __DIR__ . '/../inc/inscription.php';
require_once __DIR__ . '/../inc/stripe.php';
require_once __DIR__ . '/../inc/mail.php';

// Aucune sortie HTML : Stripe attend un statut, rien de plus.
header('Content-Type: text/plain; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit("Méthode non autorisée\n");
}

$corps = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($corps === false || $corps === '' || $signature === '') {
    http_response_code(400);
    exit("Requête invalide\n");
}

// Sans cette vérification, n'importe qui pourrait déclarer un paiement reçu.
if (!stripe_verifier_signature($corps, $signature)) {
    error_log('Webhook Stripe : signature invalide');
    http_response_code(400);
    exit("Signature invalide\n");
}

$evenement = json_decode($corps, true);
if (!is_array($evenement) || empty($evenement['type'])) {
    http_response_code(400);
    exit("Contenu illisible\n");
}

$type   = (string) $evenement['type'];
$objet  = $evenement['data']['object'] ?? [];
$piId   = (string) ($objet['id'] ?? '');

try {
    switch ($type) {

        case 'payment_intent.succeeded':
            // Paiement d'une cotisation (inscription / réinscription) ?
            if (!empty($objet['metadata']['inscription_id'])) {
                $ins = inscription_par_intention($piId);
                if (!$ins) {
                    $s = db()->prepare('SELECT * FROM inscriptions WHERE id = ?');
                    $s->execute([(int) $objet['metadata']['inscription_id']]);
                    $ins = $s->fetch() ?: null;
                }
                if ($ins && (int) ($objet['amount_received'] ?? 0) === (int) $ins['total_cents']) {
                    inscription_marquer_paye((int) $ins['id'], 'carte');
                }
                exit("OK\n");
            }

            $bon = bon_par_intention($piId);

            // Repli : si l'association a échoué, on retrouve le bon par ses métadonnées.
            if (!$bon && !empty($objet['metadata']['bon_cadeau_id'])) {
                $bon = bon_par_id((int) $objet['metadata']['bon_cadeau_id']);
                if ($bon) {
                    bon_associer_intention((int) $bon['id'], $piId);
                }
            }

            if (!$bon) {
                error_log('Webhook Stripe : aucun bon pour ' . $piId);
                // 200 quand même : inutile que Stripe réessaie indéfiniment.
                exit("Bon introuvable, ignoré\n");
            }

            // Le montant encaissé doit correspondre : sinon on n'active rien.
            $recu = (int) ($objet['amount_received'] ?? 0);
            if ($recu !== (int) $bon['montant_cents']) {
                error_log(sprintf('Webhook Stripe : montant inattendu %d ≠ %d (bon %s)',
                    $recu, $bon['montant_cents'], $bon['reference']));
                exit("Montant incohérent, ignoré\n");
            }

            // Idempotent : Stripe peut envoyer le même événement plusieurs fois.
            [$bon, $premierPassage] = bon_marquer_paye((int) $bon['id']);

            if ($premierPassage) {
                if (@email_bon_cadeau($bon)) {
                    bon_marquer_envoye((int) $bon['id']);
                }
                @email_paiement_recu_club($bon);
            }

            exit("OK\n");

        case 'payment_intent.payment_failed':
            $bon = bon_par_intention($piId);
            $ref = $bon['reference'] ?? '?';
            $motif = $objet['last_payment_error']['message'] ?? 'motif inconnu';
            error_log("Webhook Stripe : paiement échoué pour {$ref} — {$motif}");
            // Le bon reste « en_attente_paiement » : le client peut réessayer.
            exit("OK\n");

        default:
            // Tout autre événement : on accuse réception sans rien faire.
            exit("Ignoré : {$type}\n");
    }
} catch (Throwable $e) {
    error_log('Webhook Stripe : ' . $e->getMessage());
    // 500 : Stripe réessaiera, on ne perd pas le paiement.
    http_response_code(500);
    exit("Erreur interne\n");
}
