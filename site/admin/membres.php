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
        <tr><th>Membre</th><th>Rôles</th><th>Dernière connexion</th><th>Accès</th><th></th></tr>
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
            <?php $sesRoles = roles_du_membre($m);
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
