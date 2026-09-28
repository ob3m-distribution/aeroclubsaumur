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
        <table class="horaires">
          <caption class="visuellement-cache">Horaires d’ouverture du club, jour par jour</caption>
          <tbody>
            <?php $aujourdhui = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'][(int) date('w')]; ?>
            <?php foreach (HORAIRES_OUVERTURE as $jour => $plage): ?>
              <tr<?= $jour === $aujourdhui ? ' class="horaires__auj"' : '' ?>>
                <th scope="row"><?= e(ucfirst($jour)) ?></th>
                <td<?= $plage === null ? ' class="horaires__ferme"' : '' ?>><?= e($plage ?? 'Fermé') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="texte-petit" style="margin-top:.6rem"><?= texte('contact.horaires.note',
          'En dehors de ces horaires, appelez-nous ou écrivez-nous.', 'long') ?></p>
      </div>
      <div>
        <h3><?= texte('contact.reseaux.titre', 'Nous suivre') ?></h3>
        <p><?= texte('contact.reseaux.intro',
          'Les sorties, les vols et la vie du club, au fil des saisons.', 'long') ?></p>
        <?php
          $icones = [
            'facebook'  => '<path d="M13.5 21v-7.5h2.6l.4-3h-3V8.6c0-.9.3-1.5 1.5-1.5h1.6V4.4c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.2H7.8v3h2.6V21h3.1Z"/>',
            'instagram' => '<path d="M12 7.3a4.7 4.7 0 1 0 0 9.4 4.7 4.7 0 0 0 0-9.4Zm0 7.7a3 3 0 1 1 0-6 3 3 0 0 1 0 6Zm6-7.9a1.1 1.1 0 1 1-2.2 0 1.1 1.1 0 0 1 2.2 0ZM21 8.1c-.1-1.5-.4-2.8-1.5-3.9S17.2 2.8 15.7 2.7C14.2 2.6 9.8 2.6 8.3 2.7 6.8 2.8 5.5 3.1 4.4 4.2S3 6.6 2.9 8.1c-.1 1.5-.1 5.9 0 7.4.1 1.5.4 2.8 1.5 3.9s2.4 1.4 3.9 1.5c1.5.1 5.9.1 7.4 0 1.5-.1 2.8-.4 3.9-1.5s1.4-2.4 1.5-3.9c.1-1.5.1-5.9-.1-7.4Zm-2 9.3a3 3 0 0 1-1.7 1.7c-1.2.5-4 .4-5.3.4s-4.1.1-5.3-.4a3 3 0 0 1-1.7-1.7c-.5-1.2-.4-4-.4-5.3s-.1-4.1.4-5.3a3 3 0 0 1 1.7-1.7c1.2-.5 4-.4 5.3-.4s4.1-.1 5.3.4a3 3 0 0 1 1.7 1.7c.5 1.2.4 4 .4 5.3s.1 4.1-.4 5.3Z"/>',
          ];
        ?>
        <ul class="reseaux">
          <?php foreach (RESEAUX_SOCIAUX as $cle => [$nomReseau, $urlReseau, $compte]): ?>
            <li>
              <a class="reseaux__lien reseaux__lien--<?= e($cle) ?>" href="<?= e($urlReseau) ?>" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true" focusable="false"><?= $icones[$cle] ?></svg>
                <span><strong><?= e($nomReseau) ?></strong><small><?= e($compte) ?></small></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
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
