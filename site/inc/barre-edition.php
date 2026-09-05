<?php
declare(strict_types=1);

/**
 * Barre affichée UNIQUEMENT pendant une session d'édition.
 *
 * On ne peut pas activer l'édition depuis le site : cela se fait
 * exclusivement depuis le back-office, section « Contenus du site ».
 * Cette barre sert à savoir où l'on en est et à terminer.
 */
require_once __DIR__ . '/contenu.php';

if (!mode_edition()) {
    return;
}

require_once __DIR__ . '/auth.php';

$restant = EDITION_DUREE - (time() - (int) ($_SESSION['mode_edition_depuis'] ?? 0));
$minutes = max(1, (int) ceil($restant / 60));
?>
<div class="barre-edition barre-edition--active">
  <span class="barre-edition__etat">✎ Mode modification</span>
  <span class="barre-edition__aide">Cliquez sur un texte ou une photo entourés de pointillés</span>

  <form method="post" action="/admin/mode-edition.php" style="display:inline">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
    <input type="hidden" name="action" value="terminer">
    <button type="submit" class="barre-edition__bouton">Terminer</button>
  </form>

  <span class="barre-edition__lien" title="Au-delà, il faudra repasser par le back-office">
    <?= $minutes ?> min
  </span>
</div>

<link rel="stylesheet" href="/assets/css/edition.css?v=3">
<script>window.SAC_CSRF = <?= json_encode(jeton_csrf(), JSON_THROW_ON_ERROR) ?>;</script>
<script src="/assets/js/edition.js?v=3" defer></script>
