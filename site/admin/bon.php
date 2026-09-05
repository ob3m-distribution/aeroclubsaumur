<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/bon-cadeau.php';
require_once __DIR__ . '/../inc/mail.php';
exiger_droit('bons.voir');

$id  = (int) ($_GET['id'] ?? 0);
$bon = $id > 0 ? bon_par_id($id) : null;

if (!$bon) {
    $_SESSION['message_erreur'] = 'Ce bon cadeau est introuvable.';
    header('Location: /admin/bons.php', true, 302);
    exit;
}

/* ---- Actions ---------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    exiger_droit('bons.gerer');

    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $_SESSION['message_erreur'] = 'Session expirée, action non effectuée.';
        header('Location: /admin/bon.php?id=' . $id, true, 303);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    try {
        switch ($action) {

            case 'marquer_utilise':
                if ($bon['statut'] !== 'paye') {
                    throw new RuntimeException('Seul un bon payé peut être marqué comme utilisé.');
                }
                $s = db()->prepare("UPDATE bons_cadeaux SET statut='utilise', utilise_le=NOW() WHERE id=?");
                $s->execute([$id]);
                journaliser('bon.utilise', 'bon#' . $id, (string) $bon['code']);
                $_SESSION['message_succes'] = 'Bon marqué comme utilisé.';
                break;

            case 'annuler':
                if (!in_array($bon['statut'], ['en_attente_paiement', 'paye'], true)) {
                    throw new RuntimeException('Ce bon ne peut plus être annulé.');
                }
                $s = db()->prepare("UPDATE bons_cadeaux SET statut='annule' WHERE id=?");
                $s->execute([$id]);
                journaliser('bon.annule', 'bon#' . $id, (string) ($bon['code'] ?? $bon['reference']));
                $_SESSION['message_succes'] = 'Bon annulé. Pensez au remboursement si le paiement a été encaissé.';
                break;

            case 'rouvrir':
                if ($bon['statut'] !== 'utilise') {
                    throw new RuntimeException('Seul un bon utilisé peut être rouvert.');
                }
                $s = db()->prepare("UPDATE bons_cadeaux SET statut='paye', utilise_le=NULL WHERE id=?");
                $s->execute([$id]);
                journaliser('bon.rouvert', 'bon#' . $id, (string) $bon['code']);
                $_SESSION['message_succes'] = 'Bon rouvert : il est de nouveau utilisable.';
                break;

            case 'renvoyer':
                if ($bon['statut'] !== 'paye' || $bon['code'] === null) {
                    throw new RuntimeException('Seul un bon payé peut être renvoyé.');
                }
                if (email_bon_cadeau($bon)) {
                    bon_marquer_envoye($id);
                    journaliser('bon.renvoye', 'bon#' . $id, (string) $bon['acheteur_email']);
                    $_SESSION['message_succes'] = 'Bon renvoyé à ' . $bon['acheteur_email'] . '.';
                } else {
                    throw new RuntimeException('L’envoi a échoué. Vérifiez l’adresse email.');
                }
                break;

            case 'prolonger':
                if ($bon['statut'] !== 'paye') {
                    throw new RuntimeException('Seul un bon payé peut être prolongé.');
                }
                $mois = max(1, min(6, (int) ($_POST['mois'] ?? 6)));
                $s = db()->prepare(
                    'UPDATE bons_cadeaux
                        SET date_fin_validite = DATE_ADD(GREATEST(COALESCE(date_fin_validite, CURDATE()), CURDATE()), INTERVAL ? MONTH),
                            expire_le = DATE_ADD(GREATEST(COALESCE(expire_le, CURDATE()), CURDATE()), INTERVAL ? MONTH)
                      WHERE id = ?'
                );
                $s->execute([$mois, $mois, $id]);
                journaliser('bon.prolonge', 'bon#' . $id, '+' . $mois . ' mois');
                $_SESSION['message_succes'] = 'Validité prolongée de ' . $mois . ' mois.';
                break;

            case 'rembourser':
                if (!in_array($bon['statut'], ['paye', 'utilise'], true)) {
                    throw new RuntimeException('Seul un bon payé peut être remboursé.');
                }
                $max = (int) $bon['montant_cents'];
                $partiel = ($_POST['type_remb'] ?? '') === 'partiel';
                $cents = $partiel
                    ? (int) round((float) str_replace(',', '.', (string) ($_POST['montant'] ?? '0')) * 100)
                    : $max;
                if ($cents <= 0 || $cents > $max) {
                    throw new RuntimeException('Montant de remboursement invalide (max ' . prix($max) . ').');
                }
                if (!empty($bon['stripe_payment_intent_id'])) {
                    require_once __DIR__ . '/../inc/stripe.php';
                    if (stripe_actif()) {
                        $params = ['payment_intent' => $bon['stripe_payment_intent_id']];
                        if ($cents < $max) $params['amount'] = $cents;   // partiel
                        try {
                            stripe_appel('POST', 'refunds', $params, 'refund-' . $id . '-' . $cents);
                        } catch (Throwable $e) {
                            throw new RuntimeException('Remboursement Stripe refusé : ' . $e->getMessage());
                        }
                    }
                }
                $total = $cents >= $max;
                if ($total) {
                    db()->prepare("UPDATE bons_cadeaux SET statut='rembourse' WHERE id=?")->execute([$id]);
                }
                journaliser('bon.rembourse', 'bon#' . $id, ($total ? 'total' : 'partiel') . ' ' . prix($cents));
                $_SESSION['message_succes'] = ($total
                    ? 'Bon remboursé intégralement (' . prix($cents) . ')'
                    : 'Remboursement partiel de ' . prix($cents) . ' effectué') . ' via Stripe.';
                break;

            case 'note':
                $note = trim((string) ($_POST['message'] ?? ''));
                $s = db()->prepare('UPDATE bons_cadeaux SET message = ? WHERE id = ?');
                $s->execute([$note !== '' ? mb_substr($note, 0, 1000) : null, $id]);
                journaliser('bon.note', 'bon#' . $id);
                $_SESSION['message_succes'] = 'Note enregistrée.';
                break;

            default:
                throw new RuntimeException('Action inconnue.');
        }
    } catch (Throwable $ex) {
        $_SESSION['message_erreur'] = $ex->getMessage();
    }

    header('Location: /admin/bon.php?id=' . $id, true, 303);
    exit;
}

$titre = 'Bon ' . ($bon['code'] ?? $bon['reference']);
$actif = 'bons';
require __DIR__ . '/inc/entete.php';

[$lib, $cls] = STATUTS_BON[$bon['statut']] ?? [$bon['statut'], 'expire'];
$gere = peut('bons.gerer');
?>

<p><a href="/admin/bons.php">← Retour à la liste</a></p>

<div class="detail">

  <div>
    <div class="bloc">
      <div class="bloc__titre">
        <h2>Bon cadeau</h2>
        <span class="etat etat--<?= e($cls) ?>"><?= e($lib) ?></span>
      </div>

      <dl class="paire">
        <dt>Référence</dt><dd class="code-bon"><?= e($bon['reference']) ?></dd>
        <?php if ($bon['code']): ?>
          <dt>Code du bon</dt>
          <dd class="code-bon" style="font-size:1.125rem"><strong><?= e($bon['code']) ?></strong></dd>
        <?php endif; ?>
        <dt>Montant</dt><dd><?= e(prix((int) $bon['montant_cents'])) ?></dd>
        <dt>Créé le</dt><dd><?= e(date('d/m/Y à H:i', strtotime((string) $bon['cree_le']))) ?></dd>
        <?php if ($bon['paye_le']): ?>
          <dt>Payé le</dt><dd><?= e(date('d/m/Y à H:i', strtotime((string) $bon['paye_le']))) ?></dd>
        <?php endif; ?>
        <?php if ($bon['expire_le']): ?>
          <dt>Valable jusqu’au</dt>
          <dd>
            <?= e(date('d/m/Y', strtotime((string) $bon['expire_le']))) ?>
            <?php
              $jours = (int) floor((strtotime((string) $bon['expire_le']) - time()) / 86400);
              if ($bon['statut'] === 'paye' && $jours <= 60):
            ?>
              <span class="etat etat--planifie" style="margin-left:.4rem">
                <?= $jours >= 0 ? "encore {$jours} jour" . ($jours > 1 ? 's' : '') : 'dépassé' ?>
              </span>
            <?php endif; ?>
          </dd>
        <?php endif; ?>
        <?php if ($bon['utilise_le']): ?>
          <dt>Utilisé le</dt><dd><?= e(date('d/m/Y', strtotime((string) $bon['utilise_le']))) ?></dd>
        <?php endif; ?>
        <?php if ($bon['pdf_envoye_le']): ?>
          <dt>Bon envoyé le</dt><dd><?= e(date('d/m/Y à H:i', strtotime((string) $bon['pdf_envoye_le']))) ?></dd>
        <?php endif; ?>
        <?php if ($bon['stripe_payment_intent_id']): ?>
          <dt>Paiement Stripe</dt>
          <dd><span class="champ-lecture"><?= e($bon['stripe_payment_intent_id']) ?></span></dd>
        <?php endif; ?>
        <dt>CGV acceptées</dt>
        <dd><?= e(date('d/m/Y à H:i', strtotime((string) $bon['cgv_acceptees_le']))) ?></dd>
      </dl>
    </div>

    <div class="bloc">
      <h2>Acheteur</h2>
      <dl class="paire">
        <dt>Nom</dt><dd><?= e($bon['acheteur_prenom'] . ' ' . $bon['acheteur_nom']) ?></dd>
        <dt>Email</dt><dd><a href="mailto:<?= e($bon['acheteur_email']) ?>"><?= e($bon['acheteur_email']) ?></a></dd>
        <dt>Téléphone</dt><dd><a href="tel:<?= e($bon['acheteur_telephone']) ?>"><?= e($bon['acheteur_telephone']) ?></a></dd>
      </dl>
    </div>

    <div class="bloc">
      <h2>Note interne</h2>
      <?php if ($gere): ?>
        <form method="post" data-unique>
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <input type="hidden" name="action" value="note">
          <div class="champ">
            <label for="message" class="visuellement-cache">Note</label>
            <textarea id="message" name="message" maxlength="1000"
                      placeholder="Message de l’acheteur, remarque interne…"><?= e((string) ($bon['message'] ?? '')) ?></textarea>
          </div>
          <div class="actions" style="margin-top:.75rem">
            <button type="submit" class="btn btn--contour">Enregistrer la note</button>
          </div>
        </form>
      <?php else: ?>
        <p><?= $bon['message'] ? e((string) $bon['message']) : '<span style="color:var(--gris-500)">Aucune note.</span>' ?></p>
      <?php endif; ?>
    </div>

  </div>

  <div>
    <div class="bloc">
      <h2>Actions</h2>

      <?php if (!$gere): ?>
        <p style="color:var(--gris-500)">
          Votre rôle ne permet pas de modifier les bons cadeaux.
        </p>
      <?php else: ?>

        <div class="actions" style="flex-direction:column;align-items:stretch">

          <?php if ($bon['statut'] === 'paye'): ?>
            <form method="post" data-unique>
              <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
              <input type="hidden" name="action" value="marquer_utilise">
              <button type="submit" class="btn" style="width:100%;justify-content:center"
                      data-confirmer="Marquer ce bon comme utilisé ? Le vol a bien été effectué ?">
                Marquer comme utilisé
              </button>
            </form>

            <form method="post" data-unique>
              <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
              <input type="hidden" name="action" value="renvoyer">
              <button type="submit" class="btn btn--contour" style="width:100%;justify-content:center">
                Renvoyer le bon par email
              </button>
            </form>

            <details class="bon-prolonger">
              <summary class="btn btn--contour" style="width:100%;justify-content:center;list-style:none">Prolonger</summary>
              <form method="post" data-unique style="margin-top:.5rem;display:flex;gap:.5rem;align-items:center">
                <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                <input type="hidden" name="action" value="prolonger">
                <select name="mois" style="flex:1;padding:.5rem;border:1px solid var(--gris-300);border-radius:6px">
                  <?php for ($m = 1; $m <= 6; $m++): ?>
                    <option value="<?= $m ?>"<?= $m === 6 ? ' selected' : '' ?>><?= $m ?> mois</option>
                  <?php endfor; ?>
                </select>
                <button type="submit" class="btn">Valider</button>
              </form>
            </details>

          <?php endif; ?>

          <?php if ($bon['statut'] === 'utilise'): ?>
            <form method="post" data-unique>
              <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
              <input type="hidden" name="action" value="rouvrir">
              <button type="submit" class="btn btn--contour" style="width:100%;justify-content:center"
                      data-confirmer="Rouvrir ce bon ? Il redeviendra utilisable.">
                Rouvrir le bon
              </button>
            </form>
          <?php endif; ?>

          <?php if (in_array($bon['statut'], ['paye', 'utilise'], true)): ?>
            <button type="button" class="btn btn--danger" style="width:100%;justify-content:center"
                    onclick="document.getElementById('modal-remb').showModal()">Rembourser le vol</button>

            <dialog id="modal-remb" class="modal-remb">
              <form method="post">
                <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                <input type="hidden" name="action" value="rembourser">
                <h3 style="margin:0 0 .35rem">Rembourser le vol</h3>
                <p class="muet" style="margin:0 0 1rem;font-size:.85rem">Montant réglé :
                  <strong><?= e(prix((int) $bon['montant_cents'])) ?></strong> · remboursé sur la carte via Stripe.</p>

                <label class="remb-opt"><input type="radio" name="type_remb" value="total" checked>
                  <span><strong>Remboursement total</strong> — <?= e(prix((int) $bon['montant_cents'])) ?></span></label>
                <label class="remb-opt"><input type="radio" name="type_remb" value="partiel">
                  <span><strong>Remboursement partiel</strong> — indiquez le montant</span></label>

                <div id="remb-montant" style="display:none;margin:.4rem 0 0">
                  <label for="montant" style="font-size:.85rem">Montant à rembourser (€)</label>
                  <input type="number" id="montant" name="montant" step="0.01" min="0.5"
                         max="<?= number_format((int) $bon['montant_cents'] / 100, 2, '.', '') ?>"
                         placeholder="0,00" style="width:100%;padding:.5rem;border:1px solid var(--gris-300);border-radius:6px">
                </div>

                <div class="actions" style="margin-top:1.25rem;justify-content:flex-end">
                  <button type="button" class="btn btn--contour" onclick="document.getElementById('modal-remb').close()">Annuler</button>
                  <button type="submit" class="btn btn--danger">Procéder au remboursement via Stripe</button>
                </div>
              </form>
            </dialog>
            <script>
            (function () {
              var d = document.getElementById('modal-remb');
              d.querySelectorAll('input[name="type_remb"]').forEach(function (r) {
                r.addEventListener('change', function () {
                  document.getElementById('remb-montant').style.display = (this.value === 'partiel') ? 'block' : 'none';
                });
              });
            })();
            </script>
          <?php endif; ?>

          <?php if (in_array($bon['statut'], ['en_attente_paiement', 'paye'], true)): ?>
            <form method="post" data-unique>
              <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
              <input type="hidden" name="action" value="annuler">
              <button type="submit" class="btn btn--danger" style="width:100%;justify-content:center"
                      data-confirmer="Annuler ce bon ? Si le paiement a été encaissé, pensez au remboursement depuis Stripe.">
                Annuler le bon
              </button>
            </form>
          <?php endif; ?>

        </div>

        <?php if ($bon['statut'] === 'en_attente_paiement'): ?>
          <p style="margin-top:1rem;font-size:.8125rem;color:var(--gris-500)">
            Ce bon n’est pas encore payé : il n’a donc pas de code et n’a pas été envoyé.
          </p>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  </div>

</div>

<?php require __DIR__ . '/inc/pied.php'; ?>
