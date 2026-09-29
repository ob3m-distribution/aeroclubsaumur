<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/tarifs.php';
exiger_droit('tarifs.gerer');

/* ---- Enregistrement ------------------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $_SESSION['message_erreur'] = 'Session expirée, rien enregistré.';
        header('Location: /admin/tarifs.php', true, 303);
        exit;
    }

    $saisies  = (array) ($_POST['tarif'] ?? []);
    $erreurs  = [];
    $modifies = [];
    $suppr    = db()->prepare('DELETE FROM parametres WHERE cle = ?');

    foreach (TARIFS as $cle => [$cat, $libelle, $defaut]) {
        if (!array_key_exists($cle, $saisies)) continue;
        $cents = centimes_depuis_saisie((string) $saisies[$cle]);
        if ($cents === null) {
            $erreurs[] = $libelle;
            continue;
        }
        if ($cents === tarif($cle)) continue;              // inchangé
        if ($cents === $defaut) {
            $suppr->execute(['tarif.' . $cle]);           // retour à la valeur d'origine
        } else {
            definir_parametre('tarif.' . $cle, (string) $cents);
        }
        $modifies[] = $libelle . ' : ' . prix($cents);
    }

    foreach (RIB_CHAMPS as $cle => [$libelle]) {
        if (!array_key_exists($cle, (array) ($_POST['rib'] ?? []))) continue;
        $v = trim(preg_replace('/\s+/', ' ', (string) $_POST['rib'][$cle]));
        if ($cle === 'rib.iban' || $cle === 'rib.bic') $v = strtoupper($v);
        if ($v === (tarifs_modifies()[$cle] ?? '')) continue;
        if ($v === '') {
            $suppr->execute([$cle]);
        } else {
            definir_parametre($cle, mb_substr($v, 0, 80));
        }
        $modifies[] = $libelle;
    }

    if ($modifies) {
        journaliser('tarifs.maj', 'tarifs', implode(' · ', $modifies));
    }
    if ($erreurs) {
        $_SESSION['message_erreur'] = 'Montant non valide, non enregistré : ' . implode(', ', $erreurs) . '.';
    }
    if ($modifies) {
        $_SESSION['message_succes'] = count($modifies) . ' modification(s) enregistrée(s). Le site est à jour.';
    } elseif (!$erreurs) {
        $_SESSION['message_succes'] = 'Aucun changement.';
    }
    header('Location: /admin/tarifs.php', true, 303);
    exit;
}

$modifs = tarifs_modifies(true);
$parCategorie = [];
foreach (TARIFS as $cle => $t) {
    $parCategorie[$t[0]][$cle] = $t;
}

/** « 130 » ou « 130,50 » : format de saisie. */
$enEuros = static fn(int $c): string => $c % 100 ? number_format($c / 100, 2, ',', '') : (string) intdiv($c, 100);

$titre = 'Tarifs et prix';
$actif = 'tarifs';
require __DIR__ . '/inc/entete.php';
?>

<div class="bloc">
  <p class="aide" style="margin:0">
    Tous les prix du site au même endroit. Une modification s’applique immédiatement aux pages
    (Vols découvertes, Tarifs, Avions, FAQ, CGV), aux formulaires (bon cadeau, inscription,
    réinscription) et aux montants à payer. Les bons déjà achetés gardent le prix payé.
    Montants en euros : « 130 » ou « 130,50 ».
  </p>
</div>

<form method="post" data-unique>
  <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

  <?php foreach (TARIFS_CATEGORIES as $cat => $nomCat): if (empty($parCategorie[$cat])) continue; ?>
    <details class="bloc tarifs-cat">
      <summary class="tarifs-cat__tete">
        <span><?= e($nomCat) ?></span>
        <span class="muet"><?= count($parCategorie[$cat]) ?> tarif<?= count($parCategorie[$cat]) > 1 ? 's' : '' ?></span>
      </summary>
      <div class="tableau">
        <table>
          <thead><tr><th>Prestation</th><th style="width:11rem">Prix (€)</th><th>Valeur d’origine</th></tr></thead>
          <tbody>
          <?php foreach ($parCategorie[$cat] as $cle => [, $libelle, $defaut, $unite]):
              $actuel = tarif($cle); $change = isset($modifs['tarif.' . $cle]); ?>
            <tr>
              <td><label for="t-<?= e($cle) ?>"><?= e($libelle) ?></label>
                <?php if ($change): ?><span class="etat etat--planifie" style="margin-left:.35rem">modifié</span><?php endif; ?></td>
              <td>
                <span class="tarifs-saisie">
                  <input type="text" inputmode="decimal" id="t-<?= e($cle) ?>" name="tarif[<?= e($cle) ?>]"
                         value="<?= e($enEuros($actuel)) ?>" required pattern="\s*\d{1,6}([.,]\d{1,2})?\s*€?\s*">
                  <span>€<?= e($unite) ?></span>
                </span>
              </td>
              <td class="muet" style="font-size:.8125rem"><?= e(prix($defaut) . $unite) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($cat === 'inscription'): ?>
        <p class="aide" style="margin:.75rem 0 0">Sur la page Tarifs, « Cotisation 25 ans et plus » = Membre Club + Pilote,
          « moins de 25 ans » = Membre Club + Pilote −25 ans.</p>
      <?php endif; ?>
    </details>
  <?php endforeach; ?>

  <details class="bloc tarifs-cat">
    <summary class="tarifs-cat__tete">
      <span>Coordonnées bancaires (RIB du club)</span>
      <span class="muet">envoyées pour les paiements par virement</span>
    </summary>
    <div class="champs champs--duo" style="margin-top:.75rem">
      <?php foreach (RIB_CHAMPS as $cle => [$libelle]): ?>
        <div class="champ">
          <label for="r-<?= e($cle) ?>"><?= e($libelle) ?></label>
          <input type="text" id="r-<?= e($cle) ?>" name="rib[<?= e($cle) ?>]" maxlength="80"
                 value="<?= e($modifs[$cle] ?? '') ?>"
                 placeholder="<?= e(RIB_CHAMPS[$cle][1] !== '' ? RIB_CHAMPS[$cle][1] : 'à renseigner') ?>">
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (rib('iban') === ''): ?>
      <p class="message message--erreur" style="margin:.75rem 0 0">IBAN non renseigné : les e-mails de virement
        indiquent pour l’instant « communiqué par le club sur simple demande ».</p>
    <?php endif; ?>
  </details>

  <div class="actions" style="margin-top:1rem">
    <button type="submit" class="btn">Enregistrer les tarifs</button>
  </div>
</form>

<script>
/* Les accordéons ouverts le restent après l'enregistrement. */
(function () {
  var cles = 'tarifs-ouverts';
  var ouverts = [];
  try { ouverts = JSON.parse(sessionStorage.getItem(cles) || '[]'); } catch (e) {}
  var cats = document.querySelectorAll('.tarifs-cat');
  cats.forEach(function (d, i) {
    if (ouverts.indexOf(i) !== -1) d.open = true;
    d.addEventListener('toggle', function () {
      var l = [];
      cats.forEach(function (x, j) { if (x.open) l.push(j); });
      try { sessionStorage.setItem(cles, JSON.stringify(l)); } catch (e) {}
    });
  });
})();
</script>

<?php require __DIR__ . '/inc/pied.php'; ?>
