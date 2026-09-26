<?php
declare(strict_types=1);
$page = 'avions';
$description = 'Découvrez la flotte du Saumur Air Club : Evektor SportStar, Cessna 172 N et Robin DR 400-180, pour vos vols découverte, votre formation et vos sorties en club.';
require __DIR__ . '/inc/header.php';

$hero = [
  'page'         => true,
  'cle_image'    => 'avions.hero.image',
  'image'        => '/assets/img/avions-hero-baron.jpg',
  'alt'          => 'Avion bimoteur du Saumur Air Club sur le tarmac, devant la tour de contrôle',
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
      <div class="panneau__media panneau__media--galerie">
        <img src="/assets/img/evektor-hangar.jpg"
             srcset="/assets/img/evektor-hangar-900.jpg 900w, /assets/img/evektor-hangar.jpg 1280w"
             sizes="(min-width:880px) 50vw, 100vw"
             alt="L’Evektor SportStar F-HLEB dans le hangar du club"
             loading="lazy" class="est-visible">
        <img src="/assets/img/evektor-cockpit-canopy.jpg"
             srcset="/assets/img/evektor-cockpit-canopy-900.jpg 900w, /assets/img/evektor-cockpit-canopy.jpg 1280w"
             sizes="(min-width:880px) 50vw, 100vw"
             alt="Le cockpit vitré de l’Evektor SportStar, avionique Garmin"
             loading="lazy">
        <img src="/assets/img/evektor-cockpit-instruments.jpg"
             srcset="/assets/img/evektor-cockpit-instruments-900.jpg 900w, /assets/img/evektor-cockpit-instruments.jpg 1600w"
             sizes="(min-width:880px) 50vw, 100vw"
             alt="Le poste de pilotage biplace de l’Evektor SportStar, deux écrans Garmin"
             loading="lazy">
        <img src="/assets/img/evektor-hangar-porte.jpg"
             srcset="/assets/img/evektor-hangar-porte-900.jpg 900w, /assets/img/evektor-hangar-porte.jpg 1280w"
             sizes="(min-width:880px) 50vw, 100vw"
             alt="L’Evektor SportStar F-HLEB à l’entrée du hangar"
             loading="lazy">
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
      <div class="panneau__media panneau__media--galerie">
        <img src="/assets/img/robin-dr400.jpg"
             srcset="/assets/img/robin-dr400-900.jpg 900w, /assets/img/robin-dr400.jpg 1200w"
             sizes="(min-width:880px) 50vw, 100vw"
             alt="Le Robin DR 400-180 F-HZAF du club sur l’aérodrome de Saumur"
             loading="lazy" class="est-visible">
        <img src="/assets/img/robin-dr400-face.jpg"
             srcset="/assets/img/robin-dr400-face-900.jpg 900w, /assets/img/robin-dr400-face.jpg 1280w"
             sizes="(min-width:880px) 50vw, 100vw"
             alt="Le Robin DR 400 F-HZAF, vue de face avec un pilote en cabine"
             loading="lazy">
        <img src="/assets/img/robin-dr400-parking.jpg"
             srcset="/assets/img/robin-dr400-parking-900.jpg 900w, /assets/img/robin-dr400-parking.jpg 1280w"
             sizes="(min-width:880px) 50vw, 100vw"
             alt="Le Robin DR 400 F-HZAF au parking avec deux passagers"
             loading="lazy">
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

<script>
// Galerie photo de l'Evektor SportStar : fondu enchaîné, une image visible à la fois.
(function () {
  document.querySelectorAll('.galerie-fondu, .panneau__media--galerie').forEach(function (g) {
    var imgs = g.querySelectorAll('img');
    if (imgs.length < 2) return;
    var i = 0;
    setInterval(function () {
      imgs[i].classList.remove('est-visible');
      i = (i + 1) % imgs.length;
      imgs[i].classList.add('est-visible');
    }, 4000);
  });
})();
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
