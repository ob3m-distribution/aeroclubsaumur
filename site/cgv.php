<?php
declare(strict_types=1);
/* Mediateur cite plus bas (Atlantique Mediation Consommation) : le club doit
   avoir reellement adhere avant l'encaissement du premier paiement en ligne.
   Cyrille doit confirmer l'adhesion — cf. email envoye au club le 05/09/2026. */
$page  = 'cgv';
$titre = 'Conditions générales de vente';
$description = 'Conditions générales de vente du Saumur Air Club : commande, paiement, livraison et utilisation du bon cadeau vol découverte.';
require __DIR__ . '/inc/header.php';

$hero = [
  'page'     => true,
  'image'    => '/assets/img/aerodrome-vue-aerienne.jpg',
  'alt'      => 'Vue aérienne de l’aérodrome de Saumur Terrefort',
  'titre'    => 'Conditions générales de vente',
  'accroche' => 'Applicables à toute commande de bon cadeau passée sur ce site.',
];
require __DIR__ . '/inc/hero.php';
?>

<section class="section">
  <div class="conteneur">

    <div class="entete-section">
      <div>
        <p class="surtitre">Préambule</p>
        <h2 class="titre-filet">Qui vend le bon cadeau</h2>
      </div>
      <div>
        <p>
          Le site <?= e(site_url()) ?> est édité par le <strong><?= e(CLUB['nom']) ?></strong>,
          <?= e(CLUB['forme']) ?>, SIREN <?= e(CLUB['siren']) ?>, dont le siège est situé
          <?= e(CLUB['adresse_1']) ?>, Route de Marson, 49400 Saint-Hilaire-Saint-Florent.
          Contact : <a href="mailto:<?= e(CLUB['email']) ?>"><?= e(CLUB['email']) ?></a>,
          <a href="tel:<?= e(tel_lien(CLUB['tel_mobile'])) ?>"><?= e(CLUB['tel_mobile']) ?></a>.
        </p>
        <p>
          Les présentes conditions générales de vente s’appliquent à toute commande de bon
          cadeau passée sur ce site par un consommateur. Passer commande implique l’acceptation
          pleine et entière de ces conditions, qui prévalent sur tout autre document.
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Objet</p>
        <h2 class="titre-filet">Le bon cadeau vol découverte</h2>
      </div>
      <div>
        <p>
          Le site propose un seul produit à la vente en ligne : un bon cadeau donnant droit à
          un <strong>vol découverte de 30 minutes environ, pour un passager</strong>, à bord
          d’un avion du club, au prix de <?= e(prix(PRIX_BON_CADEAU_CENTIMES)) ?>.
        </p>
        <p>
          Le détail de la formule figure sur la page
          <a href="/vols-decouvertes">Vols découvertes &amp; initiations</a>. Les autres
          prestations présentées sur le site (vols à plusieurs passagers, vols d’initiation,
          adhésion au club) ne sont pas vendues en ligne et se règlent directement auprès
          du secrétariat.
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Commande</p>
        <h2 class="titre-filet">Commande et paiement</h2>
      </div>
      <div>
        <p>
          La commande se fait sans création de compte, en renseignant le formulaire de la
          page Vols découvertes. Le client vérifie les informations saisies avant de régler :
          il est seul responsable des erreurs de saisie, notamment sur l’adresse électronique
          de réception du bon.
        </p>
        <p>
          Le règlement s’effectue par carte bancaire, via le prestataire de paiement Stripe.
          Les coordonnées bancaires sont saisies directement chez Stripe, chiffrées, et ne
          transitent jamais par les serveurs du club. La commande n’est validée qu’après
          confirmation du paiement ; en l’absence de paiement, elle est annulée.
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Livraison</p>
        <h2 class="titre-filet">Réception du bon cadeau</h2>
      </div>
      <div>
        <p>
          Le bon cadeau n’est pas un bien matériel expédié par voie postale : il est envoyé
          par courrier électronique, au format PDF, à l’adresse renseignée lors de la
          commande, immédiatement après confirmation du paiement. Il porte une référence
          unique. C’est à l’acheteur de le transmettre au bénéficiaire de son choix.
        </p>
        <p>
          En l’absence de réception (dossier indésirable, adresse mal saisie), contacter le
          club à <a href="mailto:<?= e(CLUB['email']) ?>"><?= e(CLUB['email']) ?></a>.
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Utilisation</p>
        <h2 class="titre-filet">Validité et prise de rendez-vous</h2>
      </div>
      <div>
        <p>
          Le bon cadeau est valable <strong>un an à compter de la date d’achat</strong>. Aucune
          date de vol n’est fixée à la commande : c’est au bénéficiaire de contacter le club
          au <a href="tel:<?= e(tel_lien(CLUB['tel_mobile'])) ?>"><?= e(CLUB['tel_mobile']) ?></a>
          ou à <a href="mailto:<?= e(CLUB['email_vols']) ?>"><?= e(CLUB['email_vols']) ?></a>
          pour convenir d’un créneau, selon les disponibilités du club et les conditions
          météorologiques.
        </p>
        <p>
          Le vol dépend de la météo : s’il ne peut avoir lieu à la date convenue, le club
          prévient le bénéficiaire et propose une nouvelle date, sans frais supplémentaire.
          Si, malgré des efforts raisonnables des deux côtés, le vol n’a pas pu être organisé
          avant l’échéance du bon, le club peut, à son appréciation, en prolonger la validité.
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Sécurité</p>
        <h2 class="titre-filet">Déroulement et sécurité du vol</h2>
      </div>
      <div>
        <p>
          Le vol découverte est ouvert à tous, y compris aux enfants accompagnés, sans
          condition physique ni certificat médical particulier. La décision de voler
          appartient en dernier ressort au pilote, seul juge des conditions de sécurité :
          aucun vol n’est maintenu si elles ne sont pas réunies.
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Rétractation</p>
        <h2 class="titre-filet">Droit de rétractation</h2>
      </div>
      <div>
        <p>
          Conformément aux articles L. 221-18 et suivants du Code de la consommation,
          l’acheteur dispose d’un délai de <strong>14 jours</strong> à compter de l’achat
          pour se rétracter, sans avoir à justifier de motif, en écrivant à
          <a href="mailto:<?= e(CLUB['email']) ?>"><?= e(CLUB['email']) ?></a>. Le club
          rembourse alors la commande avec le même moyen de paiement, sans frais.
        </p>
        <p>
          Ce droit s’éteint, pour la prestation concernée, si le vol a déjà été effectué avant
          la fin de ce délai, à la demande expresse du bénéficiaire.
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Annulation</p>
        <h2 class="titre-filet">Annulation et remboursement par le club</h2>
      </div>
      <div>
        <p>
          Indépendamment du droit de rétractation, si le club se trouve dans l’impossibilité
          durable d’organiser le vol (indisponibilité prolongée, empêchement de l’appareil),
          il peut annuler le bon et rembourser l’acheteur, en totalité ou en partie selon la
          prestation déjà rendue, par le même moyen de paiement.
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Prix</p>
        <h2 class="titre-filet">Prix</h2>
      </div>
      <div>
        <p>
          Les prix sont indiqués en euros, toutes taxes comprises le cas échéant, et
          s’entendent pour l’ensemble de la prestation décrite. Le prix appliqué est celui
          affiché sur le site au moment de la commande.
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
          Les informations transmises lors de la commande servent uniquement à traiter
          l’achat, envoyer le bon et organiser le vol. Le détail est précisé dans notre
          <a href="/confidentialite">politique de confidentialité</a>.
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
          L’ensemble des éléments du site — textes, images, graphismes, logo — est la
          propriété du Saumur Air Club, sauf mention contraire. Toute reproduction sans
          autorisation écrite préalable est interdite.
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Litiges</p>
        <h2 class="titre-filet">Réclamations et médiation</h2>
      </div>
      <div>
        <p>
          Pour toute réclamation, contactez d’abord le club à
          <a href="mailto:<?= e(CLUB['email']) ?>"><?= e(CLUB['email']) ?></a> ou au
          <a href="tel:<?= e(tel_lien(CLUB['tel_mobile'])) ?>"><?= e(CLUB['tel_mobile']) ?></a>.
        </p>
        <p>
          À défaut de solution amiable dans un délai raisonnable, le consommateur peut
          recourir gratuitement au médiateur de la consommation dont dépend le club :
        </p>
        <p>
          <strong>Atlantique Médiation Consommation</strong><br>
          5, mail du Front populaire, 44200 Nantes<br>
          <a href="https://consommation.atlantique-mediation.org" target="_blank" rel="noopener">consommation.atlantique-mediation.org</a>
        </p>
        <p>
          Il peut également saisir la plateforme européenne de règlement en ligne des
          litiges : <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener">ec.europa.eu/consumers/odr</a>.
        </p>
        <p>
          Les présentes conditions sont soumises à la loi française. À défaut d’accord
          amiable, les tribunaux français sont seuls compétents.
        </p>
      </div>
    </div>

  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
