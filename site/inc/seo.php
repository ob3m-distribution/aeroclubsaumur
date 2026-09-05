<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/* ==================================================================
   Référencement.

   Le point critique : l'espace de dev doit rester invisible des
   moteurs, mais le site en production doit être indexé. Plutôt qu'un
   interrupteur qu'on oublie de basculer le jour J, on déduit le mode
   du nom d'hôte : « dev. » au début = espace de développement.
   Impossible de se tromper, et rien à penser à la bascule.
   ================================================================== */

function site_en_dev(): bool
{
    $hote = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    return str_starts_with($hote, 'dev.')
        || str_starts_with($hote, 'preprod.')
        || str_contains($hote, 'localhost');
}

/** Adresse publique du site, telle qu'on y accède. */
function site_url(): string
{
    $schema = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    return $schema . '://' . ($_SERVER['HTTP_HOST'] ?? 'aeroclub-saumur.fr');
}

/**
 * URL canonique de la page courante : sans paramètres, sans doublon.
 * Évite que /vols-decouvertes et /vols-decouvertes?utm_source=x
 * soient vus comme deux pages différentes.
 */
function url_canonique(): string
{
    $chemin = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
    // On retire le .php et le slash final éventuels : une seule forme fait foi.
    $chemin = preg_replace('/\.php$/', '', (string) $chemin);
    if ($chemin !== '/' && str_ends_with($chemin, '/')) {
        $chemin = rtrim($chemin, '/');
    }
    if ($chemin === '/index' || $chemin === '') {
        $chemin = '/';
    }
    return site_url() . $chemin;
}

/**
 * Balises de partage (Open Graph + X) : sans elles, un lien partagé sur
 * Facebook, WhatsApp ou LinkedIn n'affiche ni image ni description.
 */
function balises_partage(string $titre, string $description, ?string $image = null): string
{
    $url   = url_canonique();
    $image = $image ?? '/assets/img/partage.jpg';
    $imageAbs = str_starts_with($image, 'http') ? $image : site_url() . $image;

    $b = [
        ['property', 'og:type',        'website'],
        ['property', 'og:site_name',   CLUB['nom']],
        ['property', 'og:locale',      'fr_FR'],
        ['property', 'og:title',       $titre],
        ['property', 'og:description', $description],
        ['property', 'og:url',         $url],
        ['property', 'og:image',       $imageAbs],
        ['property', 'og:image:width', '1200'],
        ['property', 'og:image:height', '630'],
        ['name',     'twitter:card',   'summary_large_image'],
        ['name',     'twitter:title',  $titre],
        ['name',     'twitter:description', $description],
        ['name',     'twitter:image',  $imageAbs],
    ];

    $html = '';
    foreach ($b as [$attr, $nom, $valeur]) {
        $html .= sprintf("<meta %s=\"%s\" content=\"%s\">\n", $attr, $nom, e((string) $valeur));
    }
    return $html;
}

/**
 * Données structurées : c'est ce qui permet à Google d'afficher
 * l'adresse, le téléphone et la carte du club directement dans les
 * résultats. Décisif pour une recherche locale (« aéroclub Saumur »).
 */
function donnees_structurees(string $page = 'index'): string
{
    $base = site_url();

    $club = [
        '@context'    => 'https://schema.org',
        '@type'       => 'SportsActivityLocation',
        '@id'         => $base . '/#club',
        'name'        => CLUB['nom'],
        'description' => 'Aéro-club de Saumur : vols découverte au-dessus du Val de Loire, '
                       . 'vols d’initiation et formation au pilotage.',
        'url'         => $base . '/',
        'logo'        => $base . '/assets/img/logo.png',
        'image'       => $base . '/assets/img/aerodrome-vue-aerienne.jpg',
        'telephone'   => '+33' . ltrim(preg_replace('/\D/', '', CLUB['tel_mobile']), '0'),
        'email'       => CLUB['email'],
        'address'     => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => 'Aérodrome de Saumur Terrefort, Route de Marson',
            'addressLocality' => 'Saumur',
            'postalCode'      => '49400',
            'addressCountry'  => 'FR',
        ],
        'geo' => [
            '@type'     => 'GeoCoordinates',
            'latitude'  => CLUB['lat'],
            'longitude' => CLUB['lon'],
        ],
        'areaServed' => ['@type' => 'Place', 'name' => 'Val de Loire, Saumur'],
        'sameAs'     => [],   // à compléter avec Facebook / Instagram quand on les aura
    ];

    $blocs = [$club];

    // Sur la page des vols, on décrit aussi le produit vendu : Google peut
    // alors afficher le prix directement dans les résultats.
    if ($page === 'vols-decouvertes') {
        $blocs[] = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => 'Bon cadeau — vol découverte au-dessus du Val de Loire',
            'description' => 'Vol découverte d’environ 30 minutes au départ de l’aérodrome '
                           . 'de Saumur Terrefort. Bon cadeau valable un an.',
            'image'       => $base . '/assets/img/bon-cadeau.jpg',
            'brand'       => ['@type' => 'Brand', 'name' => CLUB['nom']],
            'offers'      => [
                '@type'         => 'Offer',
                'url'           => $base . '/vols-decouvertes',
                'price'         => number_format(PRIX_BON_CADEAU_CENTIMES / 100, 2, '.', ''),
                'priceCurrency' => 'EUR',
                'availability'  => 'https://schema.org/InStock',
                'seller'        => ['@id' => $base . '/#club'],
            ],
        ];
    }

    $html = '';
    foreach ($blocs as $b) {
        $html .= '<script type="application/ld+json">'
               . json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
               . "</script>\n";
    }
    return $html;
}

/** Pages du site à déclarer dans le plan de site. */
function pages_publiques(): array
{
    return [
        ['/',                    '1.0', 'monthly'],
        ['/vols-decouvertes',    '0.9', 'monthly'],
        ['/aerodrome',           '0.7', 'yearly'],
        ['/avions',              '0.7', 'yearly'],
        ['/tarifs-inscriptions', '0.8', 'yearly'],
        ['/faq',                 '0.7', 'monthly'],
        ['/partenaires',         '0.5', 'yearly'],
        ['/contact',             '0.6', 'yearly'],
        ['/mentions-legales',    '0.2', 'yearly'],
        ['/cgv',                 '0.3', 'yearly'],
        ['/confidentialite',     '0.2', 'yearly'],
        // /adherents est volontairement absent : page en construction.
    ];
}
