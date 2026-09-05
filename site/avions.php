<?php
declare(strict_types=1);
$page = 'avions';
$description = 'La flotte du Saumur Air Club : Evektor SportStar, Cessna 172 N et Robin DR 400-180.';
require __DIR__ . '/inc/header.php';

$hero = [
  'page'         => true,
  'cle_image'    => 'avions.hero.image',
  'image'        => '/assets/img/avions-hero.jpg',
  'alt'          => 'Les trois avions du club — F-HSAU, F-HACS et F-GCNQ — alignés devant la tour de l’aérodrome',
  'titre'        => 'Nos avions',
  'cle_titre'    => 'avions.hero.titre',
  'accroche'     => 'Trois appareils, de l’école au voyage.',
  'cle_accroche' => 'avions.hero.accroche',
];
require __DIR__ . '/inc/hero.php';
?>

<section class="section">
  <div class="conteneur">
    <div class="panneau">
      <div class="panneau__media">
        <?= image('avions.evektor.photo', '/assets/img/evektor-fhsau.jpg', [
              'alt' => 'L’Evektor SportStar RTC F-HSAU, livrée blanche à bandes verte et rouge',
              'loading' => 'lazy',
            ]) ?>
      </div>
      <div class="panneau__texte">
        <p class="surtitre"><?= texte('avions.evektor.surtitre', 'Deux appareils · 138 €/h') ?></p>
        <h2><?= texte('avions.evektor.titre', 'Evektor SportStar') ?></h2>
        <p><?= texte('avions.evektor.texte1',
          'Le club dispose de deux Evektor SportStar : des biplaces côte à côte, légers '
          . 'et modernes, d’une masse maximale au décollage de 600 kg. Leur avionique '
          . 'glass cockpit à deux écrans EFIS en fait des appareils agréables et actuels.', 'long') ?></p>
        <p><?= texte('avions.evektor.texte2',
          'Principalement destinés à l’école de pilotage et aux vols d’initiation, '
          . 'ils permettent de faire voler plusieurs élèves en parallèle.', 'long') ?></p>
      </div>
    </div>
  </div>
</section>

<section class="section section--gris">
  <div class="conteneur">
    <div class="panneau">
      <div class="panneau__texte">
        <p class="surtitre"><?= texte('avions.cessna.surtitre', '182 €/h') ?></p>
        <h2><?= texte('avions.cessna.titre', 'Cessna 172 N') ?></h2>
        <p><?= texte('avions.cessna.texte1',
          '« L’avion le plus fabriqué au monde », produit aux États-Unis depuis 1955. '
          . 'Équipé d’un moteur Lycoming de 160 ch, il vole à 220 km/h en consommant 32 l/h.', 'long') ?></p>
        <p><?= texte('avions.cessna.texte2',
          'C’est un quadriplace destiné au voyage. Stable et facile à piloter, il sert '
          . 'aussi bien aux vols découverte qu’à la formation et au vol de nuit.', 'long') ?></p>
      </div>
      <div class="panneau__media">
        <?= image('avions.cessna.photo', '/assets/img/cessna-172.jpg', [
              'alt' => 'Le Cessna 172 F-GCNQ du club devant la tour de l’aérodrome de Saumur',
              'loading' => 'lazy',
            ]) ?>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="conteneur">
    <div class="panneau">
      <div class="panneau__media">
        <?= image('avions.dr400.photo', '/assets/img/robin-dr400.jpg', [
              'alt' => 'Le Robin DR 400 F-HACS du club en vol au-dessus de la Loire',
              'loading' => 'lazy',
            ]) ?>
      </div>
      <div class="panneau__texte">
        <p class="surtitre"><?= texte('avions.dr400.surtitre', '210 €/h') ?></p>
        <h2><?= texte('avions.dr400.titre', 'Robin DR 400-180') ?></h2>
        <p><?= texte('avions.dr400.texte1',
          'Le classique des aéro-clubs français. Robuste, tolérant, avec sa verrière '
          . 'généreuse et son aile en V caractéristique — un excellent avion de voyage '
          . 'et de découverte.', 'long') ?></p>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
