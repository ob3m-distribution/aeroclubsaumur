<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/mail.php';
exiger_connexion();

const SUPPORT_EMAIL = 'ob3m.distribution@gmail.com';

$moi     = membre_connecte();
$gestion = peut('membres.gerer');           // le bureau voit et traite tous les tickets
$stTicket = ['ouvert' => ['Ouvert', 'attente'], 'en_cours' => ['En cours', 'planifie'], 'resolu' => ['Résolu', 'paye']];

/* ------------------------------------------------------------------ */
/*  Actions (POST)                                                     */
/* ------------------------------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $_SESSION['message_erreur'] = 'Session expirée, action annulée.';
        header('Location: /admin/support.php', true, 303);
        exit;
    }
    $action = $_POST['action'] ?? 'nouveau';

    /* --- Nouveau ticket ------------------------------------------- */
    if ($action === 'nouveau') {
        $sujet    = trim((string) ($_POST['sujet'] ?? ''));
        $message  = trim((string) ($_POST['message'] ?? ''));
        $priorite = in_array($_POST['priorite'] ?? '', ['basse', 'normale', 'haute'], true) ? $_POST['priorite'] : 'normale';

        $pieces = []; $tropGros = false; $mauvaisType = false; $total = 0;
        $okExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'pdf'];
        if (!empty($_FILES['pj']) && is_array($_FILES['pj']['name'])) {
            $n = count($_FILES['pj']['name']);
            for ($i = 0; $i < $n && $i < 6; $i++) {
                if (($_FILES['pj']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                $tmp = $_FILES['pj']['tmp_name'][$i];
                $nom = (string) $_FILES['pj']['name'][$i];
                $ext = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
                if (!in_array($ext, $okExt, true)) { $mauvaisType = true; continue; }
                if (!is_uploaded_file($tmp)) continue;
                $total += (int) filesize($tmp);
                if ($total > 12 * 1024 * 1024) { $tropGros = true; break; }
                $pieces[] = ['nom' => $nom,
                    'type' => $ext === 'pdf' ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext),
                    'data' => file_get_contents($tmp)];
            }
        }

        if ($sujet === '' || $message === '') {
            $_SESSION['message_erreur'] = 'Merci d’indiquer un sujet et un message.';
        } elseif ($tropGros) {
            $_SESSION['message_erreur'] = 'Les pièces jointes dépassent 12 Mo au total.';
        } elseif ($mauvaisType) {
            $_SESSION['message_erreur'] = 'Pièces acceptées : images et PDF uniquement.';
        } else {
            $auteur = trim($moi['prenom'] . ' ' . $moi['nom']);
            $role   = ROLES[$moi['role']]['libelle'] ?? $moi['role'];
            db()->prepare('INSERT INTO tickets (membre_id, membre_nom, membre_email, sujet, priorite, message, nb_pieces)
                           VALUES (?,?,?,?,?,?,?)')
                ->execute([(int) $moi['id'], $auteur, $moi['email'], mb_substr($sujet, 0, 180), $priorite,
                           mb_substr($message, 0, 5000), count($pieces)]);
            $tid = (int) db()->lastInsertId();

            $lien = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'dev.aeroclub-saumur.fr') . '/admin/support.php?ticket=' . $tid;
            $sujetMail = sprintf('[Support Saumur #%d] %s — %s', $tid, ucfirst($priorite), $sujet);
            $corps = "Nouveau ticket de support — Saumur Air Club\n\n"
                . "Ticket n° : {$tid}\nDe        : {$auteur} <{$moi['email']}> ({$role})\n"
                . "Priorité  : " . ucfirst($priorite) . "\nSujet     : {$sujet}\n"
                . "Date      : " . date('d/m/Y à H:i') . "\n" . str_repeat('—', 40) . "\n\n" . $message . "\n\n"
                . str_repeat('—', 40) . "\n" . count($pieces) . " pièce(s) jointe(s).\n\n"
                . "→ Répondez directement à cet e-mail pour écrire à {$auteur}, ou traitez le ticket ici :\n{$lien}";

            $envoi = $pieces
                ? envoyer_email_pj(SUPPORT_EMAIL, $sujetMail, $corps, $pieces, (string) $moi['email'])
                : envoyer_email(SUPPORT_EMAIL, $sujetMail, $corps, (string) $moi['email']);

            journaliser('ticket.cree', 'ticket#' . $tid, $sujet);
            $_SESSION['message_succes'] = $envoi
                ? 'Votre ticket n° ' . $tid . ' a bien été envoyé. Nous revenons vers vous par e-mail.'
                : 'Ticket enregistré (n° ' . $tid . '). Il est bien pris en compte.';
        }
        header('Location: /admin/support.php', true, 303);
        exit;
    }

    /* --- Réponse dans un ticket ----------------------------------- */
    if ($action === 'repondre' || $action === 'resoudre' || $action === 'rouvrir') {
        $tid = (int) ($_POST['ticket'] ?? 0);
        $st  = db()->prepare('SELECT * FROM tickets WHERE id = ?');
        $st->execute([$tid]);
        $ticket = $st->fetch();
        if (!$ticket) { $_SESSION['message_erreur'] = 'Ticket introuvable.'; header('Location: /admin/support.php', true, 303); exit; }
        // Un membre ne peut agir que sur son propre ticket ; le bureau sur tous.
        if (!$gestion && (int) $ticket['membre_id'] !== (int) $moi['id']) {
            $_SESSION['message_erreur'] = 'Accès refusé.'; header('Location: /admin/support.php', true, 303); exit;
        }
        $retour = '/admin/support.php?ticket=' . $tid;

        if ($action === 'resoudre' && $gestion) {
            db()->prepare('UPDATE tickets SET statut = ? WHERE id = ?')->execute(['resolu', $tid]);
            journaliser('ticket.resolu', 'ticket#' . $tid);
            $_SESSION['message_succes'] = 'Ticket n° ' . $tid . ' marqué comme résolu.';
            header('Location: ' . $retour, true, 303); exit;
        }
        if ($action === 'rouvrir' && $gestion) {
            db()->prepare('UPDATE tickets SET statut = ? WHERE id = ?')->execute(['en_cours', $tid]);
            $_SESSION['message_succes'] = 'Ticket n° ' . $tid . ' rouvert.';
            header('Location: ' . $retour, true, 303); exit;
        }

        // Réponse
        $corps = trim((string) ($_POST['reponse'] ?? ''));
        if ($corps === '') {
            $_SESSION['message_erreur'] = 'Votre réponse est vide.';
            header('Location: ' . $retour, true, 303); exit;
        }
        $auteur = trim($moi['prenom'] . ' ' . $moi['nom']);
        $cote   = $gestion ? 'bureau' : 'membre';
        db()->prepare('INSERT INTO ticket_reponses (ticket_id, auteur_id, auteur_nom, cote, corps)
                       VALUES (?,?,?,?,?)')
            ->execute([$tid, (int) $moi['id'], $auteur, $cote, mb_substr($corps, 0, 8000)]);
        db()->prepare('UPDATE tickets SET statut = ? WHERE id = ?')
            ->execute([$cote === 'bureau' ? 'en_cours' : 'ouvert', $tid]);
        journaliser('ticket.reponse', 'ticket#' . $tid);

        if ($cote === 'bureau') {
            // Alerte au membre : le bureau a répondu.
            $dest  = (string) $ticket['membre_email'];
            $sujet = sprintf('[Support Saumur #%d] Réponse du club — %s', $tid, $ticket['sujet']);
            $htmlCorps =
                '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;">Bonjour <strong>' . e($ticket['membre_nom']) . '</strong>,<br>'
                . 'le club vient de répondre à votre demande <strong>« ' . e($ticket['sujet']) . ' »</strong> :</p>'
                . '<div style="border-left:3px solid #B08D2C;background:#f8f9f9;padding:12px 16px;margin:0 0 16px;font-size:14px;line-height:1.6;white-space:pre-wrap;">'
                . e($corps) . '</div>'
                . '<p style="margin:0;font-size:14px;color:#55595b;">Pour poursuivre l’échange, répondez simplement à cet e-mail.</p>';
            $texte = "Bonjour {$ticket['membre_nom']},\n\nLe club a répondu à votre demande « {$ticket['sujet']} » :\n\n"
                . str_repeat('—', 40) . "\n" . $corps . "\n" . str_repeat('—', 40)
                . "\n\nPour poursuivre l’échange, répondez simplement à cet e-mail.\n\nLe Saumur Air Club";
            envoyer_email_html_pj($dest, $sujet, email_gabarit('Réponse à votre demande', $htmlCorps), $texte, [], SUPPORT_EMAIL);
            $_SESSION['message_succes'] = 'Réponse envoyée au membre (' . $dest . ').';
        } else {
            // Le membre relance : alerte au bureau.
            $sujet = sprintf('[Support Saumur #%d] Nouvelle réponse — %s', $tid, $ticket['sujet']);
            $corpsMail = "{$auteur} a ajouté une réponse au ticket n° {$tid} « {$ticket['sujet']} » :\n\n"
                . $corps . "\n\n→ Répondez directement à cet e-mail pour lui écrire, ou ouvrez le ticket :\n"
                . 'https://' . ($_SERVER['HTTP_HOST'] ?? 'dev.aeroclub-saumur.fr') . '/admin/support.php?ticket=' . $tid;
            envoyer_email(SUPPORT_EMAIL, $sujet, $corpsMail, (string) $moi['email']);
            $_SESSION['message_succes'] = 'Votre réponse a bien été transmise.';
        }
        header('Location: ' . $retour, true, 303);
        exit;
    }
    header('Location: /admin/support.php', true, 303);
    exit;
}

