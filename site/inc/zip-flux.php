<?php
declare(strict_types=1);

/* ==================================================================
   ZIP envoyé au fil de l'eau (sans fichier temporaire ni ZipArchive).

   Les fichiers sont « stockés » (méthode 0, sans compression) : la
   bibliothèque ne contient que des PDF, déjà compressés — compresser
   coûterait du temps serveur pour presque rien. Le CRC de chaque
   fichier est calculé avant son envoi, ce qui permet d'annoncer la
   taille totale exacte (Content-Length) : le navigateur affiche une
   vraie barre de progression.

   Limite : format ZIP classique (pas ZIP64), soit moins de 4 Go au
   total et 65 535 fichiers — très au-delà de la bibliothèque du club.
   ================================================================== */

/**
 * Prépare la liste des entrées : [['nom' => chemin/dans/le/zip.pdf,
 * 'chemin' => fichier sur le disque, 'taille' => octets, 'crc' => int]].
 * Les fichiers absents du disque sont ignorés et renvoyés à part.
 *
 * @param array $fichiers [['nom' => ..., 'chemin' => ...], ...]
 * @return array{0: array, 1: array} [entrées valides, noms manquants]
 */
function zip_preparer(array $fichiers): array
{
    $entrees = [];
    $absents = [];
    $vus = [];
    foreach ($fichiers as $f) {
        $chemin = (string) $f['chemin'];
        if (!is_file($chemin) || !is_readable($chemin)) {
            $absents[] = (string) $f['nom'];
            continue;
        }
        // Deux documents de même nom dans un dossier : « nom (2).pdf ».
        $nom = (string) $f['nom'];
        $base = $nom;
        for ($i = 2; isset($vus[mb_strtolower($nom)]); $i++) {
            $ext = pathinfo($base, PATHINFO_EXTENSION);
            $nom = ($ext !== '' ? substr($base, 0, -strlen($ext) - 1) : $base) . " ($i)" . ($ext !== '' ? ".$ext" : '');
        }
        $vus[mb_strtolower($nom)] = true;
        $entrees[] = [
            'nom'    => $nom,
            'chemin' => $chemin,
            'taille' => (int) filesize($chemin),
            'crc'    => (int) hexdec((string) hash_file('crc32b', $chemin)),
            'mtime'  => (int) filemtime($chemin),
        ];
    }
    return [$entrees, $absents];
}

/** Date et heure au format DOS (champs du ZIP). */
function zip_date_dos(int $ts): array
{
    $d = getdate($ts);
    $annee = max(1980, $d['year']);
    return [
        ($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2),
        (($annee - 1980) << 9) | ($d['mon'] << 5) | $d['mday'],
    ];
}

/** Taille exacte du ZIP qui sera produit (pour Content-Length). */
function zip_taille_totale(array $entrees): int
{
    $total = 22;                                   // fin du répertoire central
    foreach ($entrees as $e) {
        $n = strlen($e['nom']);
        $total += 30 + $n + $e['taille'];          // en-tête local + données
        $total += 46 + $n;                         // entrée du répertoire central
    }
    return $total;
}

/** Envoie le ZIP au navigateur, fichier par fichier. */
function zip_envoyer(array $entrees, string $nomArchive): void
{
    if (zip_taille_totale($entrees) > 0xFFFFFFFF || count($entrees) > 0xFFFF) {
        throw new RuntimeException('Archive trop volumineuse pour le format ZIP simple.');
    }

    @set_time_limit(0);
    @ini_set('zlib.output_compression', '0');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . rawurlencode($nomArchive) . '"; filename*=UTF-8\'\'' . rawurlencode($nomArchive));
    header('Content-Length: ' . zip_taille_totale($entrees));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');

    $central = '';
    $offset  = 0;
    foreach ($entrees as $e) {
        [$heure, $date] = zip_date_dos($e['mtime']);
        $n = strlen($e['nom']);
        // Bit 11 : noms en UTF-8 (accents corrects sous Windows / macOS).
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $heure, $date,
                      $e['crc'], $e['taille'], $e['taille'], $n, 0) . $e['nom'];
        echo $local;
        $h = fopen($e['chemin'], 'rb');
        while ($h && !feof($h)) {
            echo fread($h, 1048576);
            flush();
            if (connection_aborted()) {
                fclose($h);
                return;
            }
        }
        if ($h) fclose($h);

        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $heure, $date,
                         $e['crc'], $e['taille'], $e['taille'], $n, 0, 0, 0, 0, 0, $offset) . $e['nom'];
        $offset += strlen($local) + $e['taille'];
    }
    echo $central;
    echo pack('VvvvvVVv', 0x06054b50, 0, 0, count($entrees), count($entrees), strlen($central), $offset, 0);
    flush();
}
