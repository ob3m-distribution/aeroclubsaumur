<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/mailing.php';
exiger_droit('mailing.gerer');

$moi   = membre_connecte();
$annee = COTISATION_ANNEE;

$saisie = ['sujet' => '', 'corps' => ''];
$f = ['role' => 'tous', 'adhesion' => 'tous', 'paiement' => 'tous'];
$erreur = null;
$apercu = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $_SESSION['message_erreur'] = 'Session expirée, action non effectuée.';
        header('Location: /admin/mailing.php', true, 303);
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    $f = mailing_filtres($_POST);
    $saisie['sujet'] = trim((string) ($_POST['sujet'] ?? ''));
    $saisie['corps'] = trim((string) ($_POST['corps'] ?? ''));

    if ($action === 'envoyer') {
        $dest = mailing_destinataires($f, $annee);
        if ($saisie['sujet'] === '' || $saisie['corps'] === '') {
            $erreur = 'Sujet et message sont obligatoires.';
            $apercu = true;
        } elseif (!$dest) {
            $erreur = 'Aucun destinataire ne correspond à ces critères.';
            $apercu = true;
        } else {
            [$nb, $envoyes] = mailing_envoyer($saisie['sujet'], $saisie['corps'], $dest);
            db()->prepare(
                'INSERT INTO mailings (sujet, corps, filtre_role, filtre_adhesion, filtre_paiement,
                                       nb_destinataires, nb_envoyes, envoye_par)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([
                $saisie['sujet'], $saisie['corps'], $f['role'], $f['adhesion'], $f['paiement'],
                $nb, $envoyes, (int) $moi['id'],
            ]);
            journaliser('mailing.envoye', 'campagne', $envoyes . '/' . $nb . ' destinataires');
            $_SESSION['message_succes'] = "Envoi terminé : $envoyes message(s) envoyé(s) sur $nb destinataire(s).";
            header('Location: /admin/mailing.php', true, 303);
            exit;
        }
    } else {
        $apercu = true; // action « apercu »
    }
}

$destinataires = mailing_destinataires($f, $annee);
$nbDest = count($destinataires);

$historique = db()->query(
    'SELECT ml.*, m.prenom, m.nom FROM mailings ml
     LEFT JOIN membres m ON m.id = ml.envoye_par
     ORDER BY ml.envoye_le DESC LIMIT 50'
)->fetchAll();

$titre = 'Newsletters';
$actif = 'mailing';
require __DIR__ . '/inc/entete.php';
?>

<div class="detail">

  <div class="bloc">
    <h2>Nouvel envoi</h2>
    <?php if ($erreur): ?><div class="message message--erreur" role="alert"><?= e($erreur) ?></div><?php endif; ?>

    <form method="post" data-unique>
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

      <div class="champs champs--trio">
        <?php foreach (MAILING_FILTRES as $cle => $choix): ?>
          <div class="champ">
            <label for="f-<?= e($cle) ?>"><?= e(ucfirst($cle === 'role' ? 'destinataires' : $cle)) ?></label>
            <select id="f-<?= e($cle) ?>" name="<?= e($cle) ?>">
              <?php foreach ($choix as $k => $lib): ?>
                <option value="<?= e($k) ?>"<?= ($f[$cle] ?? 'tous') === $k ? ' selected' : '' ?>><?= e($lib) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endforeach; ?>
      </div>

      <p class="mailing-compte">
        <button type="submit" name="action" value="apercu" class="btn btn--contour btn--petit">Recalculer</button>
        <strong><?= $nbDest ?></strong> destinataire<?= $nbDest > 1 ? 's' : '' ?> — <?= e(mailing_filtres_libelle($f)) ?>
      </p>

      <div class="champ">
        <label for="sujet">Sujet</label>
        <input type="text" id="sujet" name="sujet" value="<?= e($saisie['sujet']) ?>" maxlength="255" required>
      </div>
      <div class="champ">
        <label for="corps">Message</label>
        <textarea id="corps" name="corps" rows="10" required><?= e($saisie['corps']) ?></textarea>
        <p class="aide">Astuce : écrivez <code>{prenom}</code> pour personnaliser (« Bonjour {prenom}, … »).</p>
      </div>

      <div class="actions" style="margin-top:1rem">
        <button type="submit" name="action" value="envoyer" class="btn"
                data-confirmer="Envoyer ce message à <?= $nbDest ?> destinataire(s) ? Cette action est définitive.">
          Envoyer à <?= $nbDest ?> destinataire<?= $nbDest > 1 ? 's' : '' ?>
        </button>
      </div>
    </form>

    <?php if ($apercu && $destinataires): ?>
      <details style="margin-top:1rem">
        <summary class="btn btn--contour btn--petit" style="list-style:none">Voir la liste des <?= $nbDest ?> destinataires</summary>
        <ul class="mailing-liste">
          <?php foreach ($destinataires as $d): ?>
            <li><?= e(trim($d['prenom'] . ' ' . $d['nom'])) ?> <span class="muet">&lt;<?= e($d['email']) ?>&gt;</span></li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endif; ?>
  </div>

  <div class="bloc">
    <h2>Historique des envois</h2>
    <?php if (!$historique): ?>
      <p class="vide">Aucun envoi pour l’instant.</p>
    <?php else: ?>
      <div class="news-histo">
        <?php foreach ($historique as $h):
            $cible = mailing_filtres_libelle([
                'role' => $h['filtre_role'], 'adhesion' => $h['filtre_adhesion'], 'paiement' => $h['filtre_paiement'],
            ]);
            $par = trim(($h['prenom'] ?? '') . ' ' . ($h['nom'] ?? '')); ?>
          <details class="news-item">
            <summary>
              <span class="news-item__date"><?= e(date('d/m/Y', strtotime((string) $h['envoye_le']))) ?></span>
              <span class="news-item__sujet"><?= e($h['sujet']) ?></span>
              <span class="news-item__meta"><?= (int) $h['nb_envoyes'] ?>/<?= (int) $h['nb_destinataires'] ?> destinataires</span>
              <span class="news-item__chev" aria-hidden="true">▾</span>
            </summary>
            <div class="news-item__corps">
              <p class="muet" style="margin:0 0 .6rem;font-size:.78rem">
                Envoyé le <?= e(date('d/m/Y à H:i', strtotime((string) $h['envoye_le']))) ?>
                · Cible : <?= e($cible) ?><?= $par ? ' · Par ' . e($par) : '' ?>
              </p>
              <div class="news-item__texte"><?= nl2br(e($h['corps'])) ?></div>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require __DIR__ . '/inc/pied.php'; ?>
