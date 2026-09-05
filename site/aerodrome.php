<?php
declare(strict_types=1);
$page = 'aerodrome';
$description = 'L’aérodrome de Saumur Terrefort : piste de 1450 m, fréquence 120.605, avitaillement, club house. Informations pratiques et accès.';
require __DIR__ . '/inc/header.php';

$hero = [
  'page'         => true,
  'cle_image'    => 'aerodrome.hero.image',
  'image'        => '/assets/img/aerodrome-terrefort.jpg',
  'alt'          => 'Vue aérienne de l’aérodrome de Saumur Terrefort',
  'titre'        => 'Aérodrome & Club House',
  'cle_titre'    => 'aerodrome.hero.titre',
  'accroche'     => 'Une localisation idéale, au sud du fleuve royal.',
  'cle_accroche' => 'aerodrome.hero.accroche',
];
require __DIR__ . '/inc/hero.php';
?>

<section class="section">
  <div class="conteneur">
    <div class="entete-section">
      <div>
        <p class="surtitre"><?= texte('aerodrome.intro.surtitre', 'La plateforme') ?></p>
        <h2 class="titre-filet"><?= texte('aerodrome.intro.titre', 'À moins de 2 NM du centre-ville') ?></h2>
      </div>
      <div>
        <p><?= texte('aerodrome.intro.texte1',
          'La plateforme se situe à l’ouest de la ville de Saumur, au sud de la Loire — '
          . 'dont vous admirerez les bancs de sable et les reflets d’argent.', 'long') ?></p>
        <p><?= texte('aerodrome.intro.texte2',
          'Le Saumur Air Club bénéficie, avec les associations voisines, des infrastructures de la '
          . 'plateforme, mises à disposition et gérées par la Mairie de Saumur, exploitant de l’aérodrome.', 'long') ?></p>
      </div>
    </div>

    <ul class="faits">
      <li>
        <span class="faits__valeur"><?= texte('aerodrome.fait1.valeur', CLUB['piste']) ?></span>
        <span class="faits__libelle"><?= texte('aerodrome.fait1.libelle', 'Piste revêtue') ?></span>
      </li>
      <li>
        <span class="faits__valeur"><?= texte('aerodrome.fait2.valeur', CLUB['frequence']) ?></span>
        <span class="faits__libelle"><?= texte('aerodrome.fait2.libelle', 'Fréquence dédiée') ?></span>
      </li>
      <li>
        <span class="faits__valeur"><?= texte('aerodrome.fait3.valeur', 'A/A') ?></span>
        <span class="faits__libelle"><?= texte('aerodrome.fait3.libelle', 'Non contrôlé') ?></span>
      </li>
      <li>
        <span class="faits__valeur"><?= texte('aerodrome.fait4.valeur', 'CB / Total') ?></span>
        <span class="faits__libelle"><?= texte('aerodrome.fait4.libelle', 'Avitaillement') ?></span>
      </li>
    </ul>
  </div>
</section>

