<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/contenu.php';

/* La session doit demarrer AVANT la moindre sortie HTML : sinon PHP ne peut
   plus envoyer le cookie, et le mode edition ne s'active jamais. On ne
   l'ouvre que si un cookie existe deja (un visiteur anonyme n'en a pas besoin). */
if (!empty($_COOKIE[session_name()]) && session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/auth.php';
    session_demarrer();
}

/** Chaque page definit $page (slug) et, si besoin, $titre / $description. */
$page        = $page        ?? 'index';
/* Les pages ci-dessous n'ont pas de hero sombre : l'en-tête y prend un fond
   plein (burger foncé), sinon il reste transparent et posé sur le hero. */
$avecHero    = !in_array($page, ['adherents', 'reinscription', 'preinscription', 'paiement-inscription', 'mot-de-passe-oublie', 'reinitialiser-mot-de-passe', 'merci', 'paiement', '404'], true);
$titre       = $titre       ?? (PAGES[$page]['titre'] ?? 'Saumur Air Club');
$description = $description ?? 'Aéro-club de Saumur, aérodrome de Saumur Terrefort. '
             . 'Vols découverte et d’initiation, formation au pilotage, bons cadeaux.';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
require_once __DIR__ . '/seo.php';
$titreComplet = $titre . ' — ' . CLUB['nom'];
$imagePartage = $imagePartage ?? null;
?>
<title><?= e($titreComplet) ?></title>
<meta name="description" content="<?= e($description) ?>">
<link rel="canonical" href="<?= e(url_canonique()) ?>">
<?php if (site_en_dev()): ?>
<!-- Espace de developpement : jamais indexe. Bascule automatique en production
     (le mode est deduit du nom d'hote « dev. »). -->
<meta name="robots" content="noindex, nofollow">
<?php else: ?>
<meta name="robots" content="index, follow, max-image-preview:large">
<?php endif; ?>
<meta name="theme-color" content="#14294D">
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<?php require_once __DIR__ . '/polices.php'; ?>
<link rel="preload" href="<?= e(police_preload()) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/css/style.css?v=69">
<style><?= police_css() ?></style>
<?= balises_partage($titreComplet, $description, $imagePartage) ?>
<?= donnees_structurees($page) ?>
</head>
<body>

<a class="lien-evitement" href="#contenu">Aller au contenu</a>

<header class="entete<?= $avecHero ? '' : ' entete--plein' ?>">
  <div class="entete__inner">
    <button class="burger" type="button" aria-expanded="false"
            aria-controls="menu-principal" aria-label="Ouvrir le menu">
      <span></span><span></span><span></span>
    </button>

    <nav class="nav" id="menu-principal" aria-label="Menu principal">
      <a class="nav__logo" href="/" aria-label="<?= e(CLUB['nom']) ?> — accueil">
        <img src="/assets/img/logo.png?v=2" alt="<?= e(CLUB['nom']) ?>" width="752" height="184">
      </a>
      <ul>
        <?php foreach (PAGES as $slug => $p): ?>
          <li>
            <a href="<?= e(url($slug)) ?>"<?= $slug === $page ? ' aria-current="page"' : '' ?>>
              <?= e($p['menu']) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
  </div>
</header>

<div class="nav-voile" id="nav-voile" aria-hidden="true"></div>

<main id="contenu">
