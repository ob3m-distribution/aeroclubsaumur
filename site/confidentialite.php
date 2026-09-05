<?php
declare(strict_types=1);
$page  = 'confidentialite';
$titre = 'Politique de confidentialité';
$description = 'Politique de confidentialité et protection des données personnelles du Saumur Air Club.';
require __DIR__ . '/inc/header.php';

$hero = [
  'page'     => true,
  'image'    => '/assets/img/aerodrome-vue-aerienne.jpg',
  'alt'      => 'Vue aérienne de l’aérodrome de Saumur Terrefort',
  'titre'    => 'Politique de confidentialité',
  'accroche' => 'En cours de rédaction.',
];
require __DIR__ . '/inc/hero.php';
?>

<section class="section">
  <div class="conteneur">
    <div class="entete-section">
      <div>
        <p class="surtitre">Page à venir</p>
        <h2 class="titre-filet">Cette politique est en cours de rédaction</h2>
      </div>
      <div>
        <p>
          Elle sera publiée avant l’ouverture de la vente en ligne. D’ici là, le principe
          appliqué par le club est simple : les informations que vous transmettez servent
          uniquement à traiter votre demande, et
          <strong>votre adresse électronique n’est jamais cédée à des tiers</strong>.
        </p>
        <p>
          Pour toute question, écrivez à
          <a href="mailto:<?= e(CLUB['email']) ?>"><?= e(CLUB['email']) ?></a>.
        </p>
      </div>
    </div>

    <div class="a-completer">
      <p>
        <strong>Obligatoire dès lors qu’on collecte des données personnelles.</strong>
        Le formulaire de bon cadeau recueillera nom, prénom, adresse électronique et
        téléphone : cette page devra donc préciser les points suivants.
      </p>
      <ul class="liste-check" style="margin-top:.75rem;margin-bottom:0">
        <li><strong>Responsable du traitement</strong> : l’association, avec ses coordonnées.</li>
        <li><strong>Données collectées</strong> : identité, courriel, téléphone, historique des bons.</li>
        <li><strong>Finalité</strong> : traiter l’achat, envoyer le bon, organiser le vol.</li>
        <li><strong>Base légale</strong> : l’exécution du contrat de vente.</li>
        <li><strong>Durée de conservation</strong> : combien de temps le club garde-t-il ces données ? La durée de validité du bon, puis la durée légale de conservation comptable.</li>
        <li><strong>Destinataires</strong> : le club, et le prestataire de paiement. Préciser que les coordonnées bancaires ne transitent jamais par le site.</li>
        <li><strong>Vos droits</strong> : accès, rectification, suppression, opposition — et à qui écrire.</li>
        <li><strong>Cookies</strong> : lesquels, et pour quoi. Le nouveau site n’utilise ni Google Analytics ni police distante, ce qui simplifie beaucoup ce point.</li>
        <li><strong>Réclamation</strong> : possibilité de saisir la CNIL.</li>
      </ul>
    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
