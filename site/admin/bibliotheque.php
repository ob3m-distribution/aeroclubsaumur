<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/biblio-adherents.php';
exiger_droit('contenus.gerer');

const BIB_EXTS = ['pdf','jpg','jpeg','png','gif','webp','mp4','mov','webm',
                  'xlsx','xls','docx','doc','pptx','ppt','zip'];
const BIB_MAX  = 60 * 1024 * 1024;   // 60 Mo
$DOCS_DIR = __DIR__ . '/../docs-adherents';

/* ---- Actions ------------------------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // On revient toujours dans le dossier où l'on était.
    $retour = '/admin/bibliotheque.php' . (($_POST['d'] ?? '') !== '' ? '?d=' . (int) $_POST['d'] : '');
    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $_SESSION['message_erreur'] = 'Session expirée, action non effectuée.';
        header('Location: ' . $retour, true, 303);
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'creer_section') {
            $nom = trim((string) ($_POST['nom'] ?? ''));
            // 0 (racine) doit devenir NULL : sinon la clé étrangère parent_id échoue.
            $parent = ((int) ($_POST['parent_id'] ?? 0)) ?: null;
            if ($parent !== null && !isset(biblio_sections_toutes()[$parent])) {
                throw new RuntimeException('Dossier parent introuvable.');
            }
            if ($nom === '') throw new RuntimeException('Le nom de la section est obligatoire.');
            $pos = (int) db()->query('SELECT COALESCE(MAX(position),-1)+1 FROM biblio_sections WHERE parent_id ' .
                   ($parent === null ? 'IS NULL' : '= ' . $parent))->fetchColumn();
            $s = db()->prepare('INSERT INTO biblio_sections (parent_id,nom,position) VALUES (?,?,?)');
            $s->execute([$parent, $nom, $pos]);
            journaliser('biblio.section.cree', 'section#' . db()->lastInsertId(), $nom);
            $_SESSION['message_succes'] = 'Section créée.';
        }
        elseif ($action === 'renommer_section') {
            $id = (int) ($_POST['id'] ?? 0);
            $nom = trim((string) ($_POST['nom'] ?? ''));
            if ($nom === '') throw new RuntimeException('Nom vide.');
            db()->prepare('UPDATE biblio_sections SET nom=? WHERE id=?')->execute([$nom, $id]);
            journaliser('biblio.section.renomme', 'section#' . $id, $nom);
            $_SESSION['message_succes'] = 'Section renommée.';
        }
        elseif ($action === 'supprimer_section') {
            $id = (int) ($_POST['id'] ?? 0);
            // Fichiers à effacer (uploads uniquement), avant la cascade.
            $fichiers = collecte_fichiers_section($id);
            db()->prepare('DELETE FROM biblio_sections WHERE id=?')->execute([$id]);  // cascade docs + sous-sections
            foreach ($fichiers as $f) supprimer_fichier_upload($f, $DOCS_DIR);
            journaliser('biblio.section.supprime', 'section#' . $id);
            $_SESSION['message_succes'] = 'Section supprimée (avec son contenu).';
        }
        elseif ($action === 'ajouter_document') {
            $sid = (int) ($_POST['section_id'] ?? 0);
            if (!isset(biblio_sections_toutes()[$sid])) throw new RuntimeException('Section inconnue.');
            if (empty($_FILES['fichier']) || ($_FILES['fichier']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Aucun fichier reçu (ou trop volumineux).');
            }
            $tmp = $_FILES['fichier']['tmp_name'];
            $orig = (string) $_FILES['fichier']['name'];
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, BIB_EXTS, true)) throw new RuntimeException('Type de fichier non autorisé : .' . $ext);
            if (!is_uploaded_file($tmp)) throw new RuntimeException('Transfert invalide.');
            $taille = (int) filesize($tmp);
            if ($taille > BIB_MAX) throw new RuntimeException('Fichier trop volumineux (60 Mo max).');
            $nom = trim((string) ($_POST['nom'] ?? '')) ?: pathinfo($orig, PATHINFO_FILENAME);

            $dir = $DOCS_DIR . '/_uploads';
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new RuntimeException('Dossier d’upload inaccessible.');
            $rel = '_uploads/' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file($tmp, $DOCS_DIR . '/' . $rel)) throw new RuntimeException('Échec de l’enregistrement du fichier.');

            $p = db()->prepare('SELECT COALESCE(MAX(position),-1)+1 FROM biblio_documents WHERE section_id=?');
            $p->execute([$sid]); $pos = (int) $p->fetchColumn();
            $ins = db()->prepare('INSERT INTO biblio_documents (section_id,nom,fichier,type,taille,position) VALUES (?,?,?,?,?,?)');
            $ins->execute([$sid, $nom, $rel, $ext, $taille, $pos]);
            journaliser('biblio.doc.ajoute', 'doc#' . db()->lastInsertId(), $nom);
            $_SESSION['message_succes'] = 'Document ajouté : ' . $nom;
        }
        elseif ($action === 'reordonner') {
            // Nouvel ordre des documents d'une section (glisser-déposer).
            $sid = (int) ($_POST['section_id'] ?? 0);
            $ordre = array_filter(array_map('intval', explode(',', (string) ($_POST['ordre'] ?? ''))));
            $maj = db()->prepare('UPDATE biblio_documents SET position = ? WHERE id = ? AND section_id = ?');
            $pos = 0;
            foreach ($ordre as $docId) { $maj->execute([$pos++, $docId, $sid]); }
            journaliser('biblio.doc.reordonne', 'section#' . $sid, count($ordre) . ' documents');
            $_SESSION['message_succes'] = 'Ordre des documents enregistré.';
        }
        elseif ($action === 'renommer_document') {
            $id = (int) ($_POST['id'] ?? 0);
            $nom = trim((string) ($_POST['nom'] ?? ''));
            if ($nom === '') throw new RuntimeException('Nom vide.');
            db()->prepare('UPDATE biblio_documents SET nom=? WHERE id=?')->execute([$nom, $id]);
            journaliser('biblio.doc.renomme', 'doc#' . $id, $nom);
            $_SESSION['message_succes'] = 'Document renommé.';
        }
        elseif ($action === 'supprimer_document') {
            $id = (int) ($_POST['id'] ?? 0);
            $s = db()->prepare('SELECT fichier,nom FROM biblio_documents WHERE id=?');
            $s->execute([$id]); $d = $s->fetch();
            if ($d) {
                db()->prepare('DELETE FROM biblio_documents WHERE id=?')->execute([$id]);
                supprimer_fichier_upload($d['fichier'], $DOCS_DIR);
                journaliser('biblio.doc.supprime', 'doc#' . $id, $d['nom']);
                $_SESSION['message_succes'] = 'Document supprimé.';
            }
        }
        header('Location: ' . $retour, true, 303);
        exit;
    } catch (Throwable $ex) {
        $_SESSION['message_erreur'] = $ex->getMessage();
        header('Location: ' . $retour, true, 303);
        exit;
    }
}

/** Fichiers (relpaths) de tous les documents sous une section (récursif). */
function collecte_fichiers_section(int $sid): array {
    $ids = [$sid]; $out = [];
    // descendants
    $all = biblio_sections_toutes();
    $pile = [$sid];
    while ($pile) {
        $cur = array_pop($pile);
        foreach ($all as $s) if ($s['parent_id'] === $cur) { $ids[] = $s['id']; $pile[] = $s['id']; }
    }
    $in = implode(',', array_map('intval', $ids));
    foreach (db()->query("SELECT fichier FROM biblio_documents WHERE section_id IN ($in)") as $r) {
        $out[] = $r['fichier'];
    }
    return $out;
}
/** Efface un fichier physique s'il s'agit bien d'un upload sous docs-adherents. */
function supprimer_fichier_upload(string $rel, string $base): void {
    if (strncmp($rel, '_uploads/', 9) !== 0) return;      // on ne touche pas aux 83 fichiers d'origine
    $real = realpath($base . '/' . $rel);
    $root = realpath($base);
    if ($real && $root && strncmp($real, $root, strlen($root)) === 0 && is_file($real)) @unlink($real);
}

