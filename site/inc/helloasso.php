<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config-local.php';
require_once __DIR__ . '/db.php';

/* ==================================================================
   Client HelloAsso (API v5) : paiement en ligne des cotisations et des
   bons cadeaux. Remplace Stripe.

   Principe : on crée un « checkout » côté serveur (le montant vient
   TOUJOURS de nos données, jamais du navigateur), on envoie le client
   sur la page de paiement HelloAsso, puis on VÉRIFIE auprès de l'API
   que la commande est bien payée. L'adresse de retour et les
   notifications ne servent que de déclencheur : ils ne prouvent rien.

   Configuration (inc/config-local.php, jamais versionné) :
     HELLOASSO_CLIENT_ID, HELLOASSO_CLIENT_SECRET : clés d'API ;
     HELLOASSO_ORG_SLUG : identifiant de l'association (URL HelloAsso) ;
     HELLOASSO_SANDBOX  : true pour l'environnement de test.
   ================================================================== */

function ha_const(string $nom, mixed $defaut = ''): mixed
{
    return defined($nom) ? constant($nom) : $defaut;
}

/** Le paiement en ligne est-il configuré ? */
function ha_actif(): bool
{
    foreach (['HELLOASSO_CLIENT_ID', 'HELLOASSO_CLIENT_SECRET', 'HELLOASSO_ORG_SLUG'] as $nom) {
        $v = (string) ha_const($nom);
        // Vide, ou valeur d'exemple du modèle de configuration non remplacée.
        if ($v === '' || str_starts_with($v, 'VOTRE_') || str_starts_with($v, 'SLUG_DE_')) return false;
    }
    return true;
}

/** Environnement de test (sandbox) ? */
function ha_mode_test(): bool
{
    return (bool) ha_const('HELLOASSO_SANDBOX', false);
}

/** Racine de l'API (HELLOASSO_URL_API n'est utilisée que par les tests). */
function ha_url_api(): string
{
    $force = (string) ha_const('HELLOASSO_URL_API');
    if ($force !== '') return rtrim($force, '/');
    return ha_mode_test() ? 'https://api.helloasso-sandbox.com' : 'https://api.helloasso.com';
}

/**
 * Appel HTTP brut. Retourne [code HTTP, JSON décodé ou null].
 * @throws RuntimeException si le service est injoignable.
 */
function ha_http(string $methode, string $url, array $entetes, ?string $corps): array
{
    $ch = curl_init();
    $options = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => $entetes,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CUSTOMREQUEST  => strtoupper($methode),
    ];
    if ($corps !== null) {
        $options[CURLOPT_POSTFIELDS] = $corps;
    }
    curl_setopt_array($ch, $options);
    $reponse = curl_exec($ch);
    $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erreur  = curl_error($ch);
    curl_close($ch);

    if ($reponse === false) {
        throw new RuntimeException('HelloAsso injoignable : ' . $erreur);
    }
    $json = json_decode((string) $reponse, true);
    return [$code, is_array($json) ? $json : null];
}

/**
 * Jeton d'accès OAuth2 (valable ~30 min), gardé en base pour ne pas en
 * redemander un à chaque appel (HelloAsso le déconseille).
 */
function ha_token(bool $renouveler = false): string
{
    if (!$renouveler) {
        try {
            $s = db()->prepare("SELECT valeur FROM parametres WHERE cle = 'helloasso.token'");
            $s->execute();
            $c = json_decode((string) ($s->fetchColumn() ?: ''), true);
            if (is_array($c) && !empty($c['t']) && (int) ($c['e'] ?? 0) > time() + 60) {
                return (string) $c['t'];
            }
        } catch (Throwable $e) {
            // Cache indisponible : on redemande un jeton.
        }
    }

    [$code, $json] = ha_http('POST', ha_url_api() . '/oauth2/token',
        ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => (string) ha_const('HELLOASSO_CLIENT_ID'),
            'client_secret' => (string) ha_const('HELLOASSO_CLIENT_SECRET'),
        ], '', '&'));
    if ($code >= 400 || empty($json['access_token'])) {
        throw new RuntimeException('HelloAsso : authentification refusée (' . $code . ').');
    }
    $jeton = (string) $json['access_token'];
    try {
        $v = json_encode(['t' => $jeton, 'e' => time() + (int) ($json['expires_in'] ?? 1700)]);
        db()->prepare("REPLACE INTO parametres (cle, valeur) VALUES ('helloasso.token', ?)")->execute([$v]);
    } catch (Throwable $e) {
        // Sans cache, chaque appel redemandera un jeton : plus lent, mais correct.
    }
    return $jeton;
}

