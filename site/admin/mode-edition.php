<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/contenu.php';
exiger_droit('contenus.gerer');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !jeton_csrf_valide($_POST['csrf'] ?? null)) {
    header('Location: /admin/contenus.php', true, 303);
    exit;
}

$action = (string) ($_POST['action'] ?? '');

if ($action === 'terminer') {
    unset($_SESSION['mode_edition_depuis']);
    journaliser('edition.terminee');
    $_SESSION['message_succes'] = 'Modifications terminées. Le site est de nouveau en consultation.';
    header('Location: /admin/contenus.php', true, 303);
    exit;
}

/* Activation : c'est le SEUL chemin possible, et il part du back-office. */
$_SESSION['mode_edition_depuis'] = time();
journaliser('edition.demarree');

// On ne se rend que vers une page interne du site.
$page = (string) ($_POST['page'] ?? '/');
if ($page === '' || $page[0] !== '/' || str_starts_with($page, '//') || str_starts_with($page, '/admin')) {
    $page = '/';
}

header('Location: ' . $page, true, 303);
exit;