function csrf_input(): string { return '<input type="hidden" name="csrf" value="' . e(jeton_csrf()) . '">'; }

$sections = biblio_sections_toutes();
$courant  = (int) ($_GET['d'] ?? 0);
if ($courant && !isset($sections[$courant])) $courant = 0;

/* Sous-dossiers directs + documents du dossier courant. */
$enfants = array_values(array_filter($sections, fn($s) => ((int) ($s['parent_id'] ?? 0)) === $courant));
$docs = [];
if ($courant) {
    $st = db()->prepare('SELECT id,nom,type,taille FROM biblio_documents WHERE section_id=? ORDER BY position,id');
    $st->execute([$courant]); $docs = $st->fetchAll();
}

/* Compteur récursif de documents (pour l'étiquette des dossiers). */
$docParSection = [];
foreach (db()->query('SELECT section_id, COUNT(*) n FROM biblio_documents GROUP BY section_id') as $r)
    $docParSection[(int) $r['section_id']] = (int) $r['n'];
$enfantsDe = [];
foreach ($sections as $s) $enfantsDe[(int) ($s['parent_id'] ?? 0)][] = (int) $s['id'];
function nb_docs_rec(int $id, array $dps, array $ede): int {
    $n = $dps[$id] ?? 0;
    foreach ($ede[$id] ?? [] as $c) $n += nb_docs_rec($c, $dps, $ede);
    return $n;
}

