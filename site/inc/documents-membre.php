<?php
declare(strict_types=1);

/* Mises à jour de documents (licence pilote, certificat médical) déposées
   par un membre depuis son espace adhérents, en dehors de la réadhésion.
   Historique conservé dans membre_documents ; fichiers sous
   docs-inscriptions/<id membre>/ (dossier privé, servi par
   admin/doc-inscription.php). */

require_once __DIR__ . '/db.php';

const MEMBRE_DOCS_TYPES = ['licence' => 'Licence pilote', 'medicale' => 'Certificat médical'];

/**
 * Enregistre un document envoyé. Retourne [id, null] ou [null, message d'erreur].
 * $fichier : entrée de $_FILES ; $validite : date saisie par le membre (AAAA-MM-JJ).
 */
function membre_doc_enregistrer(int $membreId, string $type, array $fichier, string $validite): array
{
    if (!isset(MEMBRE_DOCS_TYPES[$type])) return [null, 'Type de document inconnu.'];
    $d = DateTime::createFromFormat('!Y-m-d', $validite);
    if (!$d || $d->format('Y-m-d') !== $validite) return [null, 'Indiquez la date de validité du document.'];
    if ($d < new DateTime('today')) return [null, 'La date de validité est dépassée.'];
    if ($d > new DateTime('+15 years')) return [null, 'La date de validité semble erronée.'];

    if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $fichier['tmp_name'])) {
        return [null, 'Le fichier n’a pas pu être envoyé.'];
    }
    $ext = strtolower(pathinfo((string) $fichier['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic'], true)) {
        return [null, 'Format accepté : PDF, JPG, PNG, WEBP ou HEIC.'];
    }
    if ((int) filesize((string) $fichier['tmp_name']) > 8 * 1024 * 1024) {
        return [null, 'Fichier trop lourd (8 Mo maximum).'];
    }
    $dir = __DIR__ . '/../docs-inscriptions/' . $membreId;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $nom = 'maj-' . $type . '-' . date('Ymd') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file((string) $fichier['tmp_name'], $dir . '/' . $nom)) {
        return [null, 'Le fichier n’a pas pu être enregistré.'];
    }
    db()->prepare('INSERT INTO membre_documents (membre_id, type, fichier, validite) VALUES (?,?,?,?)')
        ->execute([$membreId, $type, $membreId . '/' . $nom, $validite]);
    return [(int) db()->lastInsertId(), null];
}

/** Dernier document déposé par membre et par type : [membre_id][type] => ligne. */
function membre_docs_derniers(): array
{
    $out = [];
    try {
        $q = db()->query(
            'SELECT d.* FROM membre_documents d
               JOIN (SELECT MAX(id) AS mid FROM membre_documents GROUP BY membre_id, type) x ON x.mid = d.id'
        );
        foreach ($q as $r) $out[(int) $r['membre_id']][(string) $r['type']] = $r;
    } catch (Throwable $e) {
        // Table pas encore créée (migration en attente) : rien à afficher.
    }
    return $out;
}