<section class="section section--gris">
  <div class="conteneur">
    <div class="duo">
      <div>
        <p class="surtitre"><?= texte('aerodrome.pilotes.surtitre', 'Pour les pilotes') ?></p>
        <h2 class="titre-filet"><?= texte('aerodrome.pilotes.titre', 'Informations terrain') ?></h2>
        <ul class="liste-check">
          <li><?= texte('aerodrome.pilotes.point1',
            'Une piste revêtue de ' . CLUB['piste'] . ', un vaste parking avions — et aussi voitures.', 'long') ?></li>
          <li><?= texte('aerodrome.pilotes.point2',
            'Une fréquence dédiée : ' . CLUB['frequence'] . '. Contact radio obligatoire en A/A '
            . '(French only) — l’aérodrome n’est pas contrôlé.', 'long') ?></li>
          <li><?= texte('aerodrome.pilotes.point3',
            'Un service d’avitaillement par carte bleue ou carte Total.', 'long') ?></li>
        </ul>

        <div class="encadre">
          <p><?= texte('aerodrome.admin.texte',
            'Demandes administratives concernant l’utilisation de l’aérodrome : '
            . 'contacter Madame Émilie Belin, Mairie de Saumur.', 'long') ?></p>
          <p>
            Tél. <a href="tel:+33241833114">02 41 83 31 14</a> ·
            <a href="mailto:aerodrome@saumur.fr">aerodrome@saumur.fr</a>
          </p>
        </div>
      </div>

      <div class="duo__media">
        <?= image('aerodrome.photo.terrain', '/assets/img/aerodrome-avion.jpg', [
              'alt' => 'Avions du club sur l’aire de stationnement de l’aérodrome de Saumur Terrefort',
              'width' => 1600, 'height' => 900, 'loading' => 'lazy',
            ]) ?>
      </div>
    </div>

    <div class="meteo">
      <div class="meteo__intro">
        <p class="surtitre">Conditions en direct</p>
        <h3><?= texte('aerodrome.meteo.titre', 'Le vent sur l’aérodrome') ?></h3>
        <p class="texte-petit"><?= texte('aerodrome.meteo.texte',
          'Deux stations mesurent le vent en temps réel sur la plateforme. '
          . 'Données fournies par Holfuy.', 'long') ?></p>
      </div>
      <div class="meteo__grille">
        <?php
        // Liste de paires [id, libellé] : des clés numériques seraient
        // castées en int par PHP, ce qui casse e() en mode strict_types.
        $stations = [
            ['1970', 'Station 1'],
            ['912',  'Station 2'],
        ];
        foreach ($stations as [$id, $libelle]): ?>
          <div class="meteo__carte">
            <div class="meteo__entete">
              <span class="meteo__icone" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 8h11a3 3 0 1 0-3-3"/>
                  <path d="M3 12h15a3 3 0 1 1-3 3"/>
                  <path d="M3 16h9"/>
                </svg>
              </span>
              <span class="meteo__nom"><?= e($libelle) ?></span>
              <span class="meteo__live"><span class="meteo__point"></span>En direct</span>
            </div>
            <div class="meteo__cadre">
              <iframe title="<?= e($libelle) ?> — vent en direct"
                      src="https://widget.holfuy.com/?station=<?= e($id) ?>&su=km/h&t=C&lang=fr&mode=detailed"
                      loading="lazy" scrolling="no"></iframe>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="conteneur">
    <div style="margin-bottom:2rem">
      <p class="surtitre"><?= texte('aerodrome.club.surtitre', 'Sur place') ?></p>
      <h2 class="titre-filet"><?= texte('aerodrome.club.titre', 'Le club house') ?></h2>
    </div>

    <ul class="liste-check">
      <li><?= texte('aerodrome.club.point1',
        'Une salle des pilotes dotée des équipements informatiques et de la documentation '
        . 'aéronautique nécessaires à la préparation des vols et aux formations.', 'long') ?></li>
      <li><?= texte('aerodrome.club.point2',
        'Une seconde salle pour les formations et les réunions.', 'long') ?></li>
      <li><?= texte('aerodrome.club.point3',
        'Un espace d’accueil et de convivialité où pilotes et visiteurs se retrouvent pour '
        . 'préparer leurs vols, se désaltérer et partager leur passion entre deux décollages.', 'long') ?></li>
    </ul>

    <div class="duo" style="margin-top:2.5rem">
      <div>
        <h3><?= texte('aerodrome.resto.titre', 'Se restaurer à deux pas') ?></h3>
        <p><?= texte('aerodrome.resto.texte',
          'Un hôtel-restaurant se trouve à quelques minutes à pied : Les Terrasses de Saumur, '
          . 'avec sa vue sur la ville et le château, et sa piscine.', 'long') ?></p>
        <p>Tél. <a href="tel:+33241672848">02 41 67 28 48</a></p>
      </div>
      <div>
        <h3><?= texte('aerodrome.venir.titre', 'Venir à l’aérodrome') ?></h3>
        <address style="font-style:normal;margin-bottom:1rem">
          <?= e(CLUB['adresse_1']) ?><br>
          <?= e(CLUB['adresse_2']) ?>
        </address>
        <p class="texte-petit">
          Lat : <?= e(CLUB['lat']) ?> (<?= e(CLUB['lat_dms']) ?>)<br>
          Lon : <?= e(CLUB['lon']) ?> (<?= e(CLUB['lon_dms']) ?>)
        </p>
        <a class="lien-fleche"
           href="https://www.openstreetmap.org/?mlat=<?= e(CLUB['lat']) ?>&mlon=<?= e(CLUB['lon']) ?>#map=15/<?= e(CLUB['lat']) ?>/<?= e(CLUB['lon']) ?>"
           target="_blank" rel="noopener">Voir sur la carte</a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
