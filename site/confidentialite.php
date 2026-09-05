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
  'accroche' => 'Comment le Saumur Air Club collecte, utilise et protège vos données personnelles.',
];
require __DIR__ . '/inc/hero.php';
?>

<section class="section">
  <div class="conteneur">

    <div class="entete-section">
      <div>
        <p class="surtitre">Qui est responsable</p>
        <h2 class="titre-filet">Le Saumur Air Club, responsable du traitement</h2>
      </div>
      <div>
        <p>
          Le <strong><?= e(CLUB['nom']) ?></strong>, <?= e(CLUB['forme']) ?> domiciliée
          <?= e(CLUB['adresse_1']) ?>, <?= e(CLUB['adresse_2']) ?>, est responsable du
          traitement des données personnelles collectées sur ce site.
        </p>
        <p>
          Nous ne collectons que les données nécessaires aux services que vous nous
          demandez : réserver un vol, acheter un bon cadeau, adhérer au club ou emprunter
          un ouvrage à la bibliothèque. Elles sont recueillies directement auprès de vous,
          pour les seuls usages décrits ci-dessous.
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Ce que nous collectons</p>
        <h2 class="titre-filet">Les données selon ce que vous nous demandez</h2>
      </div>
      <div>
        <p><strong>Achat d’un bon cadeau</strong> — vos nom, prénom, e-mail et téléphone,
          ainsi que le nom du bénéficiaire si vous offrez le vol à quelqu’un d’autre.</p>
        <p><strong>Compte adhérent, pré-inscription et réinscription</strong> — état civil,
          date et lieu de naissance, adresse, téléphones, e-mail, personne à prévenir en cas
          d’urgence, numéros et dates de validité de vos titres aéronautiques (LAPL, PPL,
          SEP, visite médicale, licence FFA) et, pour un membre mineur, l’identité de ses
          représentants légaux et leur autorisation.</p>
        <p><strong>Bibliothèque des adhérents</strong> — l’historique de vos emprunts
          d’ouvrages.</p>
        <p><strong>Lettre d’information du club</strong> — votre e-mail et l’historique des
          envois, pour les adhérents qui la reçoivent.</p>
        <p><strong>Navigation sur le site</strong> — un simple cookie de session technique.
          Ce site n’utilise ni Google Analytics, ni police ou script chargés depuis un
          serveur tiers.</p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Pourquoi</p>
        <h2 class="titre-filet">Finalités et base légale</h2>
      </div>
      <div>
        <ul class="liste-check" style="margin-top:0">
          <li><strong>Vendre et honorer un bon cadeau</strong> : exécution du contrat de
            vente.</li>
          <li><strong>Gérer votre adhésion</strong> (cotisation, dossier, accès aux
            aéronefs) : exécution du contrat d’adhésion et respect des obligations
            réglementaires propres à l’activité aérienne (validité des titres et de la
            visite médicale).</li>
          <li><strong>Assurer votre sécurité en vol</strong> (personne à prévenir,
            informations médicales de validité) : intérêt légitime et obligation légale.</li>
          <li><strong>Vous tenir informé de la vie du club</strong> : intérêt légitime d’une
            association envers ses adhérents ; vous pouvez vous y opposer à tout moment.</li>
          <li><strong>Gérer les prêts de la bibliothèque</strong> : exécution du service
            rendu aux adhérents.</li>
        </ul>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Membres mineurs</p>
        <h2 class="titre-filet">Le consentement des représentants légaux</h2>
      </div>
      <div>
        <p>
          Un mineur ne peut adhérer ou pratiquer les sports aériens au sein du club qu’avec
          l’autorisation expresse de ses parents ou représentants légaux, recueillie au
          moment de l’inscription. Les données les concernant ne servent qu’à cette
          autorisation et au dossier du membre.
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Partage</p>
        <h2 class="titre-filet">Qui a accès à vos données</h2>
      </div>
      <div>
        <p>Vos données ne sont jamais vendues ni cédées à des fins commerciales. Elles sont
          partagées uniquement avec :</p>
        <ul class="liste-check" style="margin-top:.5rem">
          <li>le secrétariat et le bureau du club, pour traiter votre demande ;</li>
          <li><strong><?= e(REALISATION['raison_sociale']) ?></strong>, prestataire qui
            réalise et maintient ce site, dans la stricte mesure nécessaire à son bon
            fonctionnement ;</li>
          <li><strong><?= e(HEBERGEUR['nom']) ?></strong>, qui héberge le site et les
            e-mails du club ;</li>
          <li><strong>Stripe</strong>, notre prestataire de paiement, pour le seul règlement
            par carte bancaire. <strong>Vos coordonnées bancaires ne transitent jamais par
            nos serveurs</strong> : elles sont saisies directement sur les pages sécurisées
            de Stripe.</li>
        </ul>
        <p style="margin-top:.75rem">
          Certains de ces prestataires peuvent traiter des données en dehors de l’Union
          européenne (Stripe, notamment, aux États-Unis) ; ces transferts sont encadrés par
          les clauses contractuelles types de la Commission européenne.
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Conservation</p>
        <h2 class="titre-filet">Combien de temps nous gardons vos données</h2>
      </div>
      <div>
        <ul class="liste-check" style="margin-top:0">
          <li><strong>Bon cadeau</strong> : le temps de sa durée de validité, puis la durée
            légale de conservation des pièces comptables.</li>
          <li><strong>Dossier d’adhérent</strong> : la durée de votre adhésion, puis la
            durée légale de conservation comptable et réglementaire applicable au club.</li>
          <li><strong>Pré-inscription non finalisée</strong> : le temps nécessaire au
            traitement de votre demande.</li>
          <li><strong>Lettre d’information</strong> : jusqu’à votre opposition ou la fin de
            votre adhésion.</li>
        </ul>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Sécurité</p>
        <h2 class="titre-filet">Comment vos données sont protégées</h2>
      </div>
      <div>
        <p>
          Le site est servi en connexion chiffrée (HTTPS), les mots de passe des comptes
          adhérents et administrateurs sont stockés sous forme de hachage, et l’accès aux
          dossiers d’adhérents et aux outils d’administration est réservé aux personnes
          habilitées du club. En cas d’incident de sécurité affectant vos données, nous en
          informerons les autorités compétentes conformément à la réglementation.
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Cookies</p>
        <h2 class="titre-filet">Les traceurs utilisés sur ce site</h2>
      </div>
      <div>
        <p>
          Le site dépose un unique cookie de session, strictement nécessaire à son
          fonctionnement (rester connecté à votre compte adhérent) : il est exempté de
          consentement et ne sert à aucun suivi publicitaire ou statistique.
        </p>
        <p>
          Sur la page de paiement, le prestataire Stripe dépose ses propres cookies de
          lutte contre la fraude, indispensables au traitement sécurisé de votre paiement.
          Le site n’utilise par ailleurs ni Google Analytics, ni réseau social intégré, ni
          police ou script chargés depuis un serveur tiers.
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Vos droits</p>
        <h2 class="titre-filet">Accès, rectification, suppression…</h2>
      </div>
      <div>
        <p>
          Conformément au Règlement général sur la protection des données (RGPD) et à la
          loi Informatique et Libertés, vous disposez d’un droit d’accès, de rectification,
          d’effacement, de limitation, d’opposition et de portabilité sur les données vous
          concernant.
        </p>
        <p>
          Pour exercer l’un de ces droits, écrivez à
          <a href="mailto:<?= e(CLUB['email']) ?>"><?= e(CLUB['email']) ?></a> en précisant
          votre demande ; un justificatif d’identité pourra vous être demandé. Nous vous
          répondrons dans un délai maximum d’un mois.
        </p>
        <p>
          Si vous estimez, après nous avoir contactés, que vos droits ne sont pas
          respectés, vous pouvez adresser une réclamation à la
          <a href="https://www.cnil.fr/fr/plaintes" target="_blank" rel="noopener">CNIL</a>.
        </p>
      </div>
    </div>

    <div class="entete-section">
      <div>
        <p class="surtitre">Contact</p>
        <h2 class="titre-filet">Une question sur vos données</h2>
      </div>
      <div>
        <p>
          <strong><?= e(CLUB['nom']) ?></strong><br>
          <?= e(CLUB['adresse_1']) ?>, <?= e(CLUB['adresse_2']) ?><br>
          E-mail : <a href="mailto:<?= e(CLUB['email']) ?>"><?= e(CLUB['email']) ?></a>
        </p>
      </div>
    </div>

  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
