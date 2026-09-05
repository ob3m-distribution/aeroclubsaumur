<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

/* ==================================================================
   Rôles et autorisations.

   Les autorisations sont définies ICI, pas en base : on les lit d'un
   coup d'œil, et on ne peut pas se retrouver avec un rôle orphelin.
   ================================================================== */

const AUTORISATIONS = [
    'bons.voir'      => 'Consulter les bons cadeaux',
    'bons.gerer'     => 'Modifier les bons (marquer utilisé, annuler, renvoyer)',
    'contenus.gerer' => 'Modifier les textes et les photos du site',
    'membres.gerer'  => 'Gérer les membres et leurs accès',
    'mailing.gerer'  => 'Envoyer des e-mails groupés aux membres',
];

const ROLES = [
    'superadmin' => [
        'libelle'     => 'Super administrateur',
        'description' => 'Accès total, seul habilité à modifier les textes et les photos du site.',
        'droits'      => ['bons.voir', 'bons.gerer', 'contenus.gerer', 'membres.gerer', 'mailing.gerer'],
    ],
    'administrateur' => [
        'libelle'     => 'Administrateur',
        'description' => 'Gère les bons cadeaux, les membres et les envois groupés. Ne touche pas aux contenus du site.',
        'droits'      => ['bons.voir', 'bons.gerer', 'membres.gerer', 'mailing.gerer'],
    ],
    'secretariat' => [
        'libelle'     => 'Secrétariat',
        'description' => 'Gère les bons cadeaux.',
        'droits'      => ['bons.voir', 'bons.gerer'],
    ],
    'instructeur' => [
        'libelle'     => 'Pilote / instructeur',
        'description' => 'Consulte les bons cadeaux.',
        'droits'      => ['bons.voir'],
    ],
    'lecture' => [
        'libelle'     => 'Lecture seule',
        'description' => 'Consulte sans rien modifier.',
        'droits'      => ['bons.voir'],
    ],
    'adherent' => [
        'libelle'     => 'Adhérent',
        'description' => 'Membre du club : accès à l’espace adhérents, aucun accès au back-office.',
        'droits'      => [],
    ],
];

/* ---------- Session du back-office ---------------------------------- */