/* Fil d'Ariane. */
$fil = []; $c = $courant; $g = 0;
while ($c && $g++ < 30) { $fil[] = $sections[$c]; $c = (int) ($sections[$c]['parent_id'] ?? 0); }
$fil = array_reverse($fil);

$titre = 'Bibliothèque adhérents';
$actif = 'biblio';
require __DIR__ . '/inc/entete.php';
?>

<div class="bloc">

  <nav class="bibx-fil" aria-label="Fil d’Ariane">
    <a href="?d=0">🏠 Bibliothèque</a>
    <?php foreach ($fil as $f): ?>
      <span>›</span><a href="?d=<?= (int) $f['id'] ?>"><?= e($f['nom']) ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="bibx-barre">
    <button type="button" class="btn" id="btn-dossier">📁 Nouveau dossier</button>
    <?php if ($courant): ?>
      <a class="btn btn--contour" href="#ajouter">⬆️ Ajouter un document</a>
    <?php endif; ?>
  </div>

  <?php if (!$enfants && !$docs): ?>
    <p class="bibx-vide">Ce dossier est vide.
      <?= $courant ? 'Ajoutez un document ci-dessous, ou' : '' ?> créez un
      <?= $courant ? 'sous-dossier' : 'dossier' ?> avec le bouton « 📁 Nouveau dossier ».</p>
  <?php endif; ?>

  <?php if ($enfants): ?>
  <h3 class="bibx-titre">Dossiers</h3>
  <div class="bibx-dossiers">
    <?php foreach ($enfants as $d): $n = nb_docs_rec((int) $d['id'], $docParSection, $enfantsDe); ?>
      <div class="bibx-dossier">
        <a class="bibx-dossier__lien" href="?d=<?= (int) $d['id'] ?>">
          <span class="bibx-dossier__ic">📁</span>
          <span class="bibx-dossier__nom"><?= e($d['nom']) ?></span>
          <span class="bibx-dossier__cpt"><?= $n ?> document<?= $n > 1 ? 's' : '' ?></span>
        </a>
        <div class="bibx-dossier__act">
          <button type="button" class="btn btn--contour btn--petit"
                  data-renommer="<?= (int) $d['id'] ?>" data-nom="<?= e($d['nom']) ?>">Renommer</button>
          <form method="post" style="display:inline">
            <?= csrf_input() ?><input type="hidden" name="action" value="supprimer_section">
            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><input type="hidden" name="d" value="<?= $courant ?>">
            <button class="btn btn--danger btn--petit"
                    data-confirmer="Supprimer le dossier « <?= e($d['nom']) ?> » et TOUT son contenu ?">Supprimer</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($docs): ?>
  <h3 class="bibx-titre">Documents <?php if (count($docs) > 1): ?><span class="bibx-hint">— glissez pour réordonner</span><?php endif; ?></h3>
  <ul class="bibx-docs" id="bibx-docs"<?= count($docs) > 1 ? ' data-reordonnable="1"' : '' ?>>
    <?php foreach ($docs as $d): ?>
      <li data-id="<?= (int) $d['id'] ?>"<?= count($docs) > 1 ? ' draggable="true"' : '' ?>>
        <?php if (count($docs) > 1): ?><span class="bibx-poignee" title="Glisser pour déplacer" aria-hidden="true">⠿</span><?php endif; ?>
        <span class="bibx-badge"><?= e(strtoupper($d['type'])) ?></span>
        <span class="bibx-docnom"><?= e($d['nom']) ?></span>
        <span class="bibx-taille"><?= $d['taille'] ? round($d['taille'] / 1048576, 1) . ' Mo' : '' ?></span>
        <a class="btn btn--contour btn--petit" href="/doc-adherent.php?doc=<?= (int) $d['id'] ?>" target="_blank" rel="noopener">Ouvrir</a>
        <button type="button" class="btn btn--contour btn--petit"
                data-renommer-doc="<?= (int) $d['id'] ?>" data-nom="<?= e($d['nom']) ?>">Renommer</button>
        <form method="post" style="display:inline">
          <?= csrf_input() ?><input type="hidden" name="action" value="supprimer_document">
          <input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><input type="hidden" name="d" value="<?= $courant ?>">
          <button class="btn btn--danger btn--petit" data-confirmer="Supprimer ce document ?">Supprimer</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <?php if ($courant): ?>
  <div class="bibx-ajout" id="ajouter">
    <h3 class="bibx-titre">Ajouter un document dans ce dossier</h3>
    <form method="post" enctype="multipart/form-data" class="bibx-upload">
      <?= csrf_input() ?><input type="hidden" name="action" value="ajouter_document">
      <input type="hidden" name="section_id" value="<?= $courant ?>"><input type="hidden" name="d" value="<?= $courant ?>">
      <label class="bibx-fichier">
        <input type="file" name="fichier" required>
        <span>Choisir un fichier (PDF, image, vidéo, Excel… 60 Mo max)</span>
      </label>
      <input type="text" name="nom" placeholder="Nom affiché (facultatif)">
      <button class="btn" type="submit">Ajouter le document</button>
    </form>
  </div>
  <?php endif; ?>

