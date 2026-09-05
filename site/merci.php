<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bon-cadeau.php';
require_once __DIR__ . '/inc/stripe.php';
require_once __DIR__ . '/inc/mail.php';

session_demarrer();

$bon      = null;
$paye     = false;
$enAttente = false;

/* --- Retour de Stripe -------------------------------------------------
   Stripe renvoie ici avec ?payment_intent=pi_xxx&redirect_status=...
   On interroge l'API plutot que de croire le parametre d'URL : n'importe
   qui peut ecrire redirect_status=succeeded dans la barre d'adresse.
   Le webhook reste la source de verite ; ce passage sert a afficher le
   code tout de suite, sans attendre. bon_marquer_paye() est idempotent,
   les deux chemins peuvent donc l'appeler sans risque de doublon. */
$pi = (string) ($_GET['payment_intent'] ?? '');

if ($pi !== '' && stripe_actif()) {
    try {
        $intention = stripe_lire_intention($pi);
        $statut = (string) ($intention['status'] ?? '');
        $bon = bon_par_intention($pi);

        if (!$bon && !empty($intention['metadata']['bon_cadeau_id'])) {
            $bon = bon_par_id((int) $intention['metadata']['bon_cadeau_id']);
        }

        if ($bon && $statut === 'succeeded'
            && (int) ($intention['amount_received'] ?? 0) === (int) $bon['montant_cents']) {

            [$bon, $premierPassage] = bon_marquer_paye((int) $bon['id']);
            $paye = true;

            if ($premierPassage) {
                if (@email_bon_cadeau($bon)) {
                    bon_marquer_envoye((int) $bon['id']);
                }
                @email_paiement_recu_club($bon);
            }
            unset($_SESSION['bon_en_cours']);
        } elseif ($bon && in_array($statut, ['processing', 'requires_action'], true)) {
            $enAttente = true;
        }
    } catch (Throwable $e) {
        error_log('Retour Stripe : ' . $e->getMessage());
    }
}

/* --- Sans retour Stripe : demande enregistree, paiement pas encore actif */
$reference  = $bon['reference'] ?? ($_SESSION['derniere_reference'] ?? null);
$emailEnvoi = $bon['acheteur_email'] ?? ($_SESSION['dernier_email'] ?? null);
unset($_SESSION['derniere_reference'], $_SESSION['dernier_email']);

// Arrivee directe, sans rien avoir envoye.
if ($reference === null) {
    header('Location: /vols-decouvertes#formulaire', true, 302);
    exit;
}

$page  = 'merci';
$titre = $paye ? 'Paiement confirmé' : 'Demande envoyée';
$description = 'Votre bon cadeau pour un vol découverte.';
require __DIR__ . '/inc/header.php';
?>

<section class="section" style="padding-block:clamp(3.5rem,8vw,6rem)">
  <div class="conteneur">
    <div class="confirmation">

      <div class="confirmation__marque" aria-hidden="true">✓</div>

      <?php if ($paye): ?>

        <p class="surtitre">Paiement confirmé</p>
        <h1 class="titre-page">Merci, votre bon cadeau est prêt</h1>

        <div class="confirmation__reference">
          <span>N° de votre bon cadeau</span>
          <strong><?= e((string) ($bon['numero_bon'] ?: $bon['reference'])) ?></strong>
        </div>

        <p>
          Un email vient de partir
          <?php if ($emailEnvoi): ?>vers <strong><?= e($emailEnvoi) ?></strong><?php endif; ?>
          avec votre bon cadeau (PDF) et son numéro. Si vous ne le voyez pas d’ici
          quelques minutes, pensez à regarder dans vos courriers indésirables.
        </p>

        <p>
          Le bon est <strong>valable jusqu’au
          <?= e(date('d/m/Y', strtotime((string) $bon['expire_le']))) ?></strong>.
          Le bénéficiaire contacte le club, muni du code, pour convenir d’une
          date de vol selon la météo.
        </p>

      <?php elseif ($enAttente): ?>

        <p class="surtitre">Paiement en cours</p>
        <h1 class="titre-page">Votre paiement est en cours de traitement</h1>

        <div class="confirmation__reference">
          <span>Votre référence</span>
          <strong><?= e($reference) ?></strong>
        </div>

        <p>
          Votre banque doit encore confirmer l’opération. Dès que ce sera fait,
          vous recevrez votre bon cadeau par email — vous n’avez rien d’autre à faire.
        </p>

      <?php else: ?>

        <p class="surtitre">Demande enregistrée</p>
        <h1 class="titre-page">Merci, votre demande est bien arrivée</h1>

        <div class="confirmation__reference">
          <span>Votre référence</span>
          <strong><?= e($reference) ?></strong>
        </div>

        <p>
          Un accusé de réception vient d’être envoyé
          <?php if ($emailEnvoi): ?>à <strong><?= e($emailEnvoi) ?></strong><?php endif; ?>.
          Si vous ne le voyez pas d’ici quelques minutes, pensez à regarder
          dans vos courriers indésirables.
        </p>

        <p>
          Le club va vous recontacter très rapidement pour finaliser le règlement
          et vous transmettre votre bon cadeau, valable un an.
        </p>

      <?php endif; ?>

      <div class="encadre" style="text-align:left;max-width:32rem;margin:2rem auto 0">
        <p style="margin-bottom:.5rem"><strong>Une question ?</strong></p>
        <p style="margin:0">
          <a href="tel:<?= e(tel_lien(CLUB['tel_mobile'])) ?>"><?= e(CLUB['tel_mobile']) ?></a><br>
          <a href="mailto:<?= e(CLUB['email_vols']) ?>"><?= e(CLUB['email_vols']) ?></a>
        </p>
      </div>

      <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;margin-top:2.5rem">
        <a class="bouton" href="/">Retour à l’accueil</a>
        <a class="bouton bouton--contour" href="<?= e(url('vols-decouvertes')) ?>">Les vols découvertes</a>
      </div>

    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
