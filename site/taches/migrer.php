<?php
declare(strict_types=1);

/* ==================================================================
   Mises à jour de la base de données, appliquées automatiquement.

   Appelé par le workflow de déploiement (.github/workflows/deploy.yml)
   juste après la mise en ligne : plus besoin de copier-coller du SQL
   dans phpMyAdmin.

   Chaque fichier de site/migrations/ (ordre alphabétique : préfixe
   numérique) n'est exécuté qu'UNE fois ; les fichiers déjà passés sont
   notés dans la table migrations_appliquees. Au premier échec, on
   s'arrête : les suivants attendent le prochain déploiement.

   Les fichiers doivent rester rejouables sans dégât (IF NOT EXISTS,
   INSERT IGNORE…) : si l'un échoue à mi-chemin, il sera rejoué en
   entier au déploiement suivant.

   Protégé par le secret d'ops (même secret que notifier.php).
   POST : k=<secret>
   ================================================================== */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: text/plain; charset=utf-8');

$secret = defined('SAUMUR_EXPORT_SECRET') ? SAUMUR_EXPORT_SECRET : '';
$fourni = (string) ($_POST['k'] ?? ($_SERVER['HTTP_X_EXPORT_SECRET'] ?? ''));
if ($secret === '' || !hash_equals($secret, $fourni)) {
    http_response_code(403);
    exit("forbidden\n");
}

set_time_limit(300);

/**
 * Découpe un fichier SQL en instructions. Convention des fichiers de
 * migrations/ : une instruction se termine par « ; » en fin de ligne ;
 * les lignes « -- » sont des commentaires.
 */
function instructions_sql(string $sql): array
{
    $lignes = [];
    foreach (preg_split('/\R/', $sql) as $l) {
        if (preg_match('/^\s*--/', $l)) continue;
        $lignes[] = $l;
    }
    $out = [];
    foreach (preg_split('/;\s*$/m', implode("\n", $lignes)) as $i) {
        if (trim($i) !== '') $out[] = trim($i);
    }
    return $out;
}

$pdo = db();
$pdo->exec('CREATE TABLE IF NOT EXISTS migrations_appliquees (
    nom         VARCHAR(190) NOT NULL PRIMARY KEY,
    applique_le DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$faites = $pdo->query('SELECT nom FROM migrations_appliquees')->fetchAll(PDO::FETCH_COLUMN);
$fichiers = glob(__DIR__ . '/../migrations/*.sql') ?: [];
sort($fichiers, SORT_STRING);

$note = $pdo->prepare('INSERT INTO migrations_appliquees (nom) VALUES (?)');
$nb = 0;
foreach ($fichiers as $f) {
    $nom = basename($f);
    if (in_array($nom, $faites, true)) continue;
    try {
        foreach (instructions_sql((string) file_get_contents($f)) as $instr) {
            $st = $pdo->query($instr);
            // Vider un éventuel résultat (SELECT de contrôle) avant la suite.
            if ($st instanceof PDOStatement) {
                do { try { $st->fetchAll(); } catch (Throwable $e) { break; } } while ($st->nextRowset());
                $st->closeCursor();
            }
        }
        $note->execute([$nom]);
        echo "OK      $nom\n";
        $nb++;
    } catch (Throwable $e) {
        http_response_code(500);
        error_log("Migration $nom : " . $e->getMessage());
        echo "ECHEC   $nom : " . $e->getMessage() . "\n";
        exit;
    }
}
echo $nb ? "$nb migration(s) appliquée(s).\n" : "Base à jour, rien à faire.\n";
