<?php
declare(strict_types=1);
$page  = 'mentions-legales';
$titre = 'Mentions légales';
$description = 'Mentions légales du site du Saumur Air Club : éditeur, directeur de publication, hébergeur, propriété intellectuelle.';
require __DIR__ . '/inc/header.php';

$hero = [
  'page'  => true,
  'image' => '/assets/img/aerodrome-vue-aerienne.jpg',
  'alt'   => 'Vue aérienne de l’aérodrome de Saumur Terrefort',
  'titre' => 'Mentions légales',
];
require __DIR__ . '/inc/hero.php';
?>

<section class="section">
  <div class="conteneur">

    <div class="entete-section">
      <div>
        <p class="surtitre">Éditeur du site</p>
        <h2 class="titre-filet">Saumur Air Club</h2>
      </div>
      <div>
        <p>
          <strong><?= e(CLUB['nom']) ?></strong> — <?= e(CLUB['forme']) ?><br>
          <?= e(CLUB['adresse_1']) ?><br>
          Route de Marson, 49400 Saint-Hilaire-Saint-Florent, France
        </p>
        <p>
          SIRET : <?= e(CLUB['siret']) ?><br>
          Téléphone : <a href="tel:<?= e(tel_lien(CLUB['tel_mobile'])) ?>"><?= e(CLUB['tel_mobile']) ?></a><br>
          Courriel : <a href="mailto:<?= e(CLUB['email']) ?>"><?= e(CLUB['email']) ?></a>
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Responsable</p>
        <h2 class="titre-filet">Directeur de la publication</h2>
      </div>
      <div>
        <p>
          <strong><?= e(CLUB['directeur']) ?></strong><br>
          Téléphone : <a href="tel:<?= e(tel_lien(CLUB['tel_admin'])) ?>"><?= e(CLUB['tel_admin']) ?></a><br>
          Courriel : <a href="mailto:<?= e(CLUB['email_admin']) ?>"><?= e(CLUB['email_admin']) ?></a>
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Hébergement</p>
        <h2 class="titre-filet">Hébergeur du site</h2>
      </div>
      <div>
        <p>
          <strong><?= e(HEBERGEUR['nom']) ?></strong><br>
          <?= e(HEBERGEUR['adresse']) ?><br>
          Téléphone : <?= e(HEBERGEUR['tel']) ?><br>
          <a href="<?= e(HEBERGEUR['site']) ?>" target="_blank" rel="noopener"><?= e(HEBERGEUR['site']) ?></a>
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Droits</p>
        <h2 class="titre-filet">Propriété intellectuelle</h2>
      </div>
      <div>
        <p>
          L’ensemble des éléments constituant ce site — textes, images, graphismes,
          logo, icônes — est la propriété du Saumur Air Club, sauf mention contraire.
        </p>
        <p>
          Toute reproduction, représentation, modification, publication ou adaptation
          de tout ou partie de ces éléments, quel que soit le moyen ou le procédé
          utilisé, est interdite sans autorisation écrite préalable.
        </p>
        <p>
          Les logos des partenaires demeurent la propriété de leurs détenteurs respectifs
          et sont reproduits avec leur accord.
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Vos données</p>
        <h2 class="titre-filet">Données personnelles</h2>
      </div>
      <div>
        <p>
          Les informations que vous transmettez via les formulaires du site sont
          utilisées uniquement pour traiter votre demande.
          <strong>En aucun cas votre adresse électronique ne sera cédée à des tiers.</strong>
        </p>
        <p>
          Conformément au Règlement général sur la protection des données, vous disposez
          d’un droit d’accès, de rectification et de suppression des données vous concernant.
          Pour l’exercer, écrivez à
          <a href="mailto:<?= e(CLUB['email']) ?>"><?= e(CLUB['email']) ?></a>.
        </p>
        <p>
          Le détail des traitements est précisé dans notre
          <a href="/confidentialite">politique de confidentialité</a>.
        </p>
      </div>
    </div>

    <div class="a-completer">
      <p>
        <strong>À vérifier avant la mise en production.</strong>
      </p>
      <ul class="liste-check" style="margin-top:.75rem;margin-bottom:0">
        <li>
          <strong>L’hébergeur a changé.</strong> L’ancien site déclarait NETIM ;
          le nouveau est chez IONOS. Les coordonnées ci-dessus sont celles d’IONOS France —
          à confirmer sur le contrat, c’est une mention obligatoire.
        </li>
        <li>
          <strong>Le numéro SIRET semble incomplet.</strong> Celui de l’ancien site
          (<?= e(CLUB['siret']) ?>) compte 9 chiffres : c’est un SIREN. Un SIRET en
          comporte 14. Il manque probablement les 5 chiffres du NIC.
        </li>
        <li>
          <strong>Les adresses en <code>@saumur-airclub.aero</code></strong> resteront-elles
          valides après la bascule, ou faut-il les remplacer ?
        </li>
        <li>
          <strong>Numéro RNA</strong> (répertoire national des associations, format W suivi
          de 9 chiffres) : absent de l’ancien site, à ajouter si le club le connaît.
        </li>
      </ul>
    </div>

  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
