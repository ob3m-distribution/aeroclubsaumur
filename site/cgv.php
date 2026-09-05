<?php
declare(strict_types=1);
$page  = 'cgv';
$titre = 'Conditions générales de vente';
$description = 'Conditions générales de vente du Saumur Air Club.';
require __DIR__ . '/inc/header.php';

$hero = [
  'page'     => true,
  'image'    => '/assets/img/aerodrome-vue-aerienne.jpg',
  'alt'      => 'Vue aérienne de l’aérodrome de Saumur Terrefort',
  'titre'    => 'Conditions générales de vente',
  'accroche' => 'En cours de rédaction.',
];
require __DIR__ . '/inc/hero.php';
?>

<section class="section">
  <div class="conteneur">
    <div class="entete-section">
      <div>
        <p class="surtitre">Page à venir</p>
        <h2 class="titre-filet">Ces conditions sont en cours de rédaction</h2>
      </div>
      <div>
        <p>
          Elles seront publiées avant l’ouverture de la vente en ligne des bons cadeaux.
          En attendant, le secrétariat du club répond à toute question :
          <a href="mailto:<?= e(CLUB['email']) ?>"><?= e(CLUB['email']) ?></a>.
        </p>
      </div>
    </div>

    <div class="a-completer">
      <p>
        <strong>Obligatoire avant d’encaisser le moindre paiement en ligne.</strong>
        Le client doit accepter ces conditions au moment de l’achat, et elles doivent
        être consultables à tout moment. Voici ce qu’elles devront préciser — les
        réponses appartiennent au club :
      </p>
      <ul class="liste-check" style="margin-top:.75rem;margin-bottom:0">
        <li><strong>Identité du vendeur</strong> : association, SIRET, adresse, contact.</li>
        <li><strong>Objet de la vente</strong> : le bon cadeau pour un vol découverte, 130 €, un passager, 30 minutes.</li>
        <li><strong>Validité du bon</strong> : un an à compter de l’achat. Que se passe-t-il après ? Prolongation possible ? Remboursement ?</li>
        <li><strong>Annulation météo</strong> : le vol est reporté selon les disponibilités. Combien de reports ? Que faire si aucune date ne convient ?</li>
        <li><strong>Droit de rétractation</strong> : 14 jours pour un achat en ligne. Le club l’applique-t-il, et selon quelles modalités ?</li>
        <li><strong>Conditions du vol</strong> : âge minimum, poids maximal du passager, restrictions médicales.</li>
        <li><strong>Modalités de paiement</strong> : carte bancaire, prestataire, moment du débit.</li>
        <li><strong>Réclamations et litiges</strong> : à qui s’adresser, et mention du médiateur de la consommation (obligatoire).</li>
      </ul>
    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
