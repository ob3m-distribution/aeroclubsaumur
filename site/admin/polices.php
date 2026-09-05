<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/contenu.php';
require_once __DIR__ . '/../inc/polices.php';
exiger_droit('contenus.gerer');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && jeton_csrf_valide($_POST['csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    $cle    = (string) ($_POST['police'] ?? '');

    if ($action === 'appliquer' && isset(POLICES[$cle])) {
        definir_parametre('police', $cle);
        journaliser('police.changee', $cle);
        $_SESSION['message_succes'] = 'Police appliquée à tout le site : ' . POLICES[$cle]['nom'] . '.';
        header('Location: /admin/polices.php', true, 303);
        exit;
    }

    if ($action === 'apercu' && isset(POLICES[$cle])) {
        // L'aperçu passe par le mode édition : il n'affecte que vous.
        $_SESSION['mode_edition_depuis'] = time();
        header('Location: /?police=' . urlencode($cle), true, 303);
        exit;
    }
}

$actuelle = parametre('police', 'inter');

$titre = 'Police du site';
$actif = 'contenus';
require __DIR__ . '/inc/entete.php';
?>

<p><a href="/admin/contenus.php">← Contenus du site</a></p>

<div class="bloc" style="border-left:3px solid var(--rouge)">
  <h2>Choisir la police du site</h2>
  <p style="max-width:62ch;color:var(--gris-700)">
    Cliquez sur <strong>Voir sur le site</strong> pour parcourir vos vraies pages
    avec cette police — l’aperçu n’est visible que par vous, les visiteurs continuent
    de voir la police actuelle. Quand une vous convient, cliquez sur
    <strong>Appliquer</strong>.
  </p>
  <p style="max-width:62ch;color:var(--gris-700);margin-bottom:0">
    Toutes sont sous licence libre et hébergées sur notre serveur : aucune requête
    vers Google, rien à déclarer côté RGPD.
  </p>
</div>

<?php foreach (POLICES as $cle => $p):
  $estActuelle = ($cle === $actuelle);
  $famille = trim(explode(',', $p['famille'])[0], " '\"");
?>
  <div class="bloc" style="<?= $estActuelle ? 'border-left:3px solid var(--vert)' : '' ?>">

    <style>
      <?php foreach ($p['fichiers'] as $f): ?>
        @font-face{font-family:'<?= e($famille) ?>';src:url('/assets/fonts/<?= e($f) ?>') format('woff2');font-weight:100 900;font-display:swap}
      <?php endforeach; ?>
      .ex-<?= e($cle) ?> { font-family: <?= $p['famille'] ?>; }
    </style>

    <div class="bloc__titre">
      <h2><?= e($p['nom']) ?>
        <?php if ($estActuelle): ?>
          <span class="etat etat--paye" style="margin-left:.4rem">police actuelle</span>
        <?php endif; ?>
      </h2>
      <span class="etat etat--utilise"><?= e($p['caractere']) ?></span>
    </div>

    <p style="color:var(--gris-700);max-width:64ch"><?= e($p['description']) ?></p>

    <!-- Échantillon dans les conditions réelles du site -->
    <div class="ex-<?= e($cle) ?>"
         style="border:1px solid var(--gris-200);border-radius:var(--rayon);
                padding:1.5rem 1.75rem;background:var(--blanc);margin:1.25rem 0">
      <p style="font-size:.75rem;font-weight:500;letter-spacing:.09em;
                text-transform:uppercase;color:var(--gris-500);margin:0 0 .6rem">
        Saumur Air Club
      </p>
      <p style="font-size:2.25rem;font-weight:400;line-height:1.1;color:var(--noir);
                margin:0 0 .5rem;letter-spacing:-.01em">
        Le ciel n’est pas la limite
      </p>
      <p style="font-size:1rem;line-height:1.5;color:var(--gris-700);margin:0 0 1rem;max-width:60ch">
        Venez découvrir le Val de Loire vu d’en haut. Châteaux, vignoble et bancs
        de sable de la Loire, à quelques minutes de vol de Saumur.
      </p>
      <p style="font-size:.875rem;color:var(--gris-500);margin:0">
        130 € · 30 minutes · 1450 × 30 m · fréquence 120.605 · F-HSAU
      </p>
    </div>

    <div class="actions">
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="police" value="<?= e($cle) ?>">
        <input type="hidden" name="action" value="apercu">
        <button type="submit" class="btn btn--contour">Voir sur le site ↗</button>
      </form>

      <?php if (!$estActuelle): ?>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <input type="hidden" name="police" value="<?= e($cle) ?>">
          <input type="hidden" name="action" value="appliquer">
          <button type="submit" class="btn"
                  data-confirmer="Appliquer <?= e($p['nom']) ?> à tout le site, pour tous les visiteurs ?">
            Appliquer au site
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<div class="bloc">
  <h3>Mon avis, si ça peut aider</h3>
  <p style="color:var(--gris-700);max-width:64ch">
    <strong>Space Grotesk</strong> est celle qui raconte le mieux votre activité :
    ses formes viennent des lettrages techniques et aérospatiaux. C’est un parti pris
    assumé, qui donnera une identité immédiate au site.
  </p>
  <p style="color:var(--gris-700);max-width:64ch;margin-bottom:0">
    <strong>Manrope</strong> est le meilleur compromis : nettement plus moderne
    qu’Inter, avec du caractère, mais sans jamais gêner la lecture d’un texte long.
    Si vous hésitez, c’est celle-là.
  </p>
</div>

<?php require __DIR__ . '/inc/pied.php'; ?>
