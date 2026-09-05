<?php
declare(strict_types=1);
$page = 'index';
$description = 'Aéro-club de Saumur, au cœur du Val de Loire. Vol découverte, vol d’initiation et formation au pilotage sur l’aérodrome de Saumur Terrefort.';
require __DIR__ . '/inc/header.php';

$hero = [
  'variante'   => 'accueil',   // hero animé en 3 calques
  'fond'       => '/assets/img/accueil-fond.jpg',       // décor fixe
  'nuages'     => '/assets/img/accueil-nuages.png',     // nuages qui dérivent
  'avion'      => '/assets/img/accueil-avion-detoure.png', // avion qui arrive de loin
  'alt'        => 'Le Val de Loire vu du ciel, un avion du Saumur Air Club en approche',
  'titre'      => 'Le ciel n’est pas la limite, c’est notre terrain de jeu',
  'cle_titre'  => 'accueil.hero.titre',
  'cle_titre'  => 'accueil.hero.titre',
  'logo'       => '/assets/img/logo-blanc.png',
  'logo_alt'   => 'Saumur Air Club',
  'actions'    =>
      '<a class="bouton bouton--or" href="' . e(url('vols-decouvertes')) . '">Vol découverte</a>'
    . '<a class="bouton bouton--fantome" href="' . e(url('vols-decouvertes')) . '#vol-initiation">Vol d’initiation</a>'
    . '<a class="bouton bouton--fantome" href="' . e(url('tarifs-inscriptions')) . '">Apprendre à piloter</a>',
];
require __DIR__ . '/inc/hero.php';
?>

<?php require __DIR__ . '/inc/footer.php'; ?>