/* ------------------------------------------------------------------ */
/*  Vue détail d'un ticket                                             */
/* ------------------------------------------------------------------ */
$ticketVu = null;
if (isset($_GET['ticket'])) {
    $st = db()->prepare('SELECT * FROM tickets WHERE id = ?');
    $st->execute([(int) $_GET['ticket']]);
    $ticketVu = $st->fetch();
    if ($ticketVu && !$gestion && (int) $ticketVu['membre_id'] !== (int) $moi['id']) $ticketVu = null;
}

$titre = 'Support';
$actif = 'support';
require __DIR__ . '/inc/entete.php';

if ($ticketVu):
    $rep = db()->prepare('SELECT * FROM ticket_reponses WHERE ticket_id = ? ORDER BY cree_le ASC');
    $rep->execute([(int) $ticketVu['id']]);
    $rep = $rep->fetchAll();
    [$lib, $cls] = $stTicket[$ticketVu['statut']] ?? [$ticketVu['statut'], 'attente'];
?>
  <p style="margin:0 0 1rem"><a href="/admin/support.php">← Retour au support</a></p>
  <div class="bloc">
    <div style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;justify-content:space-between">
      <h2 style="margin:0">Ticket #<?= (int) $ticketVu['id'] ?> — <?= e($ticketVu['sujet']) ?></h2>
      <span class="etat etat--<?= e($cls) ?>"><?= e($lib) ?></span>
    </div>
    <p class="aide" style="margin:.4rem 0 0">
      De <strong><?= e($ticketVu['membre_nom']) ?></strong> &lt;<?= e($ticketVu['membre_email']) ?>&gt;
      · priorité <?= e($ticketVu['priorite']) ?>
      · <?= e(date('d/m/Y à H:i', strtotime((string) $ticketVu['cree_le']))) ?>
      <?php if ((int) $ticketVu['nb_pieces'] > 0): ?> · <?= (int) $ticketVu['nb_pieces'] ?> pièce(s) jointe(s) (dans l’e-mail d’alerte)<?php endif; ?>
    </p>
  </div>

  <div class="fil-tickets">
    <div class="bulle bulle--membre">
      <div class="bulle__meta"><?= e($ticketVu['membre_nom']) ?> · <?= e(date('d/m à H:i', strtotime((string) $ticketVu['cree_le']))) ?></div>
      <div class="bulle__txt"><?= nl2br(e($ticketVu['message'])) ?></div>
    </div>
    <?php foreach ($rep as $r): ?>
      <div class="bulle bulle--<?= $r['cote'] === 'bureau' ? 'bureau' : 'membre' ?>">
        <div class="bulle__meta"><?= $r['cote'] === 'bureau' ? 'Club' : e($r['auteur_nom']) ?> · <?= e(date('d/m à H:i', strtotime((string) $r['cree_le']))) ?></div>
        <div class="bulle__txt"><?= nl2br(e($r['corps'])) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($ticketVu['statut'] !== 'resolu' || $gestion): ?>
  <div class="bloc" style="margin-top:1rem">
    <h3><?= $gestion ? 'Répondre au membre' : 'Ajouter une réponse' ?></h3>
    <?php if ($gestion): ?>
      <p class="aide" style="margin:0 0 .6rem">Votre réponse est envoyée par e-mail au membre. Il pourra vous répondre en retour (l’échange revient sur votre boîte).</p>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <input type="hidden" name="action" value="repondre">
      <input type="hidden" name="ticket" value="<?= (int) $ticketVu['id'] ?>">
      <div class="champ">
        <textarea name="reponse" rows="5" maxlength="8000" required placeholder="Écrivez votre réponse…"></textarea>
      </div>
      <div class="actions" style="margin-top:.8rem;display:flex;gap:.6rem;flex-wrap:wrap">
        <button type="submit" class="btn">Envoyer la réponse</button>
        <?php if ($gestion && $ticketVu['statut'] !== 'resolu'): ?>
          <button type="submit" class="btn btn--contour" name="action" value="resoudre">Marquer comme résolu</button>
        <?php elseif ($gestion && $ticketVu['statut'] === 'resolu'): ?>
          <button type="submit" class="btn btn--contour" name="action" value="rouvrir">Rouvrir le ticket</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
  <?php endif; ?>

