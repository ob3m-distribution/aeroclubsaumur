<?php
declare(strict_types=1);
$page = 'tarifs-inscriptions';
$description = 'Tarifs et modalités d’adhésion au Saumur Air Club : cotisation annuelle, licence fédérale, tarifs horaires par avion.';
require __DIR__ . '/inc/header.php';

$hero = [
  'page'         => true,
  'cle_image'    => 'tarifs.hero.image',
  'image'        => '/assets/img/tarifs-hero.jpg',
  'alt'          => 'Vue en vol sous l’aile d’un avion, au-dessus d’une mer de nuages',
  'titre'        => 'Tarifs & inscriptions',
  'cle_titre'    => 'tarifs.hero.titre',
  'accroche'     => 'Rejoindre le club, se former, voler toute l’année.',
  'cle_accroche' => 'tarifs.hero.accroche',
];
require __DIR__ . '/inc/hero.php';

/* Grilles tarifaires : chaque ligne est modifiable. */
$adhesion = [
  ['tarifs.adhesion1', 'Cotisation — 25 ans et plus',        '240 €'],
  ['tarifs.adhesion2', 'Cotisation — moins de 25 ans',       '120 €'],
  ['tarifs.adhesion3', 'Licence fédérale',                   '96 €'],
  ['tarifs.adhesion4', 'Revue Info Pilote (facultatif)',     '49 €'],
];
$horaires = [
  ['tarifs.horaire1', 'Evektor SportStar G3X', '138 €/h'],
  ['tarifs.horaire2', 'Cessna 172',            '182 €/h'],
  ['tarifs.horaire3', 'DR 400-180',            '210 €/h'],
  ['tarifs.horaire4', 'Supplément instruction', '+ 40 €/h'],
];
$formations = [
  ['tarifs.formation1', 'PPL',  'Licence de pilote privé avion.'],
  ['tarifs.formation2', 'LAPL', 'Licence de pilote d’aéronef léger.'],
  ['tarifs.formation3', 'BIA',  'Brevet d’initiation aéronautique.'],
  ['tarifs.formation4', 'ULM',  'Formation ultra-léger motorisé.'],
];
?>

<section class="section">
  <div class="conteneur">
    <div class="entete-section">
      <div>
        <p class="surtitre"><?= texte('tarifs.intro.surtitre', 'Le principe') ?></p>
        <h2 class="titre-filet"><?= texte('tarifs.intro.titre', 'Voler en association') ?></h2>
      </div>
      <div>
        <p><?= texte('tarifs.intro.texte1',
          'Le Saumur Air Club est une association : on n’y achète pas des heures de vol, '
          . 'on y adhère. Une cotisation annuelle, une licence fédérale, puis un tarif '
          . 'horaire propre à chaque avion.', 'long') ?></p>
        <p>
          <?= texte('tarifs.intro.texte2',
            'Le préalable à votre inscription est un vol d’initiation : l’occasion de '
            . 'découvrir le pilotage avant de vous engager.', 'long') ?>
        </p>
      </div>
    </div>

    <div class="grille grille--2">
      <div>
        <h3><?= texte('tarifs.adhesion.titre', 'Adhésion annuelle') ?></h3>
        <div class="tableau-enveloppe">
          <table>
            <caption class="visuellement-cache">Cotisations et licences</caption>
            <thead><tr><th scope="col">Prestation</th><th scope="col">Tarif</th></tr></thead>
            <tbody>
              <?php foreach ($adhesion as [$cle, $libelle, $prix]): ?>
                <tr>
                  <td><?= texte($cle . '.libelle', $libelle) ?></td>
                  <td><?= texte($cle . '.prix', $prix) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div>
        <h3><?= texte('tarifs.horaires.titre', 'Tarifs horaires en solo') ?></h3>
        <div class="tableau-enveloppe">
          <table>
            <caption class="visuellement-cache">Tarifs horaires par avion</caption>
            <thead><tr><th scope="col">Avion</th><th scope="col">Tarif</th></tr></thead>
            <tbody>
              <?php foreach ($horaires as [$cle, $libelle, $prix]): ?>
                <tr>
                  <td><?= texte($cle . '.libelle', $libelle) ?></td>
                  <td><?= texte($cle . '.prix', $prix) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <p class="texte-petit" style="margin-top:1rem">
      <?= texte('tarifs.mention',
        'Tarifs 2026. Les tarifs horaires s’entendent en solo ; l’instruction s’ajoute au tarif de l’avion.', 'long') ?>
    </p>

  </div>
</section>

<section class="section section--gris">
  <div class="conteneur">
    <div class="entete-section">
      <div>
        <p class="surtitre"><?= texte('tarifs.formations.surtitre', 'Se former') ?></p>
        <h2 class="titre-filet"><?= texte('tarifs.formations.titre', 'Les formations du club') ?></h2>
      </div>
      <div>
        <p><?= texte('tarifs.formations.texte',
          'Le club forme au brevet de pilote et accompagne chacun à son rythme, '
          . 'encadré par ses instructeurs.', 'long') ?></p>
      </div>
    </div>

    <div class="grille grille--4">
      <?php foreach ($formations as [$cle, $titreF, $descF]): ?>
        <div class="etape">
          <h3><?= texte($cle . '.titre', $titreF) ?></h3>
          <p><?= texte($cle . '.texte', $descF, 'long') ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="conteneur">
    <div class="appel">
      <div class="appel__texte">
        <p class="surtitre"><?= texte('tarifs.appel.surtitre', 'Une question ?') ?></p>
        <h2><?= texte('tarifs.appel.titre', 'Le secrétariat vous répond') ?></h2>
        <p><?= texte('tarifs.appel.texte',
          'Le club vous accueille sur place, à l’aérodrome de Saumur Terrefort.', 'long') ?></p>
      </div>
      <a class="bouton" href="<?= e(url('contact')) ?>">Nous contacter</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
