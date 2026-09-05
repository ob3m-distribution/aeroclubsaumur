<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/* ==================================================================
   Choix de la police du site.

   Toutes sont sous licence SIL Open Font et hébergées sur notre
   serveur : aucune requête vers Google, donc rien à déclarer côté RGPD.
   ================================================================== */

const POLICES = [
    'inter' => [
        'nom'         => 'Inter',
        'famille'     => "'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
        'fichiers'    => ['Inter-latin.woff2', 'Inter-latin-ext.woff2'],
        'description' => 'Neutre et très lisible. Le choix sûr — mais on la voit partout.',
        'caractere'   => 'Sobre',
    ],
    'manrope' => [
        'nom'         => 'Manrope',
        'famille'     => "'Manrope', system-ui, sans-serif",
        'fichiers'    => ['Manrope-latin.woff2', 'Manrope-latin-ext.woff2'],
        'description' => 'Géométrique et contemporaine, avec des terminaisons légèrement '
                       . 'coupées qui lui donnent du caractère sans nuire à la lecture.',
        'caractere'   => 'Moderne, chaleureuse',
    ],
    'outfit' => [
        'nom'         => 'Outfit',
        'famille'     => "'Outfit', system-ui, sans-serif",
        'fichiers'    => ['Outfit-latin.woff2', 'Outfit-latin-ext.woff2'],
        'description' => 'Géométrie pure, presque architecturale. Des formes très nettes, '
                       . 'un rendu net et actuel.',
        'caractere'   => 'Épurée, graphique',
    ],
    'space-grotesk' => [
        'nom'         => 'Space Grotesk',
        'famille'     => "'SpaceGrotesk', system-ui, sans-serif",
        'fichiers'    => ['SpaceGrotesk-latin.woff2', 'SpaceGrotesk-latin-ext.woff2'],
        'description' => 'Inspirée des lettrages techniques et aérospatiaux. La plus '
                       . 'typée des quatre — un vrai parti pris pour un aéro-club.',
        'caractere'   => 'Technique, aéronautique',
    ],
    'plus-jakarta' => [
        'nom'         => 'Plus Jakarta Sans',
        'famille'     => "'PlusJakartaSans', system-ui, sans-serif",
        'fichiers'    => ['PlusJakartaSans-latin.woff2', 'PlusJakartaSans-latin-ext.woff2'],
        'description' => 'Moderne mais accueillante, avec des courbes plus douces. '
                       . 'Elle adoucit le contraste du rouge et du noir.',
        'caractere'   => 'Douce, accessible',
    ],
];

/**
 * Police active. Un paramètre ?police=xxx permet de prévisualiser
 * sans rien changer pour les visiteurs — réservé au mode édition.
 */
function police_active(): string
{
    static $choix = null;
    if ($choix !== null) {
        return $choix;
    }

    // Aperçu temporaire, uniquement pour qui peut modifier les contenus.
    $apercu = (string) ($_GET['police'] ?? '');
    if ($apercu !== '' && isset(POLICES[$apercu])) {
        require_once __DIR__ . '/contenu.php';
        if (mode_edition()) {
            return $choix = $apercu;
        }
    }

    require_once __DIR__ . '/auth.php';
    $enregistre = parametre('police', 'inter');
    $choix = isset(POLICES[$enregistre]) ? $enregistre : 'inter';
    return $choix;
}

/** Bloc <style> déclarant la police choisie. */
function police_css(): string
{
    $cle = police_active();
    $p   = POLICES[$cle];

    $plages = [
        'latin'     => 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, '
                     . 'U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+2074, U+20AC, '
                     . 'U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD',
        'latin-ext' => 'U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, '
                     . 'U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, '
                     . 'U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF',
    ];

    // Le nom de famille CSS est celui du fichier, sans le suffixe de plage.
    $famille = explode(',', $p['famille'])[0];
    $famille = trim($famille, " '\"");

    $css = '';
    foreach ($p['fichiers'] as $fichier) {
        $plage = str_contains($fichier, 'latin-ext') ? 'latin-ext' : 'latin';
        $css .= sprintf(
            "@font-face{font-family:'%s';src:url('/assets/fonts/%s') format('woff2');"
            . "font-weight:100 900;font-display:swap;unicode-range:%s}",
            $famille, $fichier, $plages[$plage]
        );
    }
    $css .= sprintf(':root{--police:%s}', $p['famille']);

    return $css;
}

/** Fichier à précharger, pour éviter le clignotement au chargement. */
function police_preload(): string
{
    $p = POLICES[police_active()];
    return '/assets/fonts/' . $p['fichiers'][0];
}
