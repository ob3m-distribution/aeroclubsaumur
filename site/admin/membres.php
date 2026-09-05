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
$saisie = ['prenom' => '', 'nom' => '', 'email' => '', 'role' => 'lecture'];

/* ---- Actions ------------------------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $_SESSION['message_erreur'] = 'Session expirée, action non effectuée.';
        header('Location: /admin/membres.php', true, 303);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'role') {
            $cible = (int) ($_POST['id'] ?? 0);
            $role  = (string) ($_POST['role'] ?? '');
            if (!isset(roles_attribuables()[$role])) {
                throw new RuntimeException('Vous ne pouvez pas attribuer ce rôle.');
            }
            // Seul un super administrateur peut toucher a un autre super administrateur.
            $s = db()->prepare('SELECT role FROM membres WHERE id = ?');
            $s->execute([$cible]);
            if ($s->fetchColumn() === 'superadmin' && !est_superadmin()) {
                throw new RuntimeException('Seul un super administrateur peut modifier ce compte.');
            }
            if ($cible === (int) $moi['id']) {
                throw new RuntimeException('Vous ne pouvez pas modifier votre propre rôle.');
            }
            $s = db()->prepare('UPDATE membres SET role = ? WHERE id = ?');
            $s->execute([$role, $cible]);
            journaliser('membre.role', 'membre#' . $cible, $role);
            $_SESSION['message_succes'] = 'Rôle mis à jour.';
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
    <h2><?= count($membres) ?> membre<?= count($membres) > 1 ? 's' : '' ?></h2>
  </div>

  <div class="tableau">
    <table>
      <thead>
        <tr><th>Membre</th><th>Rôle</th><th>Dernière connexion</th><th>Accès</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($membres as $m): $estMoi = (int) $m['id'] === (int) $moi['id']; ?>
        <tr>
          <td>
            <strong><?= e($m['prenom'] . ' ' . $m['nom']) ?></strong>
            <?php if ($estMoi): ?><span class="etat etat--paye" style="margin-left:.35rem">vous</span><?php endif; ?>
            <br><span style="color:var(--gris-500);font-size:.8125rem"><?= e($m['email']) ?></span>
          </td>
          <td>
            <?php if ($estMoi): ?>
              <?= e(ROLES[$m['role']]['libelle'] ?? $m['role']) ?>
            <?php else: ?>
              <form method="post" style="display:flex;gap:.35rem;align-items:center">
                <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                <input type="hidden" name="action" value="role">
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <select name="role" style="padding:.3rem .5rem;font-size:.8125rem">
                  <?php foreach (roles_attribuables() as $k => $r): ?>
                    <option value="<?= e($k) ?>"<?= $m['role'] === $k ? ' selected' : '' ?>><?= e($r['libelle']) ?></option>
                  <?php endforeach; ?>
                </select>
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
          <?php if (!$r['droits']): ?><li class="muet">Espace adhérent uniquement (aucun accès au B.O.)</li><?php endif; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="aide" style="margin:.9rem 0 0">
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
