<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/biblio-adherents.php';
require_once __DIR__ . '/../inc/mail.php';
exiger_droit('membres.gerer');

/** Lien d'invitation / de réinitialisation (première connexion ou oubli). */
function lien_mot_de_passe(int $membreId, int $heures): string
{
    $token = creer_token_reset($membreId, $heures);
    return 'https://' . ($_SERVER['HTTP_HOST'] ?? 'dev.aeroclub-saumur.fr')
         . '/reinitialiser-mot-de-passe?token=' . $token;
}

$moi = membre_connecte();
$erreurs = [];
$saisie = ['prenom' => '', 'nom' => '', 'email' => '', 'role' => 'adherent'];

/* ---- Actions ------------------------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $_SESSION['message_erreur'] = 'Session expirée, action non effectuée.';
        header('Location: /admin/membres.php', true, 303);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'roles') {
            $cible = (int) ($_POST['id'] ?? 0);
            $s = db()->prepare('SELECT role FROM membres WHERE id = ?');
            $s->execute([$cible]);
            $roleActuel = $s->fetchColumn();
            if ($roleActuel === false) {
                throw new RuntimeException('Membre introuvable.');
            }
            // Seul un super administrateur peut toucher a un autre super administrateur.
            if ($roleActuel === 'superadmin' && !est_superadmin()) {
                throw new RuntimeException('Seul un super administrateur peut modifier ce compte.');
            }
            $choisis = array_map('strval', (array) ($_POST['roles'] ?? []));
            definir_roles_membre($cible, $choisis);

            // Super administrateur : reservé aux super administrateurs, et
            // jamais sur son propre compte (on ne se retire pas la main).
            if (est_superadmin() && $cible !== (int) $moi['id']) {
                $super = in_array('superadmin', $choisis, true);
                if ($super !== ($roleActuel === 'superadmin')) {
                    db()->prepare('UPDATE membres SET role = ? WHERE id = ?')
                        ->execute([$super ? 'superadmin' : 'adherent', $cible]);
                }
            }
            journaliser('membre.roles', 'membre#' . $cible, implode(',', $choisis));
            $_SESSION['message_succes'] = 'Rôles mis à jour.';
        }

        elseif ($action === 'activer' || $action === 'desactiver') {
            $cible = (int) ($_POST['id'] ?? 0);
            if ($cible === (int) $moi['id']) {
                throw new RuntimeException('Vous ne pouvez pas désactiver votre propre compte.');
            }
            $actifNouveau = $action === 'activer' ? 1 : 0;
            $s = db()->prepare('UPDATE membres SET actif = ?, echecs_connexion = 0, bloque_jusqua = NULL WHERE id = ?');
            $s->execute([$actifNouveau, $cible]);
            journaliser('membre.' . $action, 'membre#' . $cible);
            $_SESSION['message_succes'] = $actifNouveau ? 'Accès rétabli.' : 'Accès suspendu.';
        }

        elseif ($action === 'inviter') {
            $cible = (int) ($_POST['id'] ?? 0);
            $s = db()->prepare('SELECT prenom, email, derniere_connexion FROM membres WHERE id = ?');
            $s->execute([$cible]);
            $m = $s->fetch();
            if (!$m) {
                throw new RuntimeException('Membre introuvable.');
            }
            $invitation = empty($m['derniere_connexion']); // jamais connecté = invitation
            $lien = lien_mot_de_passe($cible, 72);
            $envoi = email_lien_mot_de_passe($m['email'], (string) $m['prenom'], $lien, $invitation);
            journaliser('membre.inviter', 'membre#' . $cible, $m['email']);
            $_SESSION['message_succes'] = $envoi
                ? 'Lien envoyé à ' . $m['email'] . ' (valable 72 h).'
                : 'L’e-mail n’a pas pu être envoyé. Réessayez plus tard.';
        }

        elseif ($action === 'inviter_groupe') {
            // Invitation (ou lien mot de passe) à plusieurs membres d'un coup.
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));
            if (!$ids) {
                throw new RuntimeException('Aucun membre sélectionné.');
            }
            set_time_limit(600);
            $lire = db()->prepare('SELECT id, prenom, email, derniere_connexion FROM membres WHERE id = ?');
            $ok = 0;
            $echecs = [];
            foreach ($ids as $cible) {
                $lire->execute([$cible]);
                $m = $lire->fetch();
                if (!$m) continue;
                $lien = lien_mot_de_passe((int) $m['id'], 72);
                if (email_lien_mot_de_passe($m['email'], (string) $m['prenom'], $lien, empty($m['derniere_connexion']))) {
                    $ok++;
                } else {
                    $echecs[] = $m['email'];
                }
            }
            journaliser('membre.inviter_groupe', 'membres', $ok . ' envoyé(s) / ' . count($ids));
            $_SESSION['message_succes'] = $ok . ' e-mail' . ($ok > 1 ? 's' : '') . ' d’invitation envoyé' . ($ok > 1 ? 's' : '')
                . ' (liens valables 72 h).';
            if ($echecs) {
                $_SESSION['message_erreur'] = 'Échec d’envoi pour : ' . implode(', ', $echecs) . '.';
            }
        }

        header('Location: /admin/membres.php', true, 303);
        exit;
    } catch (Throwable $ex) {
        $_SESSION['message_erreur'] = $ex->getMessage();
        header('Location: /admin/membres.php', true, 303);
        exit;
    }
}

$membres = db()->query('SELECT * FROM membres ORDER BY actif DESC, nom ASC, prenom ASC')->fetchAll();

$titre = 'Membres & accès';
$actif = 'membres';
require __DIR__ . '/inc/entete.php';
?>

<div class="bloc">
  <div class="bloc__titre">
    <h2><span id="nb-visibles"><?= count($membres) ?></span> membre<?= count($membres) > 1 ? 's' : '' ?>
      <span class="muet" id="nb-total" style="font-weight:400;font-size:.875rem"></span></h2>
  </div>

  <div class="membres-filtres">
    <div class="champ" style="min-width:16rem">
      <label for="f-recherche" class="visuellement-cache">Rechercher</label>
      <input type="search" id="f-recherche" placeholder="Rechercher un nom, un e-mail…">
    </div>
    <p class="aide" style="margin:.4rem 0 0">Trier : cliquez sur le titre d’une colonne. Filtrer : bouton ▾ à côté du titre
      (ex. Rôles → Bureau, Dernière connexion → jamais).</p>
  </div>

  <form method="post" id="invit-groupe" class="membres-selection">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
    <input type="hidden" name="action" value="inviter_groupe">
    <span><strong id="nb-selection">0</strong> sélectionné(s)</span>
    <button type="button" class="btn btn--contour btn--petit" id="tout-selectionner">Sélectionner tous les membres affichés</button>
    <button type="button" class="btn btn--contour btn--petit" id="rien-selectionner">Tout désélectionner</button>
    <button type="submit" class="btn btn--petit" id="envoyer-invitations" disabled
            data-confirmer="Envoyer l’e-mail d’invitation (lien pour choisir son mot de passe) aux membres sélectionnés ?">
      Envoyer l’invitation par e-mail
    </button>
  </form>

  <div class="tableau">
    <table id="table-membres">
      <thead>
        <tr>
          <th class="col-case"><input type="checkbox" id="case-toutes" aria-label="Sélectionner tous les membres affichés"></th>
          <th>Membre</th><th>Rôles</th><th>Dernière connexion</th><th>Accès</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($membres as $m): $estMoi = (int) $m['id'] === (int) $moi['id']; ?>
        <tr data-texte="<?= e(mb_strtolower($m['prenom'] . ' ' . $m['nom'] . ' ' . $m['email'])) ?>">
          <td class="col-case"><input type="checkbox" name="ids[]" value="<?= (int) $m['id'] ?>" form="invit-groupe"
                                      aria-label="Sélectionner <?= e($m['prenom'] . ' ' . $m['nom']) ?>"></td>
          <td>
            <strong><?= e($m['prenom'] . ' ' . $m['nom']) ?></strong>
            <?php if ($estMoi): ?><span class="etat etat--paye" style="margin-left:.35rem">vous</span><?php endif; ?>
            <br><span style="color:var(--gris-500);font-size:.8125rem"><?= e($m['email']) ?></span>
          </td>
          <?php $sesRoles = roles_du_membre($m); ?>
          <td data-valeurs="<?= e(implode('|', array_map(fn($r) => ROLES[$r]['libelle'], $sesRoles))) ?>">
            <?php
                  $verrou = $m['role'] === 'superadmin' && !est_superadmin(); ?>
            <?php if ($verrou): ?>
              <?= e(libelle_roles($m)) ?>
            <?php else: ?>
              <form method="post" class="roles-cases">
                <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                <input type="hidden" name="action" value="roles">
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <?php foreach (roles_attribuables() as $k => $r):
                    // On ne retire pas son propre statut de super administrateur.
                    $fige = $k === 'superadmin' && $estMoi; ?>
                  <label<?= $k === 'superadmin' ? ' class="roles-cases__super"' : '' ?>>
                    <input type="checkbox" name="roles[]" value="<?= e($k) ?>"
                      <?= in_array($k, $sesRoles, true) ? ' checked' : '' ?><?= $fige ? ' disabled' : '' ?>>
                    <?= e($k === 'superadmin' ? 'Super admin' : $r['libelle']) ?>
                  </label>
                <?php endforeach; ?>
                <button type="submit" class="btn btn--contour btn--petit">OK</button>
              </form>
            <?php endif; ?>
          </td>
          <td style="font-size:.8125rem">
            <?= $m['derniere_connexion']
                ? e(date('d/m/Y à H:i', strtotime((string) $m['derniere_connexion'])))
                : '<span style="color:var(--gris-500)">jamais</span>' ?>
          </td>
          <td>
            <?php if ((int) $m['actif'] === 1): ?>
              <span class="etat etat--paye">Actif</span>
            <?php else: ?>
              <span class="etat etat--annule">Suspendu</span>
            <?php endif; ?>
          </td>
          <td class="nombre">
            <?php if (!$estMoi): ?>
              <div class="actions" style="justify-content:flex-end">
                <form method="post">
                  <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                  <input type="hidden" name="action" value="<?= (int) $m['actif'] === 1 ? 'desactiver' : 'activer' ?>">
                  <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                  <button type="submit" class="btn btn--contour btn--petit"
                          data-confirmer="<?= (int) $m['actif'] === 1
                            ? 'Suspendre l’accès de ce membre ?' : 'Rétablir l’accès de ce membre ?' ?>">
                    <?= (int) $m['actif'] === 1 ? 'Suspendre' : 'Rétablir' ?>
                  </button>
                </form>
                <form method="post">
                  <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                  <input type="hidden" name="action" value="inviter">
                  <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                  <button type="submit" class="btn btn--contour btn--petit"
                          data-confirmer="Envoyer à ce membre un e-mail avec un lien pour choisir son mot de passe ?">
                    <?= empty($m['derniere_connexion']) ? 'Renvoyer l’invitation' : 'Lien mot de passe' ?>
                  </button>
                </form>
              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
/* Recherche et sélection pour l'envoi groupé. Le tri et les filtres par
   colonne (bouton ▾) viennent de admin-tables.js, commun au back-office. */
