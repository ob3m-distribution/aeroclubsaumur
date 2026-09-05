<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config-local.php';

/* ==================================================================
   Client Stripe minimal.

   Pas de bibliotheque officielle : aucun shell sur le serveur, donc
   pas de composer. Deux appels suffisent (creer une intention de
   paiement, verifier une signature de webhook), autant les ecrire.
   ================================================================== */

/** Le paiement est-il configure ? */
function stripe_actif(): bool
{
    return STRIPE_CLE_SECRETE !== '' && STRIPE_CLE_PUBLIQUE !== '';
}

/** Vrai si les cles sont des cles de test (pk_test_ / sk_test_). */
function stripe_mode_test(): bool
{
    return str_starts_with(STRIPE_CLE_PUBLIQUE, 'pk_test_');
}

/**
 * Appel HTTP a l'API Stripe.
 *
 * @param string $cleIdempotence Empeche qu'un rejeu cree deux paiements.
 * @throws RuntimeException si l'appel echoue.
 */
function stripe_appel(string $methode, string $chemin, array $donnees = [], string $cleIdempotence = ''): array
{
    if (!stripe_actif()) {
        throw new RuntimeException('Stripe n’est pas configuré.');
    }

    $url = 'https://api.stripe.com/v1/' . ltrim($chemin, '/');
    $ch  = curl_init();

    $entetes = [
        'Authorization: Bearer ' . STRIPE_CLE_SECRETE,
        'Stripe-Version: 2024-06-20',
    ];
    if ($cleIdempotence !== '') {
        $entetes[] = 'Idempotency-Key: ' . $cleIdempotence;
    }

    $options = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => $entetes,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    if (strtoupper($methode) === 'POST') {
        $options[CURLOPT_POST] = true;
        // http_build_query gere les tableaux imbriques attendus par Stripe.
        $options[CURLOPT_POSTFIELDS] = http_build_query($donnees, '', '&');
    } elseif ($donnees) {
        $options[CURLOPT_URL] = $url . '?' . http_build_query($donnees);
    }

    curl_setopt_array($ch, $options);
    $reponse = curl_exec($ch);
    $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erreur  = curl_error($ch);
    curl_close($ch);

    if ($reponse === false) {
        throw new RuntimeException('Stripe injoignable : ' . $erreur);
    }

    $json = json_decode((string) $reponse, true);
    if (!is_array($json)) {
        throw new RuntimeException('Réponse Stripe illisible.');
    }
    if ($code >= 400) {
        $msg = $json['error']['message'] ?? 'erreur inconnue';
        throw new RuntimeException('Stripe (' . $code . ') : ' . $msg);
    }

    return $json;
}

/**
 * Cree l'intention de paiement d'un bon cadeau.
 * Le montant vient TOUJOURS du serveur, jamais du formulaire.
 */
function stripe_creer_intention(array $bon): array
{
    return stripe_appel('POST', 'payment_intents', [
        'amount'   => (int) $bon['montant_cents'],   // vrai montant (1/2/3 pax, initiation…)
        'currency' => 'eur',
        'automatic_payment_methods' => ['enabled' => 'true'],
        'description' => 'Bon cadeau — ' . $bon['reference'],
        'receipt_email' => $bon['acheteur_email'],
        'metadata' => [
            'reference'     => $bon['reference'],
            'bon_cadeau_id' => (string) $bon['id'],
            'acheteur'      => $bon['acheteur_prenom'] . ' ' . $bon['acheteur_nom'],
        ],
    ], 'bon-' . $bon['reference'] . '-' . (int) $bon['montant_cents']);   // idempotence
}

/**
 * Cree l'intention de paiement d'une cotisation (inscription / réinscription).
 * Le montant vient TOUJOURS du serveur.
 */
function stripe_creer_intention_inscription(array $ins): array
{
    return stripe_appel('POST', 'payment_intents', [
        'amount'   => (int) $ins['total_cents'],
        'currency' => 'eur',
        'automatic_payment_methods' => ['enabled' => 'true'],
        'description' => 'Cotisation ' . (int) $ins['annee'] . ' — ' . $ins['nom'] . ' ' . $ins['prenom'],
        'receipt_email' => $ins['courriel'],
        'metadata' => [
            'inscription_id' => (string) $ins['id'],
            'membre'         => $ins['nom'] . ' ' . $ins['prenom'],
        ],
    ], 'inscription-' . $ins['id'] . '-' . (int) $ins['total_cents']);
}

/** Relit une intention de paiement (pour verifier son statut). */
function stripe_lire_intention(string $id): array
{
    return stripe_appel('GET', 'payment_intents/' . urlencode($id));
}

/**
 * Verifie la signature d'un webhook Stripe.
 * Sans cela, n'importe qui pourrait declarer un paiement recu.
 */
function stripe_verifier_signature(string $corpsBrut, string $enteteSignature, int $tolerance = 300): bool
{
    if (STRIPE_WEBHOOK_SECRET === '') {
        return false;
    }

    $horodatage = null;
    $signatures = [];
    foreach (explode(',', $enteteSignature) as $partie) {
        $kv = explode('=', trim($partie), 2);
        if (count($kv) !== 2) {
            continue;
        }
        if ($kv[0] === 't') {
            $horodatage = (int) $kv[1];
        } elseif ($kv[0] === 'v1') {
            $signatures[] = $kv[1];
        }
    }

    if ($horodatage === null || !$signatures) {
        return false;
    }
    // Rejoue tardif : on refuse au-dela de la tolerance.
    if (abs(time() - $horodatage) > $tolerance) {
        return false;
    }

    $attendue = hash_hmac('sha256', $horodatage . '.' . $corpsBrut, STRIPE_WEBHOOK_SECRET);
    foreach ($signatures as $s) {
        if (hash_equals($attendue, $s)) {
            return true;
        }
    }
    return false;
}