</div>

<!-- Formulaires cachés pilotés par les boutons (nom demandé simplement). -->
<form id="f-creer" method="post" hidden>
  <?= csrf_input() ?><input type="hidden" name="action" value="creer_section">
  <input type="hidden" name="parent_id" value="<?= $courant ?>"><input type="hidden" name="d" value="<?= $courant ?>">
  <input type="hidden" name="nom" id="creer-nom">
</form>
<form id="f-renommer" method="post" hidden>
  <?= csrf_input() ?><input type="hidden" name="action" value="renommer_section">
  <input type="hidden" name="d" value="<?= $courant ?>">
  <input type="hidden" name="id" id="ren-id"><input type="hidden" name="nom" id="ren-nom">
</form>
<form id="f-renommer-doc" method="post" hidden>
  <?= csrf_input() ?><input type="hidden" name="action" value="renommer_document">
  <input type="hidden" name="d" value="<?= $courant ?>">
  <input type="hidden" name="id" id="rend-id"><input type="hidden" name="nom" id="rend-nom">
</form>
<form id="f-reordonner" method="post" hidden>
  <?= csrf_input() ?><input type="hidden" name="action" value="reordonner">
  <input type="hidden" name="d" value="<?= $courant ?>">
  <input type="hidden" name="section_id" value="<?= $courant ?>">
  <input type="hidden" name="ordre" id="reordonner-ordre">
</form>

