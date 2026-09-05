<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
exiger_connexion();
if (!est_superadmin()) {
    http_response_code(403);
    $_SESSION['message_erreur'] = 'Documentation réservée aux super-administrateurs.';
    header('Location: /admin/', true, 302);
    exit;
}

/* ------------------------------------------------------------------ */
/*  Tables (auto-création)                                             */
/* ------------------------------------------------------------------ */
db()->exec("CREATE TABLE IF NOT EXISTS site_documentation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cle VARCHAR(60) NOT NULL UNIQUE,
    titre VARCHAR(160) NOT NULL,
    contenu MEDIUMTEXT NOT NULL,
    ordre INT NOT NULL DEFAULT 0,
    maj_le TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
db()->exec("CREATE TABLE IF NOT EXISTS site_doc_journal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resume VARCHAR(255) NOT NULL,
    detail MEDIUMTEXT NULL,
    source VARCHAR(40) NOT NULL DEFAULT 'auto',
    cree_le TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_date (cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ------------------------------------------------------------------ */
/*  Amorçage (une seule fois, si vide)                                 */
/* ------------------------------------------------------------------ */
if ((int) db()->query('SELECT COUNT(*) FROM site_documentation')->fetchColumn() === 0) {
    $seed = require __DIR__ . '/inc/documentation-seed.php';
    $ins = db()->prepare('INSERT INTO site_documentation (cle, titre, contenu, ordre) VALUES (?,?,?,?)');
    $o = 0;
    foreach ($seed as $s) { $ins->execute([$s['cle'], $s['titre'], $s['contenu'], $o += 10]); }
}

/* ------------------------------------------------------------------ */
/*  Actions                                                           */
/* ------------------------------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $_SESSION['message_erreur'] = 'Session expirée.';
        header('Location: /admin/documentation.php', true, 303); exit;
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'enregistrer') {
        $id     = (int) ($_POST['id'] ?? 0);
        $titre  = trim((string) ($_POST['titre'] ?? ''));
        $contenu = (string) ($_POST['contenu'] ?? '');
        $ordre  = (int) ($_POST['ordre'] ?? 0);
        if ($titre === '') {
            $_SESSION['message_erreur'] = 'Titre requis.';
        } elseif ($id > 0) {
            db()->prepare('UPDATE site_documentation SET titre=?, contenu=?, ordre=? WHERE id=?')
                ->execute([mb_substr($titre,0,160), $contenu, $ordre, $id]);
            $_SESSION['message_succes'] = 'Section mise à jour.';
        } else {
            $cle = trim((string) ($_POST['cle'] ?? '')) ?: 'section-' . bin2hex(random_bytes(3));
            try {
                db()->prepare('INSERT INTO site_documentation (cle,titre,contenu,ordre) VALUES (?,?,?,?)')
                    ->execute([mb_substr($cle,0,60), mb_substr($titre,0,160), $contenu, $ordre]);
                $_SESSION['message_succes'] = 'Section ajoutée.';
            } catch (Throwable $e) { $_SESSION['message_erreur'] = 'Cette clé existe déjà.'; }
        }
        journaliser('doc.maj', 'documentation', $titre);
    } elseif ($action === 'supprimer') {
        db()->prepare('DELETE FROM site_documentation WHERE id=?')->execute([(int) ($_POST['id'] ?? 0)]);
        $_SESSION['message_succes'] = 'Section supprimée.';
    } elseif ($action === 'purger_journal') {
        db()->exec('DELETE FROM site_doc_journal WHERE cree_le < DATE_SUB(NOW(), INTERVAL 180 DAY)');
        $_SESSION['message_succes'] = 'Journal purgé (entrées de plus de 6 mois).';
    }
    header('Location: /admin/documentation.php', true, 303); exit;
}

/* ------------------------------------------------------------------ */
/*  Rendu mini-markdown (# ## ###, - listes, **gras**, liens auto)     */
/* ------------------------------------------------------------------ */
function doc_markdown(string $txt): string
{
    $out = ''; $listeOuverte = false;
    foreach (preg_split('/\r\n|\r|\n/', $txt) as $ligne) {
        $l = rtrim($ligne);
        $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $inline = function (string $s) use ($esc) {
            $s = $esc($s);
            $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
            $s = preg_replace('/`(.+?)`/', '<code>$1</code>', $s);
            return $s;
        };
        if ($l === '') { if ($listeOuverte) { $out .= "</ul>"; $listeOuverte = false; } continue; }
        if (preg_match('/^### (.+)/', $l, $m)) { if ($listeOuverte){$out.="</ul>";$listeOuverte=false;} $out .= '<h4>' . $inline($m[1]) . '</h4>'; }
        elseif (preg_match('/^## (.+)/', $l, $m)) { if ($listeOuverte){$out.="</ul>";$listeOuverte=false;} $out .= '<h3>' . $inline($m[1]) . '</h3>'; }
        elseif (preg_match('/^# (.+)/', $l, $m)) { if ($listeOuverte){$out.="</ul>";$listeOuverte=false;} $out .= '<h2>' . $inline($m[1]) . '</h2>'; }
        elseif (preg_match('/^[-*] (.+)/', $l, $m)) { if (!$listeOuverte){$out.="<ul>";$listeOuverte=true;} $out .= '<li>' . $inline($m[1]) . '</li>'; }
        else { if ($listeOuverte){$out.="</ul>";$listeOuverte=false;} $out .= '<p>' . $inline($l) . '</p>'; }
    }
    if ($listeOuverte) $out .= '</ul>';
    return $out;
}

$sections = db()->query('SELECT * FROM site_documentation ORDER BY ordre ASC, id ASC')->fetchAll();
$journal  = db()->query('SELECT * FROM site_doc_journal ORDER BY cree_le DESC, id DESC LIMIT 100')->fetchAll();
$nbJournal = (int) db()->query('SELECT COUNT(*) FROM site_doc_journal')->fetchColumn();

$titre = 'Documentation site';
$actif = 'documentation';
require __DIR__ . '/inc/entete.php';
?>

<style>
.doc-section{margin-bottom:1.1rem}
.doc-rendu h2{font-size:1.15rem;color:var(--noir);margin:1rem 0 .3rem}
.doc-rendu h3{font-size:1rem;color:var(--noir);margin:.8rem 0 .25rem}
.doc-rendu h4{font-size:.92rem;color:var(--gris-700);margin:.7rem 0 .2rem;text-transform:uppercase;letter-spacing:.03em}
.doc-rendu p{margin:.3rem 0;font-size:.92rem;line-height:1.55}
.doc-rendu ul{margin:.3rem 0 .3rem 1.1rem;font-size:.92rem}
.doc-rendu li{margin:.15rem 0}
.doc-rendu code{background:var(--gris-100);border:1px solid var(--gris-200);border-radius:4px;padding:.02rem .35rem;font-size:.85em}
.doc-edit{margin-top:.5rem}
.doc-edit textarea{width:100%;min-height:200px;font-family:ui-monospace,Consolas,monospace;font-size:.85rem;line-height:1.5}
.doc-journal li{margin:.35rem 0;font-size:.88rem}
.doc-journal .d{color:var(--gris-500);font-size:.8rem}
.doc-journal .f{color:var(--gris-700)}
.doc-aide{background:var(--gris-050);border:1px solid var(--gris-200);border-radius:8px;padding:.7rem .9rem;font-size:.85rem;color:var(--gris-700);margin-bottom:1rem}
</style>

<div class="doc-aide">
  Historique vivant de tout ce qui a été construit (fonctionnalités, intégrations, incidents, chantiers).
  Les sections ci-dessous sont <strong>éditables</strong> (mini-format : <code># Titre</code>, <code>## Sous-titre</code>,
  <code>- liste</code>, <code>**gras**</code>). Le <strong>Journal des développements</strong> en bas se remplit
  <strong>automatiquement à chaque nouveau dev</strong>.
</div>

<?php foreach ($sections as $s): ?>
  <div class="bloc doc-section">
    <div class="doc-rendu">
      <?= doc_markdown((string) $s['contenu']) ?>
    </div>
    <details class="doc-edit">
      <summary>Modifier « <?= e($s['titre']) ?> »</summary>
      <form method="post" style="margin-top:.6rem">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="action" value="enregistrer">
        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
        <div class="champs champs--duo">
          <div class="champ"><label>Titre</label><input type="text" name="titre" value="<?= e($s['titre']) ?>" maxlength="160"></div>
          <div class="champ"><label>Ordre</label><input type="number" name="ordre" value="<?= (int) $s['ordre'] ?>"></div>
        </div>
        <div class="champ"><label>Contenu</label><textarea name="contenu"><?= e($s['contenu']) ?></textarea></div>
        <div class="actions" style="display:flex;gap:.6rem;margin-top:.5rem">
          <button class="btn" type="submit">Enregistrer</button>
          <button class="btn btn--contour" type="submit" name="action" value="supprimer"
                  onclick="return confirm('Supprimer cette section ?')">Supprimer</button>
        </div>
        <p class="aide" style="margin:.4rem 0 0">Clé : <code><?= e($s['cle']) ?></code> · dernière maj <?= e(date('d/m/Y H:i', strtotime((string) $s['maj_le']))) ?></p>
      </form>
    </details>
  </div>
<?php endforeach; ?>

<div class="bloc">
  <details>
    <summary><strong>➕ Ajouter une section</strong></summary>
    <form method="post" style="margin-top:.6rem">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <input type="hidden" name="action" value="enregistrer">
      <div class="champs champs--duo">
        <div class="champ"><label>Titre</label><input type="text" name="titre" maxlength="160" required></div>
        <div class="champ"><label>Ordre</label><input type="number" name="ordre" value="999"></div>
      </div>
      <div class="champ"><label>Clé (identifiant court, optionnel)</label><input type="text" name="cle" maxlength="60" placeholder="ex. paiement-alma"></div>
      <div class="champ"><label>Contenu</label><textarea name="contenu" placeholder="## Sous-titre&#10;- point 1&#10;- point 2"></textarea></div>
      <div class="actions" style="margin-top:.5rem"><button class="btn" type="submit">Ajouter</button></div>
    </form>
  </details>
</div>

<div class="bloc">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
    <h2 style="margin:0">Journal des développements <span class="muet">(<?= $nbJournal ?> entrées, auto)</span></h2>
    <form method="post" onsubmit="return confirm('Purger les entrées de plus de 6 mois ?')">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <button class="btn btn--contour" type="submit" name="action" value="purger_journal">Purger &gt; 6 mois</button>
    </form>
  </div>
  <p class="aide" style="margin:.2rem 0 .8rem">Alimenté automatiquement à chaque commit du code (date + résumé + fichiers modifiés).</p>
  <?php if (!$journal): ?>
    <p class="vide">Aucune entrée pour l’instant — la première apparaîtra au prochain dev.</p>
  <?php else: ?>
    <ul class="doc-journal">
      <?php foreach ($journal as $j): ?>
        <li>
          <span class="d"><?= e(date('d/m/Y H:i', strtotime((string) $j['cree_le']))) ?></span> —
          <?= e($j['resume']) ?>
          <?php if (!empty($j['detail'])): ?><br><span class="f"><?= e($j['detail']) ?></span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/inc/pied.php'; ?>
