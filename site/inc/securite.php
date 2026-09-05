<?php
declare(strict_types=1);

/* ==================================================================
   En-têtes de sécurité HTTP — émis côté PHP.

   Sur l'hébergement IONOS, les directives « Header » de .htaccess
   (mod_headers) s'appliquent aux fichiers STATIQUES mais PAS aux
   réponses PHP (servies par une couche proxy distincte). Les pages
   HTML échappaient donc à X-Frame-Options, CSP, HSTS… On les pose
   ici, à chaque réponse PHP, via header(). Le .htaccess reste en
   place pour couvrir les fichiers statiques (double couche).

   Inclus tout en haut de config.php, donc avant toute sortie.
   ================================================================== */

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');

    // Liste blanche stricte : seuls Stripe (paiement) et Holfuy (widget météo
    // en iframe sur /aerodrome) sont chargés depuis l'extérieur. 'unsafe-inline'
    // reste nécessaire tant que le site PHP utilise styles/scripts en ligne.
    header(
        "Content-Security-Policy: "
        . "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://js.stripe.com; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; "
        . "font-src 'self'; "
        . "connect-src 'self' https://api.stripe.com; "
        . "frame-src 'self' https://js.stripe.com https://hooks.stripe.com https://widget.holfuy.com; "
        . "frame-ancestors 'self'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "object-src 'none'; "
        . "upgrade-insecure-requests"
    );

    // Ne pas divulguer la version de PHP.
    header_remove('X-Powered-By');

    // HSTS : uniquement en HTTPS (2 ans, sous-domaines inclus, sans preload).
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=63072000; includeSubDomains');
    }
}