(function () {
  var table = document.getElementById('table-membres');
  var tbody = table.tBodies[0];
  var lignes = [].slice.call(tbody.rows);
  var recherche = document.getElementById('f-recherche');
  function visible(tr) { return tr.style.display !== 'none'; }
  function cases() { return tbody.querySelectorAll('input[name="ids[]"]'); }

  function compter() {
    var n = lignes.filter(visible).length;
    document.getElementById('nb-visibles').textContent = n;
    document.getElementById('nb-total').textContent = n < lignes.length ? '(sur ' + lignes.length + ')' : '';
    majSelection();
  }
  function majSelection() {
    var nb = [].filter.call(cases(), function (c) { return c.checked; }).length;
    document.getElementById('nb-selection').textContent = nb;
    document.getElementById('envoyer-invitations').disabled = nb === 0;
    var vis = lignes.filter(visible);
    document.getElementById('case-toutes').checked = vis.length > 0 && vis.every(function (tr) {
      return tr.querySelector('input[name="ids[]"]').checked;
    });
  }
  function selectionnerVisibles(etat) {
    lignes.forEach(function (tr) { if (visible(tr)) tr.querySelector('input[name="ids[]"]').checked = etat; });
    majSelection();
  }

  recherche.addEventListener('input', function () {
    var q = recherche.value.trim().toLowerCase();
    lignes.forEach(function (tr) {
      if (q && tr.dataset.texte.indexOf(q) === -1) tr.setAttribute('data-cache', '');
      else tr.removeAttribute('data-cache');
    });
    table.dispatchEvent(new CustomEvent('tableau:filtrer'));
    compter();   // au cas où admin-tables.js ne serait pas chargé
  });
  table.addEventListener('tableau:filtre-applique', compter);
  tbody.addEventListener('change', function (ev) { if (ev.target.name === 'ids[]') majSelection(); });
  document.getElementById('case-toutes').addEventListener('change', function () { selectionnerVisibles(this.checked); });
  document.getElementById('tout-selectionner').addEventListener('click', function () { selectionnerVisibles(true); });
  document.getElementById('rien-selectionner').addEventListener('click', function () {
    [].forEach.call(cases(), function (c) { c.checked = false; }); majSelection();
  });
  compter();
})();
</script>

