<?php
declare(strict_types=1);

/* ==================================================================
   Session et jeton anti-CSRF.

   Ces fonctions vivaient dans bon-cadeau.php, ce qui obligeait
   auth.php à dépendre du module « bons cadeaux » — une inversion :
   l'authentification n'a rien à voir avec la vente de bons.
   Elles sont ici, sans dépendance, utilisables par tout le monde.
   ================================================================== */

/** Démarre la session si besoin. À appeler AVANT toute sortie HTML. */
function session_demarrer(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

/** Jeton anti-CSRF, stable pour toute la session. */
function jeton_csrf(): string
{
    session_demarrer();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Comparaison à temps constant : jamais un simple ===. */
function jeton_csrf_valide(?string $recu): bool
{
    session_demarrer();
    return is_string($recu)
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $recu);
}

/** IP de l'appelant, telle que vue par le serveur. */
function ip_client(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}
