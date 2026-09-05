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
$rendu = static function (string $champ, string $type = 'court') use ($h): string {
    $valeur = (string) ($h[$champ] ?? '');
    $cle    = $h['cle_' . $champ] ?? null;
    return $cle ? texte((string) $cle, $valeur, $type) : e($valeur);
};
?>
<section class="<?= e($classesHero) ?>">
  <div class="hero__cadre">
  <?php if (!empty($h['fond'])): /* Accueil animé : 3 calques (fond fixe, nuages, avion). */ ?>
    <img class="hero__fond"   src="<?= e((string) $h['fond']) ?>"   alt="<?= e((string) $h['alt']) ?>" fetchpriority="high">
    <div class="hero__nuages">
      <img src="<?= e((string) $h['nuages']) ?>" alt=""><img src="<?= e((string) $h['nuages']) ?>" alt="">
    </div>
    <img class="hero__avion"  src="<?= e((string) $h['avion']) ?>"  alt="Avion du club en vol">
  <?php elseif (!empty($h['cle_image'])): ?>
    <?= image((string) $h['cle_image'], (string) $h['image'], [
          'class' => 'hero__image',
          'alt' => (string) $h['alt'],
          'fetchpriority' => 'high',
        ]) ?>
  <?php else: ?>
    <img class="hero__image" src="<?= e((string) $h['image']) ?>"
         alt="<?= e((string) $h['alt']) ?>" fetchpriority="high">
  <?php endif; ?>
  </div>

  <?php if (!empty($h['logo'])): ?>
    <img class="hero__logo" src="<?= e((string) $h['logo']) ?>"
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
