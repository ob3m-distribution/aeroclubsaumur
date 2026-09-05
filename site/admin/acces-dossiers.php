<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/biblio-adherents.php';
exiger_droit('membres.gerer');

$dossiers = biblio_sections_top();          // [id => nom]

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $_SESSION['message_erreur'] = 'Session expirée, rien enregistré.';
    } elseif (($_POST['action'] ?? '') === 'roles') {
        foreach (array_map('strval', (array) ($_POST['roles'] ?? [])) as $role) {
            $choix = array_map('strval', (array) (($_POST['racces'][$role] ?? [])));
            definir_role_dossiers($role, $choix);
        }
        journaliser('acces_dossiers.roles');
        $_SESSION['message_succes'] = 'Accès par rôle enregistrés.';
    } else {
        $acces = (array) ($_POST['acces'] ?? []);
        foreach (array_map('intval', (array) ($_POST['membres'] ?? [])) as $mid) {
            definir_membre_dossiers($mid, array_map('strval', (array) ($acces[$mid] ?? [])));
        }
        journaliser('acces_dossiers.membres');
        $_SESSION['message_succes'] = 'Accès par membre enregistrés.';
    }
    header('Location: /admin/acces-dossiers.php', true, 303);
    exit;
}

// Rôles concernés par les accès documents : ceux qui ne voient pas déjà tout.
$rolesRestreints = array_filter(ROLES, fn($r) => empty($r['droits']));

$membres = db()->query(
    "SELECT id, prenom, nom, email, role FROM membres WHERE role = 'adherent' ORDER BY nom, prenom"
)->fetchAll();

$titre = 'Accès bibliothèque';
$actif = 'acces';
require __DIR__ . '/inc/entete.php';

/** Rend une matrice (lignes × dossiers) de cases à cocher. */
$matrice = function (array $lignes, callable $idFn, callable $nomFn, callable $sousFn, callable $cocheFn,
                     string $champLignes, string $champAcces) use ($dossiers) {
    ?>
    <div class="tableau tableau--matrice">
      <table>
        <thead>
          <tr>
            <th class="col-membre">&nbsp;</th>
            <?php foreach ($dossiers as $sid => $nom): ?>
              <th class="col-dossier"><span><?= e($nom) ?></span></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($lignes as $l): $cle = $idFn($l); ?>
          <tr>
            <td class="col-membre">
              <input type="hidden" name="<?= e($champLignes) ?>[]" value="<?= e((string) $cle) ?>">
              <strong><?= e($nomFn($l)) ?></strong>
              <?php $sous = $sousFn($l); if ($sous !== ''): ?><br><span class="muet" style="font-size:.75rem"><?= e($sous) ?></span><?php endif; ?>
            </td>
            <?php foreach ($dossiers as $sid => $nom): ?>
              <td class="cell-check">
                <input type="checkbox" name="<?= e($champAcces) ?>[<?= e((string) $cle) ?>][]" value="<?= (int) $sid ?>"<?= $cocheFn($l, (string) $sid) ? ' checked' : '' ?>>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
};
?>

<?php if (!$dossiers): ?>
  <div class="bloc"><p class="vide">Aucun dossier dans la bibliothèque pour l’instant.</p></div>
<?php else: ?>

<div class="bloc">
  <div class="bloc__titre"><h2>Accès par rôle</h2></div>
  <p class="aide" style="margin:0 0 1rem">
    Définissez les dossiers visibles par défaut pour chaque rôle. Ces réglages s’appliquent
    automatiquement à tous les membres du rôle, sauf réglage individuel ci-dessous. (Les rôles
    de l’équipe qui ont accès au back-office voient toujours tout et n’apparaissent pas ici.)
  </p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
    <input type="hidden" name="action" value="roles">
    <?php $matrice(
        array_keys($rolesRestreints),
        fn($role) => $role,
        fn($role) => ROLES[$role]['libelle'] ?? $role,
        fn($role) => 'rôle',
        function ($role, $sid) { $a = role_dossiers_autorises((string) $role); return $a === null || in_array($sid, $a, true); },
        'roles', 'racces'
    ); ?>
    <div class="actions" style="margin-top:1rem"><button type="submit" class="btn">Enregistrer les accès par rôle</button></div>
  </form>
</div>

<div class="bloc">
  <div class="bloc__titre"><h2>Accès par membre</h2></div>
  <p class="aide" style="margin:0 0 1rem">
    Le détail par adhérent. Les cases reflètent l’accès effectif (celui de son rôle par défaut) :
    ajoutez ou retirez des dossiers pour affiner au cas par cas.
  </p>
  <?php if (!$membres): ?>
    <p class="vide">Aucun adhérent pour l’instant.</p>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <input type="hidden" name="action" value="membres">
      <?php $matrice(
          $membres,
          fn($m) => (int) $m['id'],
          fn($m) => trim($m['prenom'] . ' ' . $m['nom']),
          fn($m) => (string) $m['email'],
          function ($m, $sid) { $a = dossiers_effectifs($m); return $a === null || in_array($sid, $a, true); },
          'membres', 'acces'
      ); ?>
      <div class="actions" style="margin-top:1rem"><button type="submit" class="btn">Enregistrer les accès par membre</button></div>
    </form>
  <?php endif; ?>
</div>

<?php endif; ?>

<script>
/* Clic sur l'en-tête d'un dossier = cocher / décocher toute la colonne (par tableau). */
document.querySelectorAll('.tableau--matrice').forEach(function (tbl) {
  tbl.querySelectorAll('th.col-dossier').forEach(function (th, i) {
    th.style.cursor = 'pointer'; th.title = 'Tout cocher / décocher cette colonne';
    th.addEventListener('click', function () {
      var boxes = tbl.querySelectorAll('tbody tr td:nth-child(' + (i + 2) + ') input[type=checkbox]');
      var tout = [].every.call(boxes, function (b) { return b.checked; });
      boxes.forEach(function (b) { b.checked = !tout; });
    });
  });
});
</script>

<?php require __DIR__ . '/inc/pied.php'; ?>