<div class="bloc">
  <h2>Ce que permet chaque rôle</h2>
  <div class="roles-grille">
    <?php foreach (roles_attribuables() as $k => $r): ?>
      <div class="role-carte">
        <p class="role-carte__nom"><?= e($r['libelle']) ?></p>
        <p class="role-carte__desc"><?= e($r['description']) ?></p>
        <ul>
          <?php foreach ($r['droits'] as $d): ?>
            <li><?= e(AUTORISATIONS[$d] ?? $d) ?></li>
          <?php endforeach; ?>
          <?php if (!$r['droits']): ?><li class="muet">Aucun accès au back-office</li><?php endif; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="aide" style="margin:.9rem 0 0">
    Un membre peut cumuler plusieurs rôles : il obtient la somme de leurs accès.
    Chaque rôle sert aussi de liste de destinataires pour les newsletters.
  </p>
  <p class="aide" style="margin:.5rem 0 0">
    Un membre suspendu conserve son compte mais ne peut plus se connecter.
    Sa session en cours est invalidée immédiatement.
  </p>
</div>

<div class="bloc">
  <h2>Accès bibliothèque</h2>
  <p class="aide" style="margin:0 0 .75rem">
    La gestion des dossiers visibles par chaque adhérent est centralisée dans un tableau unique
    (adhérents en lignes, dossiers en colonnes).
  </p>
  <a class="btn" href="/admin/acces-dossiers.php">Ouvrir le tableau des accès →</a>
</div>

<?php require __DIR__ . '/inc/pied.php'; ?>
