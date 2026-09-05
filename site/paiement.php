<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bon-cadeau.php';
require_once __DIR__ . '/inc/stripe.php';

session_demarrer();

/* On ne paie que le bon issu de SA propre session : impossible de payer
   — ou d'espionner — le bon de quelqu'un d'autre en changeant l'URL. */
$bonId = (int) ($_SESSION['bon_en_cours'] ?? 0);
if ($bonId <= 0) {
    header('Location: /vols-decouvertes#formulaire', true, 302);
    exit;
}

$bon = bon_par_id($bonId);
if (!$bon) {
    unset($_SESSION['bon_en_cours']);
    header('Location: /vols-decouvertes#formulaire', true, 302);
    exit;
}

// Deja regle : on renvoie vers la confirmation plutot que de faire payer deux fois.
if ($bon['statut'] !== 'en_attente_paiement') {
    header('Location: /merci', true, 302);
    exit;
}

$erreurStripe = null;
$clientSecret = null;

if (stripe_actif()) {
    try {
        $intention = stripe_creer_intention($bon);
        $clientSecret = $intention['client_secret'] ?? null;
        if (empty($bon['stripe_payment_intent_id']) && !empty($intention['id'])) {
            bon_associer_intention($bonId, $intention['id']);
        }
    } catch (Throwable $e) {
        error_log('Stripe — création intention : ' . $e->getMessage());
        $erreurStripe = 'Le service de paiement est momentanément indisponible.';
    }
}

$page  = 'paiement';
$titre = 'Paiement';
$description = 'Réglez votre bon cadeau pour un vol découverte.';
require __DIR__ . '/inc/header.php';
?>

