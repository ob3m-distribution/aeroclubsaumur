<?php
declare(strict_types=1);
$page = 'partenaires';
$description = 'Les partenaires du Saumur Air Club : Ville de Saumur, Destination Saumur Val de Loire, Château le Prieuré, Bulles de Loire.';
require __DIR__ . '/inc/header.php';

$hero = [
  'page'         => true,
  'cle_image'    => 'partenaires.hero.image',
  'image'        => '/assets/img/partenaires-hero.jpg',
  'alt'          => 'Mer de nuages vue depuis un avion',
  'titre'        => 'Partenaires',
  'cle_titre'    => 'partenaires.hero.titre',
  'accroche'     => 'Ils nous accompagnent.',
  'cle_accroche' => 'partenaires.hero.accroche',
];
require __DIR__ . '/inc/hero.php';

$partenaires = [
  [
    'nom'  => 'Ville de Saumur',
    'cat'  => 'Institution',
    'desc' => 'L’aérodrome est géré par la Ville de Saumur, qui en est l’exploitant et met les infrastructures à disposition des associations.',
    'url'  => 'https://www.ville-saumur.fr/',
    'logo' => 'ville-de-saumur.png',
  ],
  [
    'nom'  => 'Destination Saumur Val de Loire',
    'cat'  => 'Tourisme',
    'desc' => 'L’office de tourisme du Saumurois : châteaux, vignoble et activités de la région, à découvrir avant ou après le vol.',
    'url'  => 'https://www.ot-saumur.fr/',
    'logo' => 'office-tourisme.png',
  ],
  [
    'nom'  => 'Château le Prieuré',
    'cat'  => 'Hébergement',
    'desc' => 'Cet élégant château offre une vue panoramique exceptionnelle sur la Loire.',
    'url'  => 'https://www.prieure.com/',
    'logo' => 'chateau-le-prieure.png',
  ],
  [
    'nom'  => 'Bulles de Loire',
    'cat'  => 'Hébergement',
    'desc' => 'Une maison d’hôtes raffinée installée dans un hôtel particulier du XVIIIᵉ siècle, à quelques mètres de la Loire et au cœur du quartier historique de Saumur. Cinq chambres au caractère propre, où l’ancien et le moderne se mêlent avec calme et intimité.',
    'url'  => 'https://www.bullesdeloire.com/',
    'logo' => 'bulles-de-loire.png',
  ],
];
?>

<section class="section">
  <div class="conteneur">
    <div class="entete-section">
      <div>
        <p class="surtitre"><?= texte('partenaires.surtitre', 'Le réseau du club') ?></p>
        <h2 class="titre-filet"><?= texte('partenaires.titre', 'Le club ne vole pas seul') ?></h2>
      </div>
      <div>
        <p><?= texte('partenaires.intro',
          'Institutions, acteurs du tourisme et maisons d’hôtes de la région qui soutiennent '
          . 'le Saumur Air Club et accueillent celles et ceux qui viennent voler chez nous.', 'long') ?></p>
      </div>
    </div>

    <div class="grille grille--partenaires">
      <?php foreach ($partenaires as $p): ?>
        <article class="carte">
          <?php $cp = 'partenaires.' . pathinfo($p['logo'], PATHINFO_FILENAME); ?>
          <div class="carte__logo">
            <?= image($cp . '.logo', '/assets/img/partenaires/' . $p['logo'], [
                  'alt' => $p['nom'], 'loading' => 'lazy',
                ]) ?>
          </div>
          <div class="carte__corps">
            <p class="surtitre" style="margin-bottom:.5rem"><?= texte($cp . '.cat', $p['cat']) ?></p>
            <h3><?= texte($cp . '.nom', $p['nom']) ?></h3>
            <p><?= texte($cp . '.desc', $p['desc'], 'long') ?></p>
            <a class="carte__lien" href="<?= e($p['url']) ?>" target="_blank" rel="noopener">
              Visiter le site
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
