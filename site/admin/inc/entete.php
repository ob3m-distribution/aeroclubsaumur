<?php
declare(strict_types=1);

require_once __DIR__ . '/../../inc/auth.php';

$moi   = membre_connecte();
$titre = $titre ?? 'Back-office';
$actif = $actif ?? '';

/** Entrées du menu, filtrées selon les droits du membre. */
$menu = [
    ['cle' => 'accueil',  'url' => '/admin/',            'libelle' => 'Tableau de bord', 'droit' => null],
    ['cle' => 'bons',     'url' => '/admin/bons.php',    'libelle' => 'Bons cadeaux',    'droit' => 'bons.voir'],
    ['cle' => 'contenus', 'url' => '/admin/contenus.php','libelle' => 'Contenus du site','droit' => 'contenus.gerer'],
    ['cle' => 'suivi',    'url' => '/admin/suivi-membres.php', 'libelle' => 'Adhérents', 'droit' => 'membres.gerer'],
    ['cle' => 'membres',  'url' => '/admin/membres.php', 'libelle' => 'Membres & accès', 'droit' => 'membres.gerer'],
    ['cle' => 'acces',    'url' => '/admin/acces-dossiers.php', 'libelle' => 'Accès bibliothèque', 'droit' => 'membres.gerer'],
    ['cle' => 'biblio',   'url' => '/admin/bibliotheque.php','libelle' => 'Bibliothèque adhérents','droit' => 'contenus.gerer'],
    ['cle' => 'mailing',  'url' => '/admin/mailing.php', 'libelle' => 'Newsletters', 'droit' => 'mailing.gerer'],
    ['cle' => 'support',  'url' => '/admin/support.php', 'libelle' => 'Support', 'droit' => null],
    ['cle' => 'documentation', 'url' => '/admin/documentation.php', 'libelle' => 'Documentation site', 'droit' => 'contenus.gerer'],
];

$msgSucces = $_SESSION['message_succes'] ?? null;
$msgErreur = $_SESSION['message_erreur'] ?? null;
unset($_SESSION['message_succes'], $_SESSION['message_erreur']);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre) ?> — Back-office Saumur Air Club</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<link rel="preload" href="/assets/fonts/Inter-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/admin/assets/admin.css?v=14">
</head>
<body>

<a class="lien-evitement" href="#contenu">Aller au contenu</a>

<div class="bo">

  <aside class="bo__menu" id="menu-admin">
    <div class="bo__marque">
      <span class="marque"><span class="marque__saumur">Saumur</span> <span class="marque__club">Air Club</span></span>
      <span class="bo__sous-marque">Back-office</span>
    </div>

    <nav aria-label="Menu du back-office">
      <ul>
        <?php foreach ($menu as $item): ?>
          <?php if ($item['droit'] !== null && !peut($item['droit'])) continue; ?>
          <li>
            <a href="<?= e($item['url']) ?>"<?= $actif === $item['cle'] ? ' aria-current="page"' : '' ?>>
              <?= e($item['libelle']) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="bo__moi">
      <p class="bo__moi-nom"><?= e($moi['prenom'] . ' ' . $moi['nom']) ?></p>
      <p class="bo__moi-role"><?= e(ROLES[$moi['role']]['libelle'] ?? $moi['role']) ?></p>
      <a class="bo__deconnexion" href="/admin/deconnexion.php">Se déconnecter</a>
    </div>
  </aside>

  <div class="bo__corps">

    <header class="bo__barre">
      <button class="bo__burger" type="button" aria-expanded="false"
              aria-controls="menu-admin" aria-label="Ouvrir le menu">
        <span></span><span></span><span></span>
      </button>
      <h1><?= e($titre) ?></h1>
      <a class="bo__voir-site" href="/" target="_blank" rel="noopener">Voir le site ↗</a>
    </header>

    <main class="bo__contenu" id="contenu">

      <?php if ($msgSucces): ?>
        <div class="message message--succes" role="status"><?= e($msgSucces) ?></div>
      <?php endif; ?>
      <?php if ($msgErreur): ?>
        <div class="message message--erreur" role="alert"><?= e($msgErreur) ?></div>
      <?php endif; ?>
