<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bon-cadeau.php';
require_once __DIR__ . '/inc/mail.php';
require_once __DIR__ . '/inc/stripe.php';

/* La session DOIT demarrer avant la moindre sortie : sinon PHP ne peut plus
   envoyer le cookie, le navigateur n'a pas de session, et le jeton CSRF
   genere a l'affichage du formulaire ne correspond a rien au POST. */
session_demarrer();

$erreurs = [];
$valeurs = [];

/* ---- Traitement du formulaire, AVANT toute sortie HTML ---------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    [$d, $erreurs] = valider_demande($_POST);
    $valeurs = $d;

    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $erreurs['csrf'] = 'Votre session a expiré. Merci de renvoyer le formulaire.';
    }

    // Piège à robots : rempli = c'est un script, on fait semblant d'accepter.
    $robot = trim((string) ($_POST['site_web'] ?? '')) !== '';

    if (!$erreurs && !$robot && trop_de_demandes(ip_client())) {
        $erreurs['debit'] = 'Trop de demandes envoyées depuis cette connexion. Merci de réessayer dans une heure, ou de nous appeler directement.';
    }

    if (!$erreurs) {
        if ($robot) {
            // On redirige comme si de rien n'était, sans rien enregistrer.
            header('Location: /merci', true, 303);
            exit;
        }
        try {
            $bon = creer_demande($d, ip_client());
            $bon['montant_affiche'] = prix(PRIX_BON_CADEAU_CENTIMES);

            $_SESSION['bon_en_cours']  = $bon['id'];
            $_SESSION['derniere_reference'] = $bon['reference'];
            $_SESSION['dernier_email'] = $bon['email'];
            unset($_SESSION['csrf']);           // jeton à usage unique

            // Redirection après POST : évite le renvoi du formulaire au rechargement.
            if (stripe_actif()) {
                header('Location: /paiement', true, 303);
                exit;
            }

            // Stripe pas encore configuré : on prévient le club, qui rappellera.
            // Un échec d'email ne doit pas perdre la demande : elle est déjà en base.
            @email_notification_club($bon);
            @email_accuse_acheteur($bon);
            header('Location: /merci', true, 303);
            exit;
        } catch (Throwable $ex) {
            error_log('Bon cadeau — échec enregistrement : ' . $ex->getMessage());
            $erreurs['technique'] = 'Une erreur technique nous empêche d’enregistrer votre demande. Merci de réessayer, ou de nous appeler au ' . CLUB['tel_mobile'] . '.';
        }
    }
}

$page = 'vols-decouvertes';
$description = 'Vol découverte au-dessus du Val de Loire : 30 minutes de survol des châteaux et du vignoble, à partir de 130 €. Vol d’initiation aux commandes avec un instructeur.';
require __DIR__ . '/inc/header.php';

$hero = [
  'page'         => true,
  'cle_image'    => 'vols.hero.image',
  'image'        => '/assets/img/cockpit-dr400.jpg',
  'alt'          => 'Vue depuis le cockpit d’un avion du club en vol au-dessus de la campagne',
  'titre'        => 'Vols découvertes & initiations',
  'cle_titre'    => 'vols.hero.titre',
  'accroche'     => 'Prenez de la hauteur pour une découverte de votre région, de la Loire majestueuse et des environs de Saumur.',
  'cle_accroche' => 'vols.hero.accroche',
];
require __DIR__ . '/inc/hero.php';
?>

<section class="section">
  <div class="conteneur">
    <div class="entete-section">
      <div>
        <p class="surtitre"><?= texte('vols.formules.surtitre', 'Nos formules') ?></p>
        <h2 class="titre-filet"><?= texte('vols.formules.titre', 'Deux façons de prendre l’air') ?></h2>
      </div>
      <div>
        <p><?= texte('vols.formules.texte',
          'Le vol découverte vous installe en passager, le temps d’un survol de la région. '
          . 'Le vol d’initiation est une démarche plus personnelle : sur rendez-vous avec un '
          . 'instructeur et sous sa responsabilité, c’est vous qui pilotez l’avion.', 'long') ?></p>
      </div>
    </div>

    <div class="grille grille--2">
      <article class="carte">
        <div class="carte__media">
          <?= image('vols.carte1.image', '/assets/img/vol-decouverte-cockpit.jpg', ['alt' => 'Deux passagers dans le cockpit pendant un vol découverte au-dessus des nuages', 'loading' => 'lazy', 'sizes' => '(min-width:860px) 50vw, 100vw']) ?>
          <div class="etiquettes"><span class="etiquette"><?= texte('vols.carte1.etiq1', 'En passager') ?></span><span class="etiquette"><?= texte('vols.carte1.etiq2', '30 min') ?></span></div>
        </div>
        <div class="carte__corps">
          <h3><?= texte('vols.carte1.titre', 'Le vol découverte') ?></h3>
          <p><?= texte('vols.carte1.texte',
            'Vous êtes passager. Installez-vous, regardez : Montsoreau, Brézé, l’abbaye '
            . 'de Fontevraud, le Cadre Noir et le château de Saumur défilent sous l’aile.', 'long') ?></p>
          <a class="carte__lien" href="#vol-decouverte">Voir les tarifs</a>
        </div>
      </article>

      <article class="carte">
        <div class="carte__media">
          <?= image('vols.carte2.image', '/assets/img/vol-initiation.jpg', ['alt' => 'Un avion du club (F-HACS) en vol au-dessus de Saumur et de la Loire', 'loading' => 'lazy', 'sizes' => '(min-width:860px) 50vw, 100vw']) ?>
          <div class="etiquettes"><span class="etiquette"><?= texte('vols.carte2.etiq1', 'Aux commandes') ?></span></div>
        </div>
        <div class="carte__corps">
          <h3><?= texte('vols.carte2.titre', 'Le vol d’initiation') ?></h3>
          <p><?= texte('vols.carte2.texte',
            'Briefing, visite prévol, check-list, puis vous pilotez. C’est le préalable '
            . 'à toute inscription au club, et souvent le premier pas vers le brevet.', 'long') ?></p>
          <a class="carte__lien" href="#vol-initiation">Voir les tarifs</a>
        </div>
      </article>
    </div>
  </div>
</section>

<section class="section section--gris" id="vol-decouverte">
  <div class="conteneur">
    <div class="entete-section">
      <div>
        <p class="surtitre"><?= texte('vols.dec.surtitre', 'Vol découverte') ?></p>
        <h2 class="titre-filet"><?= texte('vols.dec.titre', 'Survoler le Val de Loire') ?></h2>
      </div>
      <div>
        <p><?= texte('vols.dec.texte',
          'Le vol dure environ 30 minutes, selon la réglementation. Le tarif s’entend '
          . 'pour l’ensemble du vol, pas par personne : à trois, c’est 80 € chacun.', 'long') ?></p>
      </div>
    </div>

    <ul class="faits">
      <?php foreach (VOLS_DECOUVERTE as $nb => $centimes): ?>
        <li>
          <span class="faits__valeur"><?= e(prix($centimes)) ?></span>
          <span class="faits__libelle"><?= $nb ?> passager<?= $nb > 1 ? 's' : '' ?></span>
        </li>
      <?php endforeach; ?>
      <li>
        <span class="faits__valeur"><?= texte('vols.duree.valeur', '30 min') ?></span>
        <span class="faits__libelle"><?= texte('vols.duree.libelle', 'Durée du vol') ?></span>
      </li>
    </ul>

    <div class="encadre" style="margin-top:2rem">
      <p><?= texte('vols.meteo',
        'En cas de météo défavorable : nous vous préviendrons par téléphone afin que '
        . 'vous ne vous déplaciez pas pour rien, et le vol sera reporté selon les '
        . 'disponibilités de chacun.', 'long') ?></p>
    </div>
  </div>
</section>

<section class="section" id="vol-initiation">
  <div class="conteneur">
    <div class="entete-section">
      <div>
        <p class="surtitre"><?= texte('vols.init.surtitre', 'Vol d’initiation') ?></p>
        <h2 class="titre-filet"><?= texte('vols.init.titre', 'C’est vous qui pilotez') ?></h2>
      </div>
      <div>
        <p><?= texte('vols.init.texte',
          'Différent du vol découverte, le vol d’initiation est une démarche plus '
          . 'personnelle : uniquement sur rendez-vous avec un instructeur, sous sa '
          . 'responsabilité. Vous effectuez la visite prévol, la check-list, puis vous volez.', 'long') ?></p>
      </div>
    </div>

    <div class="tableau-enveloppe">
      <table>
        <caption class="visuellement-cache">Tarifs des vols d’initiation</caption>
        <thead>
          <tr>
            <th scope="col">Formule</th>
            <th scope="col">Durée</th>
            <th scope="col">Tarif</th>
          </tr>
        </thead>
        <tbody>
          <tr><td><?= texte('vols.init.f1.nom', '30 minutes') ?></td><td><?= texte('vols.init.f1.duree', '30 min') ?></td><td><?= texte('vols.init.f1.prix', '180 €') ?></td></tr>
          <tr><td><?= texte('vols.init.f2.nom', 'Passeport FFA') ?></td><td><?= texte('vols.init.f2.duree', '1 h 30') ?></td><td><?= texte('vols.init.f2.prix', '323 € + 16 € de licence et assurance') ?></td></tr>
          <tr><td><?= texte('vols.init.f3.nom', 'Passeport FFA') ?></td><td><?= texte('vols.init.f3.duree', '3 h') ?></td><td><?= texte('vols.init.f3.prix', '645 € + 16 € de licence et assurance') ?></td></tr>
        </tbody>
      </table>
    </div>
    <p class="texte-petit" style="margin-top:.75rem">
      <?= texte('vols.init.mention', 'Le vol d’initiation est le préalable à toute inscription au club.', 'long') ?>
    </p>
  </div>
</section>

<section class="section section--gris" id="bon-cadeau">
  <div class="conteneur">
    <div class="duo">
      <div>
        <p class="surtitre"><?= texte('vols.bon.surtitre', 'Idée cadeau') ?></p>
        <h2 class="titre-filet"><?= texte('vols.bon.titre', 'Offrez un vol découverte') ?></h2>
        <p class="prix"><?= e(prix(PRIX_BON_CADEAU_CENTIMES)) ?> <small>pour un passager</small></p>
        <ul class="liste-check" style="margin-top:1.25rem">
          <li><?= texte('vols.bon.point1', 'Valable un an à compter de l’achat.', 'long') ?></li>
          <li><?= texte('vols.bon.point2', 'Reçu par email en PDF, à imprimer ou à présenter sur téléphone.', 'long') ?></li>
          <li><?= texte('vols.bon.point3', 'Aucune date à choisir : le bénéficiaire contacte le club et convient d’un créneau selon la météo.', 'long') ?></li>
          <li><?= texte('vols.bon.point4', 'Paiement par carte bancaire, sécurisé.', 'long') ?></li>
        </ul>

        <a class="bouton" href="#formulaire">Commander le bon cadeau</a>
      </div>

      <div class="duo__media">
        <?= image('vols.bon.image', '/assets/img/bon-cadeau-recto-v2.jpg', [
              'alt' => 'Bon cadeau Saumur Air Club — une expérience inoubliable dans les airs',
              'width' => 718, 'height' => 1043, 'loading' => 'lazy',
              'style' => 'max-width:420px;margin-inline:auto;box-shadow:var(--ombre-carte)',
            ]) ?>
      </div>
    </div>

  </div>
</section>

<section class="section" id="commander">
  <div class="conteneur">
    <div class="entete-section">
      <div>
        <p class="surtitre"><?= texte('vols.commander.surtitre', 'Commander') ?></p>
        <h2 class="titre-filet"><?= texte('vols.commander.titre', 'Votre bon cadeau') ?></h2>
      </div>
      <div>
        <p>
          Renseignez vos coordonnées, puis réglez par carte bancaire.
          Votre bon vous est envoyé par email dans la foulée, valable un an.
        </p>
      </div>
    </div>

    <div class="colonne-etroite">
      <?php require __DIR__ . '/inc/formulaire-bon-cadeau.php'; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="conteneur">
    <div class="entete-section">
      <div>
        <p class="surtitre"><?= texte('vols.etapes.surtitre', 'Mode d’emploi') ?></p>
        <h2 class="titre-filet"><?= texte('vols.etapes.titre', 'Comment ça se passe') ?></h2>
      </div>
      <div>
        <p>
          Trois étapes, et aucune contrainte de date à l’achat : c’est un cadeau,
          le vol se cale plus tard avec le club.
        </p>
      </div>
    </div>

    <div class="etapes">
      <div class="etape">
        <h3><?= texte('vols.etape1.titre', 'Vous achetez le bon') ?></h3>
        <p><?= texte('vols.etape1.texte', 'Quelques informations, un paiement par carte, et c’est fait.', 'long') ?></p>
      </div>
      <div class="etape">
        <h3><?= texte('vols.etape2.titre', 'Vous l’offrez') ?></h3>
        <p><?= texte('vols.etape2.texte', 'Le bon arrive dans votre boîte mail en PDF, avec son code unique. À vous de le transmettre.', 'long') ?></p>
      </div>
      <div class="etape">
        <h3><?= texte('vols.etape3.titre', 'Le vol se prépare') ?></h3>
        <p>
          Le bénéficiaire contacte le club au
          <a href="tel:<?= e(tel_lien(CLUB['tel_mobile'])) ?>"><?= e(CLUB['tel_mobile']) ?></a>
          ou à <a href="mailto:<?= e(CLUB['email_vols']) ?>"><?= e(CLUB['email_vols']) ?></a>,
          et convient d’une date selon la météo.
        </p>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