<section class="section" style="padding-block:clamp(2.5rem,6vw,4rem)">
  <div class="conteneur">

    <div class="colonne-etroite">

      <p class="surtitre">Étape 2 sur 2</p>
      <h1 class="titre-page">Régler votre bon cadeau</h1>

      <!-- Récapitulatif -->
      <div class="formulaire" style="margin-bottom:1.5rem">
        <h2 style="font-size:1.125rem;margin-bottom:1rem">Récapitulatif</h2>
        <div class="tableau-enveloppe" style="border:0">
          <table>
            <tbody>
              <tr>
                <td>Bon cadeau — vol découverte, 30 minutes, 1 passager</td>
                <td style="text-align:right;white-space:nowrap"><?= e(prix((int) $bon['montant_cents'])) ?></td>
              </tr>
              <tr>
                <td><strong>Total à régler</strong></td>
                <td style="text-align:right;white-space:nowrap">
                  <strong><?= e(prix((int) $bon['montant_cents'])) ?></strong>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="texte-petit" style="margin:1rem 0 0">
          Référence <strong><?= e($bon['reference']) ?></strong> —
          au nom de <?= e($bon['acheteur_prenom'] . ' ' . $bon['acheteur_nom']) ?>,
          envoyé à <?= e($bon['acheteur_email']) ?>.
          <a href="/vols-decouvertes#formulaire">Modifier</a>
        </p>
      </div>

      <?php if ($clientSecret !== null): ?>

        <?php if (stripe_mode_test()): ?>
          <div class="alerte alerte--erreur" style="border-left-color:#B8860B">
            <strong>Mode test.</strong> Aucun paiement réel n’est encaissé.
            Carte d’essai : <code>4242 4242 4242 4242</code>, une date future, n’importe quel CVC.
          </div>
        <?php endif; ?>

        <form class="formulaire" id="form-paiement">
          <h2 style="font-size:1.125rem;margin-bottom:1rem">Paiement par carte</h2>

          <!-- Stripe injecte ici son champ carte. Les données bancaires vont
               directement chez Stripe : elles ne touchent jamais notre serveur. -->
          <div id="element-paiement"></div>

          <div id="erreur-paiement" class="alerte alerte--erreur"
               role="alert" style="display:none;margin:1.25rem 0 0"></div>

          <div class="formulaire__pied">
            <p class="formulaire__total">
              Total<br><strong><?= e(prix((int) $bon['montant_cents'])) ?></strong>
            </p>
            <button type="submit" class="bouton" id="bouton-payer">
              Payer <?= e(prix((int) $bon['montant_cents'])) ?>
            </button>
          </div>

          <p class="aide" style="margin-top:1rem">
            Paiement sécurisé par Stripe. Vos coordonnées bancaires ne transitent
            jamais par notre site.
          </p>
        </form>

        <script src="https://js.stripe.com/v3/"></script>
        <script>
          (function () {
            var stripe = Stripe(<?= json_encode(STRIPE_CLE_PUBLIQUE, JSON_THROW_ON_ERROR) ?>);
            var elements = stripe.elements({
              clientSecret: <?= json_encode($clientSecret, JSON_THROW_ON_ERROR) ?>,
              locale: 'fr',
              appearance: {
                theme: 'stripe',
                variables: {
                  colorPrimary: '#BA0202',
                  colorText: '#0E1112',
                  colorDanger: '#BA0202',
                  fontFamily: <?= json_encode(POLICES[police_active()]['famille'], JSON_THROW_ON_ERROR) ?>,
                  borderRadius: '3px'
                }
              }
            });
            elements.create('payment', { layout: 'tabs' }).mount('#element-paiement');

            var form = document.getElementById('form-paiement');
            var bouton = document.getElementById('bouton-payer');
            var zoneErreur = document.getElementById('erreur-paiement');

            function afficherErreur(message) {
              zoneErreur.textContent = message;
              zoneErreur.style.display = 'block';
              bouton.removeAttribute('aria-disabled');
              bouton.textContent = <?= json_encode('Payer ' . prix((int) $bon['montant_cents']), JSON_THROW_ON_ERROR) ?>;
              zoneErreur.scrollIntoView({ block: 'center' });
            }

            form.addEventListener('submit', async function (ev) {
              ev.preventDefault();
              if (bouton.getAttribute('aria-disabled') === 'true') return;

              bouton.setAttribute('aria-disabled', 'true');
              bouton.textContent = 'Paiement en cours…';
              zoneErreur.style.display = 'none';

              var res = await stripe.confirmPayment({
                elements: elements,
                confirmParams: {
                  return_url: window.location.origin + '/merci'
                }
              });

              // On n'arrive ici QUE si le paiement a échoué : sinon Stripe
              // a déjà redirigé le navigateur vers return_url.
              if (res.error) {
                afficherErreur(res.error.message ||
                  'Le paiement n’a pas abouti. Merci de vérifier vos informations.');
              }
            });
          })();
        </script>

      <?php elseif ($erreurStripe !== null): ?>

        <div class="alerte alerte--erreur">
          <strong><?= e($erreurStripe) ?></strong>
          <p style="margin:.5rem 0 0">
            Votre demande est enregistrée sous la référence
            <strong><?= e($bon['reference']) ?></strong>. Contactez le club au
            <a href="tel:<?= e(tel_lien(CLUB['tel_mobile'])) ?>"><?= e(CLUB['tel_mobile']) ?></a>
            pour finaliser le règlement.
          </p>
        </div>

      <?php else: ?>

        <div class="a-completer">
          <p>
            <strong>Le paiement en ligne n’est pas encore activé.</strong>
            Le compte Stripe du club reste à ouvrir. Dès que les clés seront
            renseignées dans <code>inc/config-local.php</code>, le champ de
            paiement s’affichera ici automatiquement — sans autre modification.
          </p>
          <p style="margin-bottom:0">
            En attendant, votre demande est bien enregistrée sous la référence
            <strong><?= e($bon['reference']) ?></strong> et le club vous
            recontacte pour le règlement.
          </p>
        </div>

        <div style="margin-top:1.5rem">
          <a class="bouton" href="/merci">Continuer</a>
        </div>

      <?php endif; ?>

    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
