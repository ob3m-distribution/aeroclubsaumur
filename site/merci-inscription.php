<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/inscription.php';
require_once __DIR__ . '/inc/stripe.php';
session_demarrer();

$ins = null;
$paye = false;
$enAttente = false;

/* Retour de Stripe : ?payment_intent=pi_xxx. On interroge l'API (jamais l'URL). */
$pi = (string) ($_GET['payment_intent'] ?? '');
if ($pi !== '' && stripe_actif()) {
    try {
        $intention = stripe_lire_intention($pi);
        $statut = (string) ($intention['status'] ?? '');
        $ins = inscription_par_intention($pi);
        if (!$ins && !empty($intention['metadata']['inscription_id'])) {
            $s = db()->prepare('SELECT * FROM inscriptions WHERE id = ?');
            $s->execute([(int) $intention['metadata']['inscription_id']]);
            $ins = $s->fetch() ?: null;
        }
        if ($ins && $statut === 'succeeded'
            && (int) ($intention['amount_received'] ?? 0) === (int) $ins['total_cents']) {
            inscription_marquer_paye((int) $ins['id'], 'carte');
            $paye = true;
            unset($_SESSION['inscription_en_cours']);
        } elseif ($ins && in_array($statut, ['processing', 'requires_action'], true)) {
            $enAttente = true;
        }
    } catch (Throwable $e) {
        error_log('Retour Stripe inscription : ' . $e->getMessage());
    }
}

if (!$ins && ($_SESSION['inscription_en_cours'] ?? 0)) {
    $s = db()->prepare('SELECT * FROM inscriptions WHERE id = ?');
    $s->execute([(int) $_SESSION['inscription_en_cours']]);
    $ins = $s->fetch() ?: null;
}
if (!$ins) { header('Location: /reinscription', true, 302); exit; }

$page = 'merci';
$titre = $paye ? 'Paiement confirmé' : 'Paiement en cours';
$description = 'Votre cotisation au Saumur Air Club.';
require __DIR__ . '/inc/header.php';
?>
<section class="section">
  <div class="conteneur conteneur--etroit">
    <div class="carte-recu">
      <?php if ($paye): ?>
        <h1>Merci, votre cotisation est réglée ✓</h1>
        <p>Nous avons bien reçu votre paiement de <strong><?= e(prix((int) $ins['total_cents'])) ?></strong>.
          Votre adhésion sera finalisée par le secrétariat.</p>
      <?php elseif ($enAttente): ?>
        <h1>Paiement en cours de traitement</h1>
        <p>Votre banque valide le paiement. Vous recevrez la confirmation par e-mail sous peu.</p>
      <?php else: ?>
        <h1>Paiement non confirmé</h1>
        <p>Le paiement n’a pas pu être confirmé. Vous pouvez réessayer, ou régler par virement.</p>
        <p style="margin-top:1rem"><a class="bouton bouton--secondaire" href="/paiement-inscription">Réessayer le paiement</a></p>
      <?php endif; ?>
      <p style="margin-top:1rem"><a href="/">Retour à l’accueil</a></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