<style>
.bibx-fil { display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; font-size:.95rem; margin-bottom:1rem; }
.bibx-fil a { text-decoration:none; color:var(--lien,#1C5A8C); font-weight:600; }
.bibx-fil span { color:var(--gris-500); }
.bibx-barre { display:flex; gap:.6rem; flex-wrap:wrap; margin-bottom:1.25rem; }
.bibx-titre { font-size:.8rem; letter-spacing:.08em; text-transform:uppercase; color:var(--gris-500); margin:1.4rem 0 .6rem; }
.bibx-dossiers { display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:.8rem; }
.bibx-dossier { border:1px solid var(--gris-200); border-radius:10px; background:var(--blanc); overflow:hidden; }
.bibx-dossier__lien { display:flex; flex-direction:column; align-items:flex-start; gap:.15rem; padding:1rem 1.1rem .6rem;
  text-decoration:none; color:var(--noir); }
.bibx-dossier__lien:hover { background:var(--gris-100); }
.bibx-dossier__ic { font-size:1.9rem; line-height:1; }
.bibx-dossier__nom { font-weight:700; font-size:1.02rem; }
.bibx-dossier__cpt { font-size:.8rem; color:var(--gris-500); }
.bibx-dossier__act { display:flex; gap:.35rem; padding:.5rem 1.1rem .8rem; }
.bibx-docs { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:.4rem; }
.bibx-docs li { display:flex; align-items:center; gap:.6rem; padding:.55rem .7rem; border:1px solid var(--gris-200);
  border-radius:8px; background:var(--blanc); flex-wrap:wrap; }
.bibx-badge { font-size:.6rem; font-weight:800; color:#C0392B; background:#FBEBE9; border:1px solid rgba(192,57,43,.22); border-radius:4px; padding:3px 6px; }
.bibx-docnom { flex:1; min-width:140px; font-size:.95rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.bibx-taille { font-size:.78rem; color:var(--gris-500); }
.bibx-vide { background:var(--gris-100); border-radius:10px; padding:1.25rem 1.4rem; color:var(--gris-700); }
.bibx-ajout { margin-top:2rem; border-top:1px solid var(--gris-200); padding-top:1rem; }
.bibx-upload { display:flex; flex-direction:column; gap:.7rem; max-width:520px; }
.bibx-fichier { display:block; border:2px dashed var(--gris-300); border-radius:10px; padding:1.1rem; text-align:center;
  cursor:pointer; background:var(--gris-100); }
.bibx-fichier:hover { border-color:var(--ciel,#5B9BD5); }
.bibx-fichier span { display:block; color:var(--gris-700); font-size:.9rem; margin-top:.35rem; }
.bibx-hint { font-weight:400; text-transform:none; letter-spacing:0; color:var(--gris-400); }
.bibx-poignee { cursor:grab; color:var(--gris-400); font-size:1.1rem; line-height:1; user-select:none; padding:0 .1rem; }
.bibx-docs li[draggable=true] { cursor:default; }
.bibx-drag { opacity:.5; background:#fbf7ec !important; border-color:var(--or,#b08d2c) !important; }
</style>

<script>
(function () {
  var d = document.querySelector('#f-creer input[name="d"]').value;
  var bd = document.getElementById('btn-dossier');
  if (bd) bd.addEventListener('click', function () {
    var n = prompt('Nom du nouveau dossier :');
    if (n && n.trim()) { document.getElementById('creer-nom').value = n.trim(); document.getElementById('f-creer').submit(); }
  });
  document.querySelectorAll('[data-renommer]').forEach(function (b) {
    b.addEventListener('click', function () {
      var n = prompt('Nouveau nom du dossier :', b.getAttribute('data-nom'));
      if (n && n.trim()) { document.getElementById('ren-id').value = b.getAttribute('data-renommer'); document.getElementById('ren-nom').value = n.trim(); document.getElementById('f-renommer').submit(); }
    });
  });
  document.querySelectorAll('[data-renommer-doc]').forEach(function (b) {
    b.addEventListener('click', function () {
      var n = prompt('Nouveau nom du document :', b.getAttribute('data-nom'));
      if (n && n.trim()) { document.getElementById('rend-id').value = b.getAttribute('data-renommer-doc'); document.getElementById('rend-nom').value = n.trim(); document.getElementById('f-renommer-doc').submit(); }
    });
  });
  // Afficher le nom du fichier choisi.
  var fi = document.querySelector('.bibx-fichier input[type=file]');
  if (fi) fi.addEventListener('change', function () {
    var s = fi.parentNode.querySelector('span');
    if (fi.files.length) s.textContent = fi.files[0].name;
  });

  // Glisser-déposer : réordonner les documents du dossier.
  var liste = document.getElementById('bibx-docs');
  if (liste && liste.getAttribute('data-reordonnable') === '1') {
    var drag = null;
    liste.querySelectorAll('li').forEach(function (li) {
      li.addEventListener('dragstart', function (e) { drag = li; li.classList.add('bibx-drag'); e.dataTransfer.effectAllowed = 'move'; });
      li.addEventListener('dragend', function () { li.classList.remove('bibx-drag'); enregistrerOrdre(); });
      li.addEventListener('dragover', function (e) {
        e.preventDefault();
        var apres = (e.clientY - li.getBoundingClientRect().top) > li.offsetHeight / 2;
        if (drag && drag !== li) { li.parentNode.insertBefore(drag, apres ? li.nextSibling : li); }
      });
    });
    var timer = null, dernier = '';
    function enregistrerOrdre() {
      var ids = [].map.call(liste.querySelectorAll('li'), function (l) { return l.getAttribute('data-id'); });
      var ordre = ids.join(',');
      if (ordre === dernier) return;          // pas de changement -> pas d'envoi
      dernier = ordre;
      document.getElementById('reordonner-ordre').value = ordre;
      document.getElementById('f-reordonner').submit();
    }
  }
})();
</script>

<?php require __DIR__ . '/inc/pied.php'; ?>
