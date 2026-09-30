<?php
declare(strict_types=1);

/* Téléchargement de la bibliothèque adhérents en un seul ZIP :
   toute la bibliothèque, ou un dossier (?d=ID) avec ses sous-dossiers.
   L'arborescence des dossiers est conservée dans le ZIP. */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/biblio-adherents.php';
require_once __DIR__ . '/../inc/zip-flux.php';
exiger_droit('biblio.gerer');

$sections = biblio_sections_toutes();
$racine   = (int) ($_GET['d'] ?? 0);
if ($racine && !isset($sections[$racine])) {
    $_SESSION['message_erreur'] = 'Dossier introuvable.';
    header('Location: /admin/bibliotheque.php', true, 302);
    exit;
}

/** Nom utilisable comme dossier / fichier sur tous les systèmes. */
$propre = static function (string $s): string {
    $s = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', '-', $s), " .-");
    return $s !== '' ? mb_substr($s, 0, 120) : 'sans-nom';
};

/** Chemin d'un dossier dans le ZIP, relatif au dossier téléchargé. */
$chemin = static function (int $sid) use ($sections, $racine, $propre): string {
    $parts = [];
    for ($c = $sid, $g = 0; $c && isset($sections[$c]) && $g < 30; $g++) {
        $parts[] = $propre($sections[$c]['nom']);
        if ($c === $racine) break;
        $c = (int) ($sections[$c]['parent_id'] ?? 0);
    }
    return implode('/', array_reverse($parts));
};

/** Le dossier $sid fait-il partie de ce qu'on télécharge ? */
$inclus = static function (int $sid) use ($sections, $racine): bool {
    if (!$racine) return true;
    for ($c = $sid, $g = 0; $c && $g < 30; $g++) {
        if ($c === $racine) return true;
        $c = (int) ($sections[$c]['parent_id'] ?? 0);
    }
    return false;
};

$base = realpath(__DIR__ . '/../docs-adherents') ?: '';
$fichiers = [];
foreach (db()->query('SELECT section_id, nom, fichier, type FROM biblio_documents ORDER BY section_id, position, id') as $d) {
    $sid = (int) $d['section_id'];
    if (!isset($sections[$sid]) || !$inclus($sid)) continue;
    $reel = realpath($base . '/' . $d['fichier']);
    // Jamais en dehors de docs-adherents (même garde-fou que doc-adherent.php).
    if ($base === '' || $reel === false || strncmp($reel, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0) {
        $reel = '';
    }
    $fichiers[] = [
        'nom'    => $chemin($sid) . '/' . $propre((string) $d['nom']) . '.' . strtolower((string) $d['type']),
        'chemin' => $reel,
    ];
}

[$entrees, $absents] = zip_preparer($fichiers);
if (!$entrees) {
    $_SESSION['message_erreur'] = 'Aucun document à télécharger dans ce dossier.';
    header('Location: /admin/bibliotheque.php' . ($racine ? '?d=' . $racine : ''), true, 302);
    exit;
}
if ($absents) {
    error_log('Bibliothèque ZIP : ' . count($absents) . ' fichier(s) absent(s) du serveur : ' . implode(', ', $absents));
}

$nomZip = 'bibliotheque-saumur-air-club' . ($racine ? '-' . $propre($sections[$racine]['nom']) : '')
        . '-' . date('Y-m-d') . '.zip';
journaliser('biblio.zip', $racine ? 'section#' . $racine : 'bibliotheque', count($entrees) . ' documents');
session_write_close();   // ne pas bloquer les autres pages pendant le téléchargement

zip_envoyer($entrees, $nomZip);
