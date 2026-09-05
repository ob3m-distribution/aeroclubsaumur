<?php
declare(strict_types=1);
$page = 'contact';
$description = 'Contacter le Saumur Air Club : aérodrome de Saumur Terrefort, route de Marson, 49400 Saumur.';
require __DIR__ . '/inc/header.php';

$hero = [
  'page'         => true,
  'cle_image'    => 'contact.hero.image',
  'image'        => '/assets/img/contact-hero.jpg',
  'alt'          => 'Le château de Saumur et la Loire au lever du jour',
  'titre'        => 'Contact',
  'cle_titre'    => 'contact.hero.titre',
  'accroche'     => 'Le club vous répond et vous accueille sur place.',
  'cle_accroche' => 'contact.hero.accroche',
];
require __DIR__ . '/inc/hero.php';
?>

<section class="section">
  <div class="conteneur">
    <div class="grille grille--3">

      <div>
        <p class="surtitre"><?= texte('contact.club.surtitre', 'Le club') ?></p>
        <h2 class="titre-filet"><?= texte('contact.club.titre', 'Nous joindre') ?></h2>
        <p>
          <a href="tel:<?= e(tel_lien(CLUB['tel_mobile'])) ?>"><?= e(CLUB['tel_mobile']) ?></a><br>
          <a href="tel:<?= e(tel_lien(CLUB['tel_fixe'])) ?>"><?= e(CLUB['tel_fixe']) ?></a>
        </p>
        <p>
          <strong><?= texte('contact.reserver.libelle', 'Réserver un vol') ?></strong><br>
          <a href="mailto:<?= e(CLUB['email_vols']) ?>"><?= e(CLUB['email_vols']) ?></a>
        </p>
        <p>
          <strong><?= texte('contact.secretariat.libelle', 'Secrétariat') ?></strong><br>
          <a href="mailto:<?= e(CLUB['email']) ?>"><?= e(CLUB['email']) ?></a>
        </p>
      </div>

      <div>
        <p class="surtitre"><?= texte('contact.adresse.surtitre', 'Sur place') ?></p>
        <h2 class="titre-filet"><?= texte('contact.adresse.titre', 'Adresse') ?></h2>
        <address style="font-style:normal;margin-bottom:1rem">
          <?= e(CLUB['adresse_1']) ?><br>
          <?= e(CLUB['adresse_2']) ?>
        </address>
        <p class="texte-petit">
          Lat : <?= e(CLUB['lat_dms']) ?><br>
          Lon : <?= e(CLUB['lon_dms']) ?>
        </p>
      </div>

      <div>
        <p class="surtitre"><?= texte('contact.aerodrome.surtitre', 'Administratif') ?></p>
        <h2 class="titre-filet"><?= texte('contact.aerodrome.titre', 'L’aérodrome') ?></h2>
        <p><?= texte('contact.aerodrome.texte',
          'Pour toute demande concernant l’utilisation de la plateforme, '
          . 'c’est la Mairie de Saumur qui est l’exploitant.', 'long') ?></p>
        <p>
          <?= texte('contact.aerodrome.personne', 'Madame Émilie Belin') ?><br>
          <a href="tel:+33241833114">02 41 83 31 14</a><br>
          <a href="mailto:aerodrome@saumur.fr">aerodrome@saumur.fr</a>
        </p>
      </div>

    </div>

    <div class="duo" style="margin-top:2.5rem">
      <div>
        <h3><?= texte('contact.horaires.titre', 'Horaires d’ouverture') ?></h3>
        <p><?= texte('contact.horaires.texte',
          'Horaires à préciser — cliquez ici pour les renseigner.', 'long') ?></p>
      </div>
      <div>
        <h3><?= texte('contact.reseaux.titre', 'Nous suivre') ?></h3>
        <p><?= texte('contact.reseaux.texte',
          'Liens vers les réseaux sociaux à ajouter.', 'long') ?></p>
      </div>
    </div>
  </div>
</section>

<section class="section section--gris">
  <div class="conteneur">
    <div class="appel">
      <div class="appel__texte">
        <p class="surtitre"><?= texte('contact.carte.surtitre', 'Trouver l’aérodrome') ?></p>
        <h2><?= texte('contact.carte.titre', 'Route de Marson, 49400 Saumur') ?></h2>
        <p><?= texte('contact.carte.texte',
          'À 2,5 km au sud-ouest de Saumur, sur la commune de Saint-Hilaire-Saint-Florent.', 'long') ?></p>
      </div>
      <a class="bouton"
         href="https://www.openstreetmap.org/?mlat=<?= e(CLUB['lat']) ?>&mlon=<?= e(CLUB['lon']) ?>#map=15/<?= e(CLUB['lat']) ?>/<?= e(CLUB['lon']) ?>"
         target="_blank" rel="noopener">Ouvrir la carte</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