/**
 * Appel authentifié à l'API v5. $chemin commence par « / » (sans /v5).
 * @throws RuntimeException si l'appel échoue.
 */
function ha_appel(string $methode, string $chemin, ?array $json = null): array
{
    if (!ha_actif()) {
        throw new RuntimeException('HelloAsso n’est pas configuré.');
    }
    $corps = $json !== null ? json_encode($json, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null;
    for ($essai = 0; $essai < 2; $essai++) {
        $entetes = ['Authorization: Bearer ' . ha_token($essai > 0), 'Accept: application/json'];
        if ($corps !== null) $entetes[] = 'Content-Type: application/json';
        [$code, $rep] = ha_http($methode, ha_url_api() . '/v5' . $chemin, $entetes, $corps);
        if ($code === 401 && $essai === 0) continue;   // jeton expiré : on le renouvelle une fois
        if ($code >= 400 || $rep === null) {
            $msg = is_array($rep) ? (string) ($rep['message'] ?? ($rep['title'] ?? 'erreur inconnue')) : 'réponse illisible';
            throw new RuntimeException('HelloAsso (' . $code . ') : ' . $msg);
        }
        return $rep;
    }
    throw new RuntimeException('HelloAsso : authentification refusée.');
}

/**
 * Crée un checkout et l'enregistre. Retourne l'adresse de la page de paiement.
 * $type : 'inscription' ou 'bon' ; $cibleId : id de l'inscription ou du bon ;
 * $payer : ['prenom','nom','email'] (facultatif, accélère le paiement).
 */
function ha_creer_paiement(string $type, int $cibleId, int $montantCents, string $libelle, array $payer, string $urlRetour, string $urlAnnulation): string
{
    if ($montantCents <= 0) {
        throw new RuntimeException('Montant invalide.');
    }
    $p = [];
    foreach (['firstName' => 'prenom', 'lastName' => 'nom', 'email' => 'email'] as $cle => $src) {
        $v = trim((string) ($payer[$src] ?? ''));
        if ($v !== '' && ($cle !== 'email' || filter_var($v, FILTER_VALIDATE_EMAIL))) $p[$cle] = mb_substr($v, 0, 255);
    }
    $corps = [
        'totalAmount'      => $montantCents,
        'initialAmount'    => $montantCents,
        'itemName'         => mb_substr($libelle, 0, 250),
        'backUrl'          => $urlAnnulation,
        'errorUrl'         => $urlAnnulation . (str_contains($urlAnnulation, '?') ? '&' : '?') . 'erreur=1',
        'returnUrl'        => $urlRetour,
        'containsDonation' => false,
        'metadata'         => ['type' => $type, 'id' => $cibleId],
    ];
    if ($p) $corps['payer'] = $p;

    $rep = ha_appel('POST', '/organizations/' . rawurlencode((string) ha_const('HELLOASSO_ORG_SLUG')) . '/checkout-intents', $corps);
    $checkoutId = (int) ($rep['id'] ?? 0);
    $url = (string) ($rep['redirectUrl'] ?? '');
    if ($checkoutId <= 0 || $url === '' || !str_starts_with($url, 'https://')) {
        throw new RuntimeException('HelloAsso : réponse inattendue à la création du paiement.');
    }
    db()->prepare('INSERT INTO paiements_helloasso (checkout_id, type, cible_id, montant_cents) VALUES (?,?,?,?)')
        ->execute([$checkoutId, $type, $cibleId, $montantCents]);
    return $url;
}

/** Relit un checkout auprès de l'API (avec sa commande si le paiement est autorisé). */
function ha_lire_checkout(int $checkoutId): array
{
    return ha_appel('GET', '/organizations/' . rawurlencode((string) ha_const('HELLOASSO_ORG_SLUG')) . '/checkout-intents/' . $checkoutId);
}

/**
 * Ce checkout est-il réellement payé pour ce montant ?
 * Il faut une commande, de bon montant, avec au moins un paiement « Authorized ».
 * Le montant de la commande exclut la contribution volontaire à HelloAsso.
 */
function ha_checkout_paye(array $checkout, int $montantCents): bool
{
    $ordre = $checkout['order'] ?? null;
    if (!is_array($ordre)) return false;
    if ((int) ($ordre['amount']['total'] ?? -1) !== $montantCents) return false;
    $slug = (string) ($ordre['organizationSlug'] ?? '');
    if ($slug !== '' && strcasecmp($slug, (string) ha_const('HELLOASSO_ORG_SLUG')) !== 0) return false;
    foreach ((array) ($ordre['payments'] ?? []) as $pay) {
        if (is_array($pay) && ($pay['state'] ?? '') === 'Authorized') return true;
    }
    return false;
}

/** Montant (centimes) actuellement dû pour une inscription ou un bon, ou null si introuvable. */
function ha_montant_attendu(string $type, int $cibleId): ?int
{
    $table = $type === 'inscription' ? 'inscriptions' : ($type === 'bon' ? 'bons_cadeaux' : '');
    if ($table === '') return null;
    $col = $type === 'inscription' ? 'total_cents' : 'montant_cents';
    $s = db()->prepare("SELECT $col FROM $table WHERE id = ?");
    $s->execute([$cibleId]);
    $v = $s->fetchColumn();
    return $v === false ? null : (int) $v;
}

/** Applique un paiement confirmé à son inscription ou à son bon (idempotent). */
function ha_appliquer_paiement(string $type, int $cibleId): void
{
    if ($type === 'inscription') {
        require_once __DIR__ . '/inscription.php';
        require_once __DIR__ . '/mail.php';
        $s = db()->prepare('SELECT * FROM inscriptions WHERE id = ?');
        $s->execute([$cibleId]);
        $ins = $s->fetch();
        if ($ins && inscription_marquer_paye($cibleId, 'carte')) {
            @email_cotisation_secretariat($ins, 'carte');
        }
        return;
    }
    if ($type === 'bon') {
        require_once __DIR__ . '/bon-cadeau.php';
        require_once __DIR__ . '/mail.php';
        [$bon, $premier] = bon_marquer_paye($cibleId, 'helloasso');
        if ($premier) {
            if (@email_bon_cadeau($bon)) {
                bon_marquer_envoye($cibleId);
            }
            @email_paiement_recu_club($bon);
        }
    }
}

/**
 * Vérifie auprès de HelloAsso les paiements en attente d'une inscription ou
 * d'un bon et applique ceux qui sont confirmés. Retourne true si la cible est
 * (ou vient d'être) réglée. Appelé au retour du client et à chaque notification.
 */
function ha_rapprocher(string $type, int $cibleId): bool
{
    if (!ha_actif()) return false;

    // Un paiement déjà confirmé dont l'application a échoué à mi-chemin : on la rejoue.
    $deja = db()->prepare("SELECT COUNT(*) FROM paiements_helloasso WHERE type = ? AND cible_id = ? AND statut = 'paye'");
    $deja->execute([$type, $cibleId]);
    if ((int) $deja->fetchColumn() > 0) {
        ha_appliquer_paiement($type, $cibleId);
        return true;
    }

    $q = db()->prepare("SELECT * FROM paiements_helloasso
                         WHERE type = ? AND cible_id = ? AND statut = 'cree'
                           AND cree_le > DATE_SUB(NOW(), INTERVAL 3 DAY)
                         ORDER BY id DESC LIMIT 5");
    $q->execute([$type, $cibleId]);
    foreach ($q->fetchAll() as $ligne) {
        try {
            $co = ha_lire_checkout((int) $ligne['checkout_id']);
        } catch (Throwable $e) {
            error_log('HelloAsso — lecture du checkout ' . $ligne['checkout_id'] . ' : ' . $e->getMessage());
            continue;
        }
        if (!ha_checkout_paye($co, (int) $ligne['montant_cents'])) continue;
        // Le dossier a pu changer depuis la création du paiement : un règlement
        // d'un autre montant n'est jamais appliqué automatiquement.
        if (ha_montant_attendu($type, $cibleId) !== (int) $ligne['montant_cents']) {
            error_log(sprintf('HelloAsso — montant payé %d ≠ montant dû pour %s#%d : non appliqué.', $ligne['montant_cents'], $type, $cibleId));
            continue;
        }

        db()->prepare("UPDATE paiements_helloasso SET statut = 'paye', order_id = ?, paye_le = NOW() WHERE id = ? AND statut = 'cree'")
            ->execute([(int) ($co['order']['id'] ?? 0) ?: null, (int) $ligne['id']]);
        ha_appliquer_paiement($type, $cibleId);
        return true;
    }
    return false;
}

/** [type, id de la cible] d'un checkout que nous avons créé, ou null. */
function ha_cible_du_checkout(int $checkoutId): ?array
{
    $s = db()->prepare('SELECT type, cible_id FROM paiements_helloasso WHERE checkout_id = ?');
    $s->execute([$checkoutId]);
    $r = $s->fetch();
    return $r ? [(string) $r['type'], (int) $r['cible_id']] : null;
}

/** Adresse absolue de notre site (pour les adresses de retour). */
function ha_url_site(string $chemin): string
{
    return 'https://' . ($_SERVER['HTTP_HOST'] ?? 'aeroclub-saumur.fr') . $chemin;
}
