<?php
declare(strict_types=1);

require_once __DIR__ . '/config-local.php';

/**
 * Connexion PDO, ouverte une seule fois par requete.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        BDD['hote'],
        BDD['port'],
        BDD['base']
    );

    $pdo = new PDO($dsn, BDD['utilisateur'], BDD['mot_de_passe'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Vraies requetes preparees cote serveur, pas d'emulation.
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 10,
    ]);

    return $pdo;
}
