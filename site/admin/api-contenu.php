<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/contenu.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Réponse JSON et fin.
 * On ne remplace le code HTTP que s'il vaut encore 200 : sinon un 401 ou
 * un 403 déjà positionné serait écrasé par un 400 trompeur.
 */
function repondre(bool $ok, ?string $erreur = null, array $extra = []): never
{
    if (!$ok && http_response_code() < 400) {
        http_response_code(400);
    }
    echo json_encode(['ok' => $ok, 'erreur' => $erreur] + $extra,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    repondre(false, 'Méthode non autorisée.');
}

session_demarrer();

if (!est_connecte()) {
    http_response_code(401);
    repondre(false, 'Vous n’êtes plus connecté. Rechargez la page.');
}
if (!peut('contenus.gerer')) {
    http_response_code(403);
    repondre(false, 'Seul un super administrateur peut modifier les contenus.');
}

// Le jeton peut venir de l'en-tête (appel fetch) ou du corps.
$jeton = $_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? null);
if (!jeton_csrf_valide(is_string($jeton) ? $jeton : null)) {
    http_response_code(403);
    repondre(false, 'Session expirée. Rechargez la page.');
}

$cle = trim((string) ($_POST['cle'] ?? ''));
// Clé stricte : lettres, chiffres, points, tirets. Rien d'autre.
if ($cle === '' || !preg_match('/^[a-z0-9]([a-z0-9._-]{0,98}[a-z0-9])?$/i', $cle)) {
    repondre(false, 'Identifiant de contenu invalide.');
}

try {
    if (!empty($_POST['reinitialiser'])) {
        reinitialiser_contenu($cle);
        journaliser('contenu.reinitialise', $cle);
        repondre(true, null, ['message' => 'Contenu d’origine rétabli.']);
    }

    $valeur = (string) ($_POST['valeur'] ?? '');
    $type   = (string) ($_POST['type'] ?? 'texte');
    if (!in_array($type, ['texte', 'texte_long', 'image'], true)) {
        $type = 'texte';
    }

    // Caractères de contrôle retirés ; les retours à la ligne sont conservés
    // pour les textes longs.
    $valeur = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valeur);
    if ($type !== 'texte_long') {
        $valeur = trim(preg_replace('/\s+/u', ' ', $valeur));
    } else {
        $valeur = trim($valeur);
    }

    if (mb_strlen($valeur) > 20000) {
        repondre(false, 'Ce texte est trop long (20 000 caractères maximum).');
    }

    // Champ vidé = retour à la valeur d'origine, plutôt qu'un blanc sur le site.
    if ($valeur === '') {
        reinitialiser_contenu($cle);
        journaliser('contenu.reinitialise', $cle);
        repondre(true, null, ['message' => 'Contenu d’origine rétabli.']);
    }

    definir_contenu($cle, $valeur, $type);
    journaliser('contenu.modifie', $cle, mb_substr($valeur, 0, 120));
    repondre(true, null, ['message' => 'Modification enregistrée.']);

} catch (Throwable $e) {
    error_log('api-contenu : ' . $e->getMessage());
    http_response_code(500);
    repondre(false, 'Erreur technique, modification non enregistrée.');
}
