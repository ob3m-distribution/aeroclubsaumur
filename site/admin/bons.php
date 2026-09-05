<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/bon-cadeau.php';
exiger_droit('bons.voir');

/* --- Enregistrement d'une ligne (date de réalisation / pilote / bénéficiaire) --- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    exiger_droit('bons.gerer');
    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $_SESSION['message_erreur'] = 'Session expirée, modification non enregistrée.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        maj_bon_bo(
            $id,
            (string) ($_POST['date_realisation'] ?? ''),
            (string) ($_POST['pilote'] ?? ''),
            (string) ($_POST['offert_a'] ?? '')
        );
        journaliser('bon.maj_bo', 'bon#' . $id);
        $_SESSION['message_succes'] = 'Bon mis à jour.';
    }
    $params = ['statut' => $_GET['statut'] ?? '', 'q' => $_GET['q'] ?? ''];
    header('Location: /admin/bons.php?' . http_build_query(array_filter($params)) . '#bon-' . (int) ($_POST['id'] ?? 0), true, 303);
    exit;
}

$statut    = (string) ($_GET['statut'] ?? '');
$recherche = trim((string) ($_GET['q'] ?? ''));

[$clause, $args] = filtre_bons($statut, $recherche);
$sql = 'SELECT * FROM bons_cadeaux' . $clause . ' ORDER BY (paye_le IS NULL), paye_le DESC, cree_le DESC LIMIT 300';

$stmt = db()->prepare($sql);
$stmt->execute($args);
$bons = $stmt->fetchAll();

$peutGerer = peut('bons.gerer');
$titre = 'Bons cadeaux';
$actif = 'bons';
require __DIR__ . '/inc/entete.php';

?>

<form class="filtres" method="get">
  <div class="champ">
    <label for="q">Rechercher</label>
    <input type="search" id="q" name="q" value="<?= e($recherche) ?>"
           placeholder="N° de bon, nom, email, bénéficiaire…">
  </div>
  <div class="champ">
    <label for="statut">Statut</label>
    <select id="statut" name="statut">
      <option value="">Tous</option>
      <?php foreach (STATUTS_BON as $s => [$lib, $_]): ?>
        <option value="<?= e($s) ?>"<?= $statut === $s ? ' selected' : '' ?>><?= e($lib) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="actions">
    <button type="submit" class="btn">Filtrer</button>
    <?php if ($statut !== '' || $recherche !== ''): ?>
      <a class="btn btn--contour" href="/admin/bons.php">Réinitialiser</a>
    <?php endif; ?>
    <a class="btn btn--contour" href="/admin/export-bons.php?<?= e(http_build_query(['statut' => $statut, 'q' => $recherche])) ?>">
      Exporter en CSV
    </a>
  </div>
</form>

<div class="bloc">
  <div class="bloc__titre">
    <h2><?= count($bons) ?> bon<?= count($bons) > 1 ? 's' : '' ?></h2>
  </div>

  <?php if (!$bons): ?>
    <p class="vide">Aucun bon ne correspond à cette recherche.</p>
  <?php else: ?>

    <?php /* Formulaires de mise à jour, un par ligne : hors <table> (interdit dans <tr>),
             reliés aux champs de la ligne par l'attribut form="bonf-ID". */ ?>
    <?php if ($peutGerer): foreach ($bons as $b): if (!in_array($b['statut'], ['paye', 'utilise'], true)) continue; ?>
      <form id="bonf-<?= (int) $b['id'] ?>" method="post"
            action="/admin/bons.php?<?= e(http_build_query(['statut' => $statut, 'q' => $recherche])) ?>" hidden>
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
      </form>
    <?php endforeach; endif; ?>

    <div class="tableau tableau--bons">
      <table>
        <thead>
          <tr>
            <th>Date paiement</th>
            <th>Nom</th>
            <th>Prénom</th>
            <th>N° de bon</th>
            <th>Vol</th>
            <th>Date de réalisation</th>
            <th>Pilote</th>
            <th>Offert à</th>
            <th>Fin de validité</th>
            <th>Statut</th>
            <?php if ($peutGerer): ?><th></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($bons as $b):
            [$lib, $cls] = STATUTS_BON[$b['statut']] ?? [$b['statut'], 'expire'];
            $realisable = in_array($b['statut'], ['paye', 'utilise'], true);
            $ff = 'form="bonf-' . (int) $b['id'] . '"';
        ?>
          <tr id="bon-<?= (int) $b['id'] ?>">
            <td><?= $b['paye_le'] ? e(date('d/m/Y', strtotime((string) $b['paye_le']))) : '<span class="muet">—</span>' ?></td>
            <td><a href="/admin/bon.php?id=<?= (int) $b['id'] ?>"><?= e($b['acheteur_nom']) ?></a></td>
            <td><?= e($b['acheteur_prenom']) ?></td>
            <td class="code-bon">
              <?php if (!empty($b['numero_bon'])): ?><?= e($b['numero_bon']) ?>
              <?php else: ?><span class="muet"><?= e($b['reference']) ?></span><?php endif; ?>
            </td>
            <td style="font-size:.8125rem"><?= e(libelle_vol_bon($b)) ?></td>

            <?php if ($realisable && $peutGerer): ?>
              <td><input type="date" name="date_realisation" <?= $ff ?>
                         value="<?= e($b['date_realisation'] ?? '') ?>"></td>
              <td>
                <select name="pilote" <?= $ff ?>>
                  <option value="">—</option>
                  <?php foreach (PILOTES as $p): ?>
                    <option value="<?= e($p) ?>"<?= ($b['pilote'] ?? '') === $p ? ' selected' : '' ?>><?= e($p) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="text" name="offert_a" <?= $ff ?> style="min-width:9rem"
                         value="<?= e($b['offert_a'] ?? '') ?>" placeholder="Nom Prénom"></td>
            <?php else: ?>
              <td><?= $b['date_realisation'] ? e(date('d/m/Y', strtotime((string) $b['date_realisation']))) : '<span class="muet">—</span>' ?></td>
              <td><?= $b['pilote'] ? e($b['pilote']) : '<span class="muet">—</span>' ?></td>
              <td><?= $b['offert_a'] ? e($b['offert_a']) : '<span class="muet">—</span>' ?></td>
            <?php endif; ?>

            <td class="fin-validite" data-fin="<?= e($b['date_fin_validite'] ?? '') ?>">
              <?php if (!empty($b['date_fin_validite'])): ?>
                <?= e(date('d/m/Y', strtotime((string) $b['date_fin_validite']))) ?>
                <br><span class="rebours">…</span>
              <?php else: ?><span class="muet">—</span><?php endif; ?>
            </td>
            <td><span class="etat etat--<?= e($cls) ?>"><?= e($lib) ?></span></td>
            <?php if ($peutGerer): ?>
              <td class="nombre">
                <?php if ($realisable): ?>
                  <button type="submit" <?= $ff ?> class="btn btn--petit">Enregistrer</button>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
/* Compte à rebours de validité, calculé côté client à partir de data-fin (AAAA-MM-JJ). */
(function () {
  var auj = new Date(); auj.setHours(0, 0, 0, 0);
  document.querySelectorAll('.fin-validite').forEach(function (td) {
    var fin = td.getAttribute('data-fin');
    var el = td.querySelector('.rebours');
    if (!fin || !el) return;
    var d = new Date(fin + 'T00:00:00');
    var j = Math.round((d - auj) / 86400000);
    if (j < 0)       { el.textContent = 'expiré depuis ' + (-j) + ' j'; el.className = 'rebours rebours--rouge'; }
    else if (j === 0){ el.textContent = 'expire aujourd’hui';           el.className = 'rebours rebours--rouge'; }
    else             { el.textContent = 'reste ' + j + ' j';
                       el.className = 'rebours' + (j <= 30 ? ' rebours--rouge' : (j <= 90 ? ' rebours--orange' : '')); }
  });
})();
</script>

<?php require __DIR__ . '/inc/pied.php'; ?>
