<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/inscription.php';
require_once __DIR__ . '/inc/helloasso.php';
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

$erreur = null;
if (isset($_GET['erreur'])) {
    $erreur = 'Le paiement n’a pas abouti. Vous pouvez réessayer, ou régler par virement (voir avec le club).';
}

/* Le clic sur « Payer » crée le paiement chez HelloAsso puis y envoie le client.
   Le montant vient de notre base, jamais du navigateur ; le jeton lié à la
   session empêche qu'un robot ou un lien piégé déclenche la création. */
if (isset($_GET['payer'])) {
    if (!jeton_csrf_valide($_GET['t'] ?? null)) {
        $erreur = 'Votre session a expiré. Merci de réessayer.';
    } elseif (!ha_actif()) {
        $erreur = 'Le paiement en ligne n’est pas encore actif.';
    } else {
        try {
            $url = ha_creer_paiement(
                'inscription', $insId, (int) $ins['total_cents'],
                'Cotisation ' . (int) $ins['annee'] . ' — ' . trim($ins['prenom'] . ' ' . $ins['nom']),
                ['prenom' => $ins['prenom'], 'nom' => $ins['nom'], 'email' => $ins['courriel']],
                ha_url_site('/merci-inscription'),
                ha_url_site('/paiement-inscription')
            );
            header('Location: ' . $url, true, 303);
            exit;
        } catch (Throwable $e) {
            error_log('HelloAsso inscription — création du paiement : ' . $e->getMessage());
            $erreur = 'Le service de paiement est momentanément indisponible.';
        }
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

    <?php if ($erreur !== null): ?>
      <div class="alerte alerte--erreur" role="alert"><strong><?= e($erreur) ?></strong></div>
    <?php endif; ?>

    <?php if (ha_actif()): ?>
      <?php if (ha_mode_test()): ?>
        <div class="alerte alerte--erreur" style="border-left-color:#B8860B">
          <strong>Mode test.</strong> Aucun paiement réel n’est encaissé.
        </div>
      <?php endif; ?>
      <p style="margin:1.25rem 0">
        <a class="bouton" href="/paiement-inscription?payer=1&amp;t=<?= e(jeton_csrf()) ?>">Payer <?= e(prix((int) $ins['total_cents'])) ?> par carte</a>
      </p>
      <p class="aide">Paiement sécurisé par HelloAsso : vous allez être redirigé vers leur page de paiement,
        puis ramené ici. Vos coordonnées bancaires ne transitent jamais par notre site.
        HelloAsso propose une <strong>contribution volontaire</strong> pour son fonctionnement : elle est facultative,
        vous pouvez la ramener à 0 €.</p>
    <?php else: ?>
      <div class="alerte alerte--info">Le paiement par carte n’est pas encore actif. Réglez par virement.</div>
    <?php endif; ?>

    <p style="margin-top:1rem"><a href="/reinscription">← Retour</a></p>
  </div>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
