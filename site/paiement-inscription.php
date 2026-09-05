<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/inscription.php';
require_once __DIR__ . '/inc/stripe.php';
session_demarrer();

/* On ne règle que l'inscription de SA propre session. */
$insId = (int) ($_SESSION['inscription_en_cours'] ?? 0);
if ($insId <= 0) { header('Location: /reinscription', true, 302); exit; }

$s = db()->prepare('SELECT * FROM inscriptions WHERE id = ?');
$s->execute([$insId]);
$ins = $s->fetch();
if (!$ins) { unset($_SESSION['inscription_en_cours']); header('Location: /reinscription', true, 302); exit; }

// Déjà réglée : on renvoie vers la confirmation.
if (!empty($ins['paye_le'])) { header('Location: /merci-inscription', true, 302); exit; }

$erreurStripe = null;
$clientSecret = null;
if (stripe_actif()) {
    try {
        $intention = stripe_creer_intention_inscription($ins);
        $clientSecret = $intention['client_secret'] ?? null;
        if (empty($ins['stripe_payment_intent_id']) && !empty($intention['id'])) {
            inscription_associer_intention($insId, $intention['id']);
        }
    } catch (Throwable $e) {
        error_log('Stripe inscription — intention : ' . $e->getMessage());
        $erreurStripe = 'Le service de paiement est momentanément indisponible.';
    }
}

$page = 'paiement-inscription';
$titre = 'Paiement de la cotisation';
$description = 'Réglez votre cotisation au Saumur Air Club.';
require __DIR__ . '/inc/header.php';
?>
<section class="section">
  <div class="conteneur conteneur--etroit">
    <p class="surtitre">Dernière étape</p>
    <h1>Régler votre cotisation</h1>

    <div class="carte-recap">
      <h2>Récapitulatif</h2>
      <p><?= e(inscription_resume_cotisation($ins)) ?></p>
      <p class="recap-total">Total à régler <strong><?= e(prix((int) $ins['total_cents'])) ?></strong></p>
    </div>

    <?php if ($clientSecret !== null): ?>
      <form class="formulaire" id="form-paiement">
        <div id="element-paiement"></div>
        <div class="alerte alerte--erreur" id="erreur-paiement" style="display:none;margin-top:1rem"></div>
        <div class="formulaire__pied" style="margin-top:1.25rem">
          <p class="formulaire__total">Total<br><strong><?= e(prix((int) $ins['total_cents'])) ?></strong></p>
          <button type="submit" class="bouton" id="bouton-payer">Payer <?= e(prix((int) $ins['total_cents'])) ?></button>
        </div>
        <p class="aide" style="margin-top:1rem">Paiement sécurisé par Stripe. Vos coordonnées bancaires
          ne transitent jamais par notre site.</p>
      </form>

      <script src="https://js.stripe.com/v3/"></script>
      <script>
      (function () {
        var stripe = Stripe(<?= json_encode(STRIPE_CLE_PUBLIQUE, JSON_THROW_ON_ERROR) ?>);
        var elements = stripe.elements({ clientSecret: <?= json_encode($clientSecret, JSON_THROW_ON_ERROR) ?>, locale: 'fr' });
        elements.create('payment', { layout: 'tabs' }).mount('#element-paiement');
        var form = document.getElementById('form-paiement'),
            bouton = document.getElementById('bouton-payer'),
            zoneErreur = document.getElementById('erreur-paiement');
        form.addEventListener('submit', async function (ev) {
          ev.preventDefault();
          if (bouton.getAttribute('aria-disabled') === 'true') return;
          bouton.setAttribute('aria-disabled', 'true');
          bouton.textContent = 'Paiement en cours…';
          zoneErreur.style.display = 'none';
          var res = await stripe.confirmPayment({
            elements: elements,
            confirmParams: { return_url: window.location.origin + '/merci-inscription' }
          });
          if (res.error) {
            zoneErreur.textContent = res.error.message || 'Le paiement n’a pas abouti. Vérifiez vos informations.';
            zoneErreur.style.display = 'block';
            bouton.removeAttribute('aria-disabled');
            bouton.textContent = <?= json_encode('Payer ' . prix((int) $ins['total_cents']), JSON_THROW_ON_ERROR) ?>;
          }
        });
      })();
      </script>

    <?php elseif ($erreurStripe !== null): ?>
      <div class="alerte alerte--erreur"><strong><?= e($erreurStripe) ?></strong>
        <p style="margin:.5rem 0 0">Réessayez dans un instant, ou réglez par virement (voir avec le club).</p>
      </div>
    <?php else: ?>
      <div class="alerte alerte--info">Le paiement par carte n’est pas encore actif. Réglez par virement.</div>
    <?php endif; ?>

    <p style="margin-top:1rem"><a href="/reinscription">← Retour</a></p>
  </div>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
