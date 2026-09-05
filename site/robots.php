<?php
declare(strict_types=1);

/* robots.txt généré : en dev on bloque tout, en production on ouvre.
   Rien à modifier le jour de la bascule. */

require_once __DIR__ . '/inc/seo.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');

if (site_en_dev()) {
    echo "# Espace de developpement — ne doit jamais etre indexe.\n";
    echo "User-agent: *\n";
    echo "Disallow: /\n";
    exit;
}

$base = site_url();
echo "User-agent: *\n";
echo "Allow: /\n\n";
echo "# Espaces prives et techniques\n";
echo "Disallow: /admin/\n";
echo "Disallow: /inc/\n";
echo "Disallow: /webhook/\n";
echo "Disallow: /paiement\n";
echo "Disallow: /merci\n";
echo "Disallow: /adherents\n";
echo "\nSitemap: {$base}/sitemap.xml\n";
