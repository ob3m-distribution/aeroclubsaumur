<?php
declare(strict_types=1);

/* Plan de site. Ne liste que les pages publiques et indexables :
   ni /admin/, ni le tunnel d'achat, ni l'espace en construction. */

require_once __DIR__ . '/inc/seo.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

if (site_en_dev()) {
    // Un plan de site en dev n'aurait aucun sens et pourrait fuiter.
    http_response_code(404);
    exit;
}

$base = site_url();
$aujourdhui = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach (pages_publiques() as [$chemin, $priorite, $frequence]) {
    printf(
        "  <url>\n    <loc>%s</loc>\n    <lastmod>%s</lastmod>\n"
        . "    <changefreq>%s</changefreq>\n    <priority>%s</priority>\n  </url>\n",
        htmlspecialchars($base . $chemin, ENT_XML1),
        $aujourdhui,
        $frequence,
        $priorite
    );
}

echo "</urlset>\n";