function membre_connecte(): ?array
{
    session_demarrer();
    static $membre = null;
    static $cherche = false;

    if ($cherche) {
        return $membre;
    }
    $cherche = true;

    $id = (int) ($_SESSION['membre_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM membres WHERE id = ? AND actif = 1 LIMIT 1');
    $stmt->execute([$id]);
    $m = $stmt->fetch();

    // Compte désactivé entre-temps : la session ne doit plus valoir.
    if (!$m) {
        unset($_SESSION['membre_id']);
        return null;
    }

    $membre = $m;
    return $membre;
}

function est_connecte(): bool
{
    return membre_connecte() !== null;
}

/** Le membre courant a-t-il cette autorisation ? */
function peut(string $droit): bool
{
    $m = membre_connecte();
    if (!$m) {
        return false;
    }
    $role = ROLES[$m['role']] ?? null;
    return $role !== null && in_array($droit, $role['droits'], true);
}

/** Barrière : à appeler en tête de chaque page du back-office. */
function exiger_droit(string $droit): void
{
    if (!est_connecte()) {
        $_SESSION['apres_connexion'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
        header('Location: /admin/connexion.php', true, 302);
        exit;
    }
    if (!peut($droit)) {
        http_response_code(403);
        $_SESSION['message_erreur'] = 'Vous n’avez pas l’autorisation d’accéder à cette page.';
        header('Location: /admin/', true, 302);
        exit;
    }
}

/** Le membre courant est-il super administrateur ? */
function est_superadmin(): bool
{
    $m = membre_connecte();
    return $m !== null && $m['role'] === 'superadmin';
}

/**
 * Rôles que le membre courant peut attribuer.
 * Seul un super administrateur peut en créer un autre : sinon un simple
 * administrateur se promeut lui-même et le garde-fou ne sert à rien.
 */
function roles_attribuables(): array
{
    $tous = ROLES;
    if (!est_superadmin()) {
        unset($tous['superadmin']);
    }
    return $tous;
}

/** Barrière minimale : connecté, sans droit particulier. */
function exiger_connexion(): void
{
    if (!est_connecte()) {
        session_demarrer();
        $_SESSION['apres_connexion'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
        header('Location: /admin/connexion.php', true, 302);
        exit;
    }
}

/* ---------- Connexion ------------------------------------------------ */

const MAX_ECHECS = 5;
const BLOCAGE_MINUTES = 15;

/**
 * Tente une connexion.
 * Retourne [membre|null, message d'erreur|null].
 */
function tenter_connexion(string $email, string $motDePasse): array
{
    $stmt = db()->prepare('SELECT * FROM membres WHERE email = ? LIMIT 1');
    $stmt->execute([mb_strtolower(trim($email))]);
    $m = $stmt->fetch();

    // Message volontairement identique dans tous les cas : ne pas révéler
    // quels emails existent.
    $refus = 'Identifiants incorrects.';

    if (!$m) {
        // On hache quand même, pour que la réponse mette le même temps.
        password_verify($motDePasse, '$2y$10$abcdefghijklmnopqrstuv');
        return [null, $refus];
    }

    if ((int) $m['actif'] !== 1) {
        return [null, 'Ce compte est désactivé. Contactez un administrateur.'];
    }

    if ($m['bloque_jusqua'] !== null && strtotime((string) $m['bloque_jusqua']) > time()) {
        $reste = (int) ceil((strtotime((string) $m['bloque_jusqua']) - time()) / 60);
        return [null, "Trop de tentatives. Réessayez dans {$reste} minute" . ($reste > 1 ? 's' : '') . '.'];
    }

    if (!password_verify($motDePasse, $m['mot_de_passe_hash'])) {
        $echecs = (int) $m['echecs_connexion'] + 1;
        if ($echecs >= MAX_ECHECS) {
            $maj = db()->prepare(
                'UPDATE membres SET echecs_connexion = 0,
                        bloque_jusqua = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?'
            );
            $maj->execute([BLOCAGE_MINUTES, $m['id']]);
            return [null, 'Trop de tentatives. Compte bloqué ' . BLOCAGE_MINUTES . ' minutes.'];
        }
        $maj = db()->prepare('UPDATE membres SET echecs_connexion = ? WHERE id = ?');
        $maj->execute([$echecs, $m['id']]);
        return [null, $refus];
    }

    // Succès : on remet les compteurs à zéro.
    $maj = db()->prepare(
        'UPDATE membres SET echecs_connexion = 0, bloque_jusqua = NULL,
                derniere_connexion = NOW() WHERE id = ?'
    );
    $maj->execute([$m['id']]);

    session_demarrer();
    // Nouvelle identité de session : parade au vol de session.
    session_regenerate_id(true);
    $_SESSION['membre_id'] = (int) $m['id'];

    journaliser('connexion', 'membre#' . $m['id'], $m['email']);

    return [$m, null];
}

function deconnecter(): void
{
    session_demarrer();
    $m = membre_connecte();
    if ($m) {
        journaliser('deconnexion', 'membre#' . $m['id'], $m['email']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---------- Mot de passe fort ---------------------------------------- */

/**
 * Vérifie la robustesse d'un mot de passe.
 * Règle : 8 caractères min, une majuscule, une minuscule, un chiffre,
 * un caractère spécial. Retourne un message d'erreur, ou null si OK.
 */
function mot_de_passe_valide(string $mdp): ?string
{
    if (mb_strlen($mdp) < 8)              return 'Le mot de passe doit contenir au moins 8 caractères.';
    if (!preg_match('/[A-ZÀ-Þ]/u', $mdp)) return 'Il faut au moins une majuscule.';
    if (!preg_match('/[a-zà-ÿ]/u', $mdp)) return 'Il faut au moins une minuscule.';
    if (!preg_match('/\d/', $mdp))        return 'Il faut au moins un chiffre.';
    if (!preg_match('/[^\p{L}\p{N}]/u', $mdp)) return 'Il faut au moins un caractère spécial (ex. ! ? @ # …).';
    return null;
}

/* ---------- Réinitialisation / définition du mot de passe ------------ */

/**
 * Crée un jeton de réinitialisation pour un membre et le retourne (brut).
 * $heures : durée de validité. Sert au « mot de passe oublié » ET à
 * l'invitation d'un nouveau membre (première connexion).
 */
function creer_token_reset(int $membreId, int $heures = 24): string
{
    $token = bin2hex(random_bytes(32));
    db()->prepare('UPDATE membres SET reset_token = ?, reset_expire = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE id = ?')
        ->execute([hash('sha256', $token), $heures, $membreId]);
    return $token;
}

/** Membre correspondant à un jeton valide (non expiré), ou null. */
function membre_par_token_reset(string $token): ?array
{
    if ($token === '') return null;
    $stmt = db()->prepare('SELECT * FROM membres WHERE reset_token = ? AND reset_expire > NOW() LIMIT 1');
    $stmt->execute([hash('sha256', $token)]);
    $m = $stmt->fetch();
    return $m ?: null;
}

/** Définit le mot de passe d'un membre et consomme son jeton. */
function definir_mot_de_passe(int $membreId, string $mdp): void
{
    db()->prepare('UPDATE membres SET mot_de_passe_hash = ?, reset_token = NULL, reset_expire = NULL,
            echecs_connexion = 0, bloque_jusqua = NULL, actif = 1 WHERE id = ?')
        ->execute([password_hash($mdp, PASSWORD_DEFAULT), $membreId]);
}

/* ---------- Journal --------------------------------------------------- */

function journaliser(string $action, ?string $cible = null, ?string $detail = null): void
{
    try {
        $m = $_SESSION['membre_id'] ?? null;
        $stmt = db()->prepare(
            'INSERT INTO journal (membre_id, action, cible, detail, ip) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([
            $m ? (int) $m : null,
            $action,
            $cible,
            $detail,
            @inet_pton(ip_client()) ?: null,
        ]);
    } catch (Throwable $e) {
        // Le journal ne doit jamais faire échouer une action métier.
        error_log('Journal : ' . $e->getMessage());
    }
}

/* ---------- Paramètres ------------------------------------------------ */

function parametre(string $cle, ?string $defaut = null): ?string
{
    static $cache = [];
    if (array_key_exists($cle, $cache)) {
        return $cache[$cle] ?? $defaut;
    }
    $stmt = db()->prepare('SELECT valeur FROM parametres WHERE cle = ? LIMIT 1');
    $stmt->execute([$cle]);
    $v = $stmt->fetchColumn();
    $cache[$cle] = ($v === false) ? null : (string) $v;
    return $cache[$cle] ?? $defaut;
}

function definir_parametre(string $cle, ?string $valeur): void
{
    $m = $_SESSION['membre_id'] ?? null;
    $stmt = db()->prepare(
        'INSERT INTO parametres (cle, valeur, modifie_par) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), modifie_par = VALUES(modifie_par)'
    );
    $stmt->execute([$cle, $valeur, $m ? (int) $m : null]);
}
