<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bon-cadeau.php';
require_once __DIR__ . '/inc/helloasso.php';

session_demarrer();

/* Lien de paiement envoyé par le club (bon saisi au back-office) : le jeton
   secret du lien prouve que c'est bien le destinataire de l'e-mail. On
   rattache le bon à la session, puis on revient sur l'adresse propre. */
if (isset($_GET['bon'], $_GET['jeton'])) {
    $bonLien = bon_par_lien((int) $_GET['bon'], (string) $_GET['jeton']);
    if ($bonLien) {
        $_SESSION['bon_en_cours'] = (int) $bonLien['id'];
        $_SESSION['derniere_reference'] = $bonLien['reference'];
        $_SESSION['dernier_email'] = $bonLien['acheteur_email'];
        header('Location: /paiement', true, 303);
        exit;
    }
    header('Location: /vols-decouvertes', true, 302);
    exit;
}

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

$erreurPaiement = null;
if (isset($_GET['erreur'])) {
    $erreurPaiement = 'Le paiement n’a pas abouti. Vous pouvez réessayer.';
}

/* Le clic sur « Payer » crée le paiement chez HelloAsso puis y envoie le client.
   Le montant vient de notre base, jamais du navigateur ; le jeton lié à la
   session empêche qu'un robot ou un lien piégé déclenche la création. */
if (isset($_GET['payer'])) {
    if (!jeton_csrf_valide($_GET['t'] ?? null)) {
        $erreurPaiement = 'Votre session a expiré. Merci de réessayer.';
    } elseif (!ha_actif()) {
        $erreurPaiement = 'Le paiement en ligne n’est pas encore actif.';
    } else {
        try {
            $url = ha_creer_paiement(
                'bon', $bonId, (int) $bon['montant_cents'],
                'Bon cadeau — ' . libelle_vol_bon($bon) . ' (' . $bon['reference'] . ')',
                ['prenom' => $bon['acheteur_prenom'], 'nom' => $bon['acheteur_nom'], 'email' => $bon['acheteur_email']],
                ha_url_site('/merci'),
                ha_url_site('/paiement')
            );
            header('Location: ' . $url, true, 303);
            exit;
        } catch (Throwable $e) {
            error_log('HelloAsso bon — création du paiement : ' . $e->getMessage());
            $erreurPaiement = 'Le service de paiement est momentanément indisponible.';
        }
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
                <td>Bon cadeau — <?= e(libelle_vol_bon($bon)) ?></td>
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

      <?php if ($erreurPaiement !== null): ?>
        <div class="alerte alerte--erreur" role="alert">
          <strong><?= e($erreurPaiement) ?></strong>
          <p style="margin:.5rem 0 0">
            Votre demande est enregistrée sous la référence
            <strong><?= e($bon['reference']) ?></strong>. En cas de difficulté, contactez le club au
            <a href="tel:<?= e(tel_lien(CLUB['tel_mobile'])) ?>"><?= e(CLUB['tel_mobile']) ?></a>.
          </p>
        </div>
      <?php endif; ?>

      <?php if (ha_actif()): ?>

        <?php if (ha_mode_test()): ?>
          <div class="alerte alerte--erreur" style="border-left-color:#B8860B">
            <strong>Mode test.</strong> Aucun paiement réel n’est encaissé.
          </div>
        <?php endif; ?>

        <div class="formulaire">
          <h2 style="font-size:1.125rem;margin-bottom:1rem">Paiement par carte</h2>
          <p class="texte-petit" style="margin:0 0 1.25rem">
            Paiement sécurisé par HelloAsso : vous allez être redirigé vers leur page de paiement,
            puis ramené ici. Vos coordonnées bancaires ne transitent jamais par notre site.
            HelloAsso propose une <strong>contribution volontaire</strong> pour son fonctionnement :
            elle est facultative, vous pouvez la ramener à 0 €.
          </p>
          <div class="formulaire__pied">
            <p class="formulaire__total">
              Total<br><strong><?= e(prix((int) $bon['montant_cents'])) ?></strong>
            </p>
            <a class="bouton" href="/paiement?payer=1&amp;t=<?= e(jeton_csrf()) ?>">
              Payer <?= e(prix((int) $bon['montant_cents'])) ?>
            </a>
          </div>
        </div>

      <?php elseif ($erreurPaiement === null): ?>

        <div class="a-completer">
          <p>
            <strong>Le paiement en ligne n’est pas encore activé.</strong>
            Votre demande est bien enregistrée sous la référence
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
