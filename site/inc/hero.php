<?php
declare(strict_types=1);
require_once __DIR__ . '/contenu.php';

/**
 * Hero commun. La page definit avant l'include :
 *   $hero = [
 *     'image'    => '/assets/img/…',      // obligatoire
 *     'alt'      => '…',                  // obligatoire
 *     'surtitre' => '…',                  // facultatif
 *     'titre'    => '…',                  // obligatoire (le <h1>)
 *     'accroche' => '…',                  // facultatif
 *     'actions'  => '<a …>…</a>',         // facultatif, HTML deja echappe
 *     'encart'   => ['surtitre','texte','lien','libelle'],  // facultatif
 *     'page'     => true,                 // hero reduit des pages interieures
 *
 *     // Rendre un element modifiable depuis le site : on fournit sa cle.
 *     'cle_image' => 'accueil.hero.image', 'cle_titre' => …, etc.
 *   ];
 */
$h = $hero ?? [];
$estPage = !empty($h['page']);
$variante = $h['variante'] ?? '';   // ex. « accueil » : grand hero animé
$classesHero = 'hero'
    . ($estPage ? ' hero--page' : '')
    . ($variante ? ' hero--' . preg_replace('/[^a-z-]/', '', $variante) : '');

/** Affiche un element : modifiable si une cle est fournie, brut sinon. */
/* Cadrage vertical de la photo ('position' => 'center 15%') : le hero
   rogne l'image en object-fit: cover, et la barre de navigation passe
   par-dessus le haut -- utile quand le sujet (des visages) est en haut. */
$stylePosition = !empty($h['position'])
    ? 'object-position:' . preg_replace('/[^a-z0-9% .-]/', '', (string) $h['position'])
    : null;

$rendu = static function (string $champ, string $type = 'court') use ($h): string {
    $valeur = (string) ($h[$champ] ?? '');
    $cle    = $h['cle_' . $champ] ?? null;
    return $cle ? texte((string) $cle, $valeur, $type) : e($valeur);
};
?>
<section class="<?= e($classesHero) ?>">
  <div class="hero__cadre">
  <?php if (!empty($h['fond'])): /* Accueil animé : 3 calques (fond fixe, nuages, avion). */ ?>
    <?php $srcNuages = attributs_srcset((string) $h['nuages'], '100vw'); ?>
    <img class="hero__fond"   src="<?= e((string) $h['fond']) ?>"<?= attributs_srcset((string) $h['fond'], '100vw') ?>   alt="<?= e((string) $h['alt']) ?>" fetchpriority="high">
    <div class="hero__nuages">
      <img src="<?= e((string) $h['nuages']) ?>"<?= $srcNuages ?> alt=""><img src="<?= e((string) $h['nuages']) ?>"<?= $srcNuages ?> alt="">
    </div>
    <img class="hero__avion"  src="<?= e((string) $h['avion']) ?>"<?= attributs_srcset((string) $h['avion'], '100vw') ?>  alt="Avion du club en vol">
  <?php elseif (!empty($h['cle_image'])): ?>
    <?= image((string) $h['cle_image'], (string) $h['image'], [
          'class' => 'hero__image',
          'alt' => (string) $h['alt'],
          'fetchpriority' => 'high',
          'style' => $stylePosition,
        ]) ?>
  <?php else: ?>
    <img class="hero__image" src="<?= e((string) $h['image']) ?>"<?= attributs_srcset((string) $h['image'], '100vw') ?>
         alt="<?= e((string) $h['alt']) ?>" fetchpriority="high"<?= $stylePosition ? ' style="' . e($stylePosition) . '"' : '' ?>>
  <?php endif; ?>
  </div>

  <?php if (!empty($h['logo'])): ?>
    <img class="hero__logo" src="<?= e((string) $h['logo']) ?>"<?= attributs_srcset((string) $h['logo'], '(max-width:700px) 148px, min(34vw, 470px)', 300) ?>
         alt="<?= e((string) ($h['logo_alt'] ?? 'Saumur Air Club')) ?>"
         width="752" height="184">
  <?php endif; ?>

  <?php if ($estPage): ?>
  <div class="hero__haut">
    <nav class="fil" aria-label="Fil d’Ariane">
      <ol>
        <li><a href="/">Accueil</a></li>
        <li><span aria-current="page"><?= e((string) $h['titre']) ?></span></li>
      </ol>
    </nav>
  </div>
  <?php endif; ?>

  <div class="hero__bas">
    <div class="hero__contenu">
      <?php if (!empty($h['surtitre'])): ?>
        <p class="surtitre"><?= $rendu('surtitre') ?></p>
      <?php endif; ?>

      <h1><?= !empty($h['cle_titre'])
              ? $rendu('titre')
              : ($h['titre_html'] ?? e((string) $h['titre'])) ?></h1>

      <?php if (!empty($h['accroche'])): ?>
        <p class="hero__accroche"><?= $rendu('accroche', 'long') ?></p>
      <?php endif; ?>

      <?php if (!empty($h['actions'])): ?>
        <div class="hero__actions"><?= $h['actions'] ?></div>
      <?php endif; ?>
    </div>

    <?php if (!empty($h['encart'])): $en = $h['encart']; ?>
      <div class="hero__encart">
        <p class="surtitre"><?= e($en['surtitre']) ?></p>
        <p><?= e($en['texte']) ?></p>
        <a class="lien-fleche" href="<?= e($en['lien']) ?>"><?= e($en['libelle']) ?></a>
      </div>
    <?php endif; ?>
  </div>
</section>
