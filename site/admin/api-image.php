<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/contenu.php';

header('Content-Type: application/json; charset=utf-8');

/** Ne remplace le code HTTP que s'il vaut encore 200 : un 401/403 deja
    positionne ne doit pas etre ecrase par un 400 trompeur. */
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
    repondre(false, 'Seul un super administrateur peut modifier les photos.');
}

$jeton = $_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? null);
if (!jeton_csrf_valide(is_string($jeton) ? $jeton : null)) {
    http_response_code(403);
    repondre(false, 'Session expirée. Rechargez la page.');
}

$cle = trim((string) ($_POST['cle'] ?? ''));
if ($cle === '' || !preg_match('/^[a-z0-9]([a-z0-9._-]{0,98}[a-z0-9])?$/i', $cle)) {
    repondre(false, 'Identifiant de contenu invalide.');
}

if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    repondre(false, 'Aucune image reçue.');
}

$f = $_FILES['image'];
if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $messages = [
        UPLOAD_ERR_INI_SIZE   => 'Image trop lourde pour le serveur.',
        UPLOAD_ERR_FORM_SIZE  => 'Image trop lourde.',
        UPLOAD_ERR_PARTIAL    => 'Envoi interrompu, réessayez.',
        UPLOAD_ERR_NO_FILE    => 'Aucune image reçue.',
        UPLOAD_ERR_NO_TMP_DIR => 'Erreur serveur (dossier temporaire).',
        UPLOAD_ERR_CANT_WRITE => 'Erreur serveur (écriture).',
    ];
    repondre(false, $messages[$f['error']] ?? 'Envoi impossible.');
}

// Ne jamais faire confiance au chemin annoncé par le navigateur.
if (!is_uploaded_file($f['tmp_name'])) {
    repondre(false, 'Fichier invalide.');
}

const MAX_OCTETS = 8 * 1024 * 1024;
if ((int) $f['size'] > MAX_OCTETS) {
    repondre(false, 'Cette image dépasse 8 Mo.');
}

/* Le type déclaré par le navigateur ne prouve rien : on lit l'image
   pour de bon. Un fichier qui n'en est pas une échoue ici. */
$info = @getimagesize($f['tmp_name']);
if ($info === false) {
    repondre(false, 'Ce fichier n’est pas une image valide.');
}
[$largeur, $hauteur, $typeImage] = $info;

$autorises = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
if (!in_array($typeImage, $autorises, true)) {
    repondre(false, 'Format non accepté. Utilisez JPEG, PNG ou WebP.');
}
if ($largeur < 200 || $hauteur < 150) {
    repondre(false, 'Cette image est trop petite (200 × 150 pixels minimum).');
}
if ($largeur * $hauteur > 60000000) {
    repondre(false, 'Cette image a une définition trop élevée.');
}

if (!extension_loaded('gd')) {
    http_response_code(500);
    repondre(false, 'Traitement d’image indisponible sur le serveur.');
}

/* Ré-encodage complet via GD : c'est aussi ce qui neutralise tout
   contenu indésirable qui serait caché dans le fichier d'origine. */
$source = match ($typeImage) {
    IMAGETYPE_JPEG => @imagecreatefromjpeg($f['tmp_name']),
    IMAGETYPE_PNG  => @imagecreatefrompng($f['tmp_name']),
    IMAGETYPE_WEBP => @imagecreatefromwebp($f['tmp_name']),
    default        => false,
};
if ($source === false) {
    repondre(false, 'Image illisible.');
}

// Redimensionnement : au-delà de 1920 px de large, aucun gain visible.
const LARGEUR_MAX = 1920;
if ($largeur > LARGEUR_MAX) {
    $nouvelleHauteur = (int) round($hauteur * LARGEUR_MAX / $largeur);
    $redim = imagecreatetruecolor(LARGEUR_MAX, $nouvelleHauteur);
    imagecopyresampled($redim, $source, 0, 0, 0, 0,
        LARGEUR_MAX, $nouvelleHauteur, $largeur, $hauteur);
    imagedestroy($source);
    $source = $redim;
    $largeur = LARGEUR_MAX;
    $hauteur = $nouvelleHauteur;
}

$dossier = __DIR__ . '/../assets/img/contenus';
if (!is_dir($dossier) && !@mkdir($dossier, 0755, true) && !is_dir($dossier)) {
    http_response_code(500);
    repondre(false, 'Impossible de créer le dossier des photos.');
}

// Nom construit par nous seuls, à partir de la clé : jamais celui du fichier reçu.
$base = preg_replace('/[^a-z0-9]+/i', '-', $cle) . '-' . bin2hex(random_bytes(4));
$cheminJpg  = $dossier . '/' . $base . '.jpg';
$cheminWebp = $dossier . '/' . $base . '.webp';

// Fond blanc : sinon la transparence d'un PNG vire au noir en JPEG.
$plat = imagecreatetruecolor($largeur, $hauteur);
imagefill($plat, 0, 0, imagecolorallocate($plat, 255, 255, 255));
imagealphablending($plat, true);
imagecopy($plat, $source, 0, 0, 0, 0, $largeur, $hauteur);

$okJpg = imagejpeg($plat, $cheminJpg, 82);
// Le WebP est servi automatiquement par le .htaccess aux navigateurs récents.
@imagewebp($plat, $cheminWebp, 82);

imagedestroy($plat);
imagedestroy($source);

if (!$okJpg) {
    http_response_code(500);
    repondre(false, 'Enregistrement de l’image impossible.');
}

$url = '/assets/img/contenus/' . $base . '.jpg';

try {
    // On retient l'ancienne image pour la supprimer une fois la bascule faite.
    $ancienne = contenu_brut($cle, '');

    definir_contenu($cle, $url, 'image');
    journaliser('contenu.image', $cle, $url);

    if ($ancienne !== '' && str_starts_with($ancienne, '/assets/img/contenus/')) {
        $vieux = __DIR__ . '/..' . $ancienne;
        $vieuxWebp = preg_replace('/\.jpg$/', '.webp', $vieux);
        if (is_file($vieux)) { @unlink($vieux); }
        if ($vieuxWebp && is_file($vieuxWebp)) { @unlink($vieuxWebp); }
    }

    repondre(true, null, ['url' => $url, 'message' => 'Photo remplacée.']);
} catch (Throwable $e) {
    error_log('api-image : ' . $e->getMessage());
    @unlink($cheminJpg);
    @unlink($cheminWebp);
    http_response_code(500);
    repondre(false, 'Erreur technique, photo non enregistrée.');
}