<?php
    require __DIR__ . '/inc/pied.php';
    return;
endif;

/* ------------------------------------------------------------------ */
/*  Vue liste                                                          */
/* ------------------------------------------------------------------ */
$mesTickets = db()->prepare('SELECT * FROM tickets WHERE membre_id = ? ORDER BY cree_le DESC LIMIT 20');
$mesTickets->execute([(int) $moi['id']]);
$mesTickets = $mesTickets->fetchAll();

$tousTickets = [];
if ($gestion) {
    $tousTickets = db()->query('SELECT * FROM tickets ORDER BY (statut="resolu") ASC, cree_le DESC LIMIT 200')->fetchAll();
}
?>

<style>
.fil-tickets{display:flex;flex-direction:column;gap:.7rem;margin:1rem 0}
.bulle{max-width:78%;border-radius:10px;padding:.7rem .95rem}
.bulle__meta{font-size:.72rem;color:var(--gris-500);margin-bottom:.2rem}
.bulle__txt{font-size:.92rem;line-height:1.55}
.bulle--membre{align-self:flex-start;background:var(--gris-100);border:1px solid var(--gris-200)}
.bulle--bureau{align-self:flex-end;background:#eef2fb;border:1px solid #d4ddf1}
</style>

<div class="detail">
  <div>
    <div class="bloc">
      <h2>Ouvrir un ticket</h2>
      <p class="aide" style="margin:0 0 1rem">
        Une question, une anomalie, une évolution à demander ? Décrivez votre besoin : nous recevons
        une alerte immédiate et revenons vers vous par e-mail. Vous pouvez joindre des captures d’écran.
      </p>
      <form method="post" enctype="multipart/form-data" data-unique>
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="action" value="nouveau">
        <div class="champs champs--duo">
          <div class="champ">
            <label for="sujet">Sujet</label>
            <input type="text" id="sujet" name="sujet" maxlength="180" required placeholder="Ex. Erreur à la connexion">
          </div>
          <div class="champ">
            <label for="priorite">Priorité</label>
            <select id="priorite" name="priorite">
              <option value="basse">Basse</option>
              <option value="normale" selected>Normale</option>
              <option value="haute">Haute</option>
            </select>
          </div>
        </div>
        <div class="champ">
          <label for="message">Votre message</label>
          <textarea id="message" name="message" rows="7" maxlength="5000" required
                    placeholder="Décrivez le plus précisément possible : sur quelle page, ce que vous attendiez, ce qui s’est passé…"></textarea>
        </div>
        <div class="champ">
          <label for="pj">Pièces jointes <span class="muet">(images ou PDF, 6 max, 12 Mo au total)</span></label>
          <input type="file" id="pj" name="pj[]" multiple accept="image/*,.pdf">
        </div>
        <div class="actions" style="margin-top:1rem">
          <button type="submit" class="btn">Envoyer le ticket</button>
        </div>
      </form>
    </div>

    <?php if ($gestion): ?>
    <div class="bloc">
      <h2>Tous les tickets</h2>
      <?php if (!$tousTickets): ?>
        <p class="vide">Aucun ticket pour le moment.</p>
      <?php else: ?>
        <div class="tableau tableau--filtrable">
          <table>
            <thead><tr><th>N°</th><th>Date</th><th>Membre</th><th>Sujet</th><th>Priorité</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tousTickets as $t): [$l, $c] = $stTicket[$t['statut']] ?? [$t['statut'], 'attente']; ?>
              <tr>
                <td>#<?= (int) $t['id'] ?></td>
                <td style="font-size:.8rem"><?= e(date('d/m/Y', strtotime((string) $t['cree_le']))) ?></td>
                <td><?= e($t['membre_nom']) ?></td>
                <td><a href="/admin/support.php?ticket=<?= (int) $t['id'] ?>"><?= e($t['sujet']) ?></a></td>
                <td style="font-size:.8rem"><?= e(ucfirst($t['priorite'])) ?></td>
                <td><span class="etat etat--<?= e($c) ?>"><?= e($l) ?></span></td>
                <td><a href="/admin/support.php?ticket=<?= (int) $t['id'] ?>">Ouvrir →</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="bloc">
      <h2>Historique des tickets</h2>
      <?php if (!$mesTickets): ?>
        <p class="vide">Vous n’avez pas encore ouvert de ticket.</p>
      <?php else: ?>
        <div class="tableau tableau--filtrable">
          <table>
            <thead><tr><th>N°</th><th>Date</th><th>Sujet</th><th>Priorité</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($mesTickets as $t): [$l, $c] = $stTicket[$t['statut']] ?? [$t['statut'], 'attente']; ?>
              <tr>
                <td>#<?= (int) $t['id'] ?></td>
                <td style="font-size:.8rem"><?= e(date('d/m/Y', strtotime((string) $t['cree_le']))) ?></td>
                <td><a href="/admin/support.php?ticket=<?= (int) $t['id'] ?>"><?= e($t['sujet']) ?></a></td>
                <td style="font-size:.8rem"><?= e(ucfirst($t['priorite'])) ?></td>
                <td><span class="etat etat--<?= e($c) ?>"><?= e($l) ?></span></td>
                <td><a href="/admin/support.php?ticket=<?= (int) $t['id'] ?>">Ouvrir →</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="bloc">
      <h2>Guide d’utilisation</h2>
      <p class="aide" style="margin:0 0 1rem">
        Un mode d’emploi pas-à-pas explique tout ce que le site et le back-office permettent de faire.
      </p>
      <div class="actions">
        <a class="btn btn--contour" href="/admin/guide.php" target="_blank" rel="noopener">Ouvrir le guide en ligne ↗</a>
        <a class="btn btn--contour" href="/assets/guide-utilisateur.pdf" target="_blank" rel="noopener">Télécharger le guide (PDF)</a>
      </div>
    </div>
    <div class="bloc">
      <h2>Votre prestataire</h2>
      <dl class="paire">
        <dt>Société</dt><dd>OB3M Distribution — Cyrille Lepicier</dd>
        <dt>Suivi</dt><dd>Maintenance technique, mises à jour, sécurité, évolutions graphiques et correction des anomalies.</dd>
      </dl>
      <p class="aide" style="margin:.6rem 0 0">Les demandes passent par les tickets : chaque échange est tracé, et rien ne se perd.</p>
    </div>
  </div>
</div>

<?php require __DIR__ . '/inc/pied.php'; ?>
