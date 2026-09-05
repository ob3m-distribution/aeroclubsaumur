<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/* ==================================================================
   Bibliothèque adhérents — catalogue en base (sections + documents).
   Administrable depuis le B.O. (admin/bibliotheque.php).
   Tables : biblio_sections(id,parent_id,nom,position),
            biblio_documents(id,section_id,nom,fichier,type,taille,position).
   ================================================================== */

/** Toutes les sections, indexées par id (avec parent_id, nom, position). */
function biblio_sections_toutes(): array {
    static $c = null;
    if ($c !== null) return $c;
    $c = [];
    foreach (db()->query('SELECT id,parent_id,nom,position FROM biblio_sections ORDER BY position,id') as $r) {
        $c[(int) $r['id']] = [
            'id'        => (int) $r['id'],
            'parent_id' => $r['parent_id'] !== null ? (int) $r['parent_id'] : null,
            'nom'       => $r['nom'],
            'position'  => (int) $r['position'],
        ];
    }
    return $c;
}

/** Section de premier niveau dont dépend une section donnée. */
function biblio_top_de_section(int $sid): ?int {
    $all = biblio_sections_toutes();
    $cur = $sid; $g = 0;
    while (isset($all[$cur]) && $all[$cur]['parent_id'] !== null && $g++ < 30) {
        $cur = $all[$cur]['parent_id'];
    }
    return isset($all[$cur]) ? $cur : null;
}

/** Sections de premier niveau : [id => nom], dans l'ordre. */
function biblio_sections_top(): array {
    $out = [];
    foreach (biblio_sections_toutes() as $s) {
        if ($s['parent_id'] === null) $out[$s['id']] = $s['nom'];
    }
    return $out;
}

/** Arbre complet : [ ['id','nom','docs'=>[['id','nom','type','taille'],...],'sous'=>[...]], ... ]. */
function biblio_arbre(): array {
    $all = biblio_sections_toutes();
    $docs = [];
    foreach (db()->query('SELECT id,section_id,nom,fichier,type,taille FROM biblio_documents ORDER BY position,id') as $d) {
        $docs[(int) $d['section_id']][] = [
            'id' => (int) $d['id'], 'nom' => $d['nom'],
            'type' => $d['type'], 'taille' => (int) $d['taille'],
        ];
    }
    $enf = [];
    foreach ($all as $s) { $enf[$s['parent_id'] ?? 0][] = $s['id']; }
    $build = function ($sid) use (&$build, $all, $docs, $enf) {
        $node = ['id' => $sid, 'nom' => $all[$sid]['nom'], 'docs' => $docs[$sid] ?? [], 'sous' => []];
        foreach ($enf[$sid] ?? [] as $cid) { $node['sous'][] = $build($cid); }
        return $node;
    };
    $tree = [];
    foreach ($enf[0] ?? [] as $sid) { $tree[] = $build($sid); }
    return $tree;
}

/** Nombre de documents dans un nœud (récursif). */
function biblio_compter(array $n): int {
    $c = count($n['docs']);
    foreach ($n['sous'] as $s) { $c += biblio_compter($s); }
    return $c;
}

/** Un document par id : [id,section_id,nom,fichier,type,section_top]. */
function biblio_doc(int $id): ?array {
    $s = db()->prepare('SELECT id,section_id,nom,fichier,type FROM biblio_documents WHERE id = ?');
    $s->execute([$id]);
    $d = $s->fetch();
    if (!$d) return null;
    $d['section_top'] = biblio_top_de_section((int) $d['section_id']);
    return $d;
}

/* ---- Accès par dossier (top-level) pour les adhérents --------------- */

/**
 * Dossiers (ids de sections top, en texte) autorisés pour un membre.
 * - null  => non configuré : accès à TOUT (défaut).
 * - liste => uniquement ces sections (vide possible = aucun accès).
 */
function membre_dossiers_autorises(int $membreId): ?array {
    $s = db()->prepare('SELECT dossier FROM membre_dossiers WHERE membre_id = ?');
    $s->execute([$membreId]);
    $rows = $s->fetchAll(PDO::FETCH_COLUMN);
    if (!$rows) return null;
    return array_values(array_filter($rows, fn($d) => $d !== '__aucun__'));
}

/** Enregistre les dossiers autorisés (ids de sections top). */
function definir_membre_dossiers(int $membreId, array $ids): void {
    $valides = array_map('strval', array_keys(biblio_sections_top()));
    $choisis = array_values(array_intersect($valides, array_map('strval', $ids)));
    db()->prepare('DELETE FROM membre_dossiers WHERE membre_id = ?')->execute([$membreId]);
    $ins = db()->prepare('INSERT INTO membre_dossiers (membre_id, dossier) VALUES (?, ?)');
    if (!$choisis) {
        $ins->execute([$membreId, '__aucun__']);
    } else {
        foreach ($choisis as $d) $ins->execute([$membreId, $d]);
    }
}

/** Dossiers autorisés par défaut pour un rôle (null = non configuré = tout). */
function role_dossiers_autorises(string $role): ?array {
    $s = db()->prepare('SELECT dossier FROM role_dossiers WHERE role = ?');
    $s->execute([$role]);
    $rows = $s->fetchAll(PDO::FETCH_COLUMN);
    if (!$rows) return null;
    return array_values(array_filter($rows, fn($d) => $d !== '__aucun__'));
}

/** Définit l'accès par défaut d'un rôle (ids de sections top). */
function definir_role_dossiers(string $role, array $ids): void {
    $valides = array_map('strval', array_keys(biblio_sections_top()));
    $choisis = array_values(array_intersect($valides, array_map('strval', $ids)));
    db()->prepare('DELETE FROM role_dossiers WHERE role = ?')->execute([$role]);
    $ins = db()->prepare('INSERT INTO role_dossiers (role, dossier) VALUES (?, ?)');
    if (!$choisis) { $ins->execute([$role, '__aucun__']); }
    else { foreach ($choisis as $d) $ins->execute([$role, $d]); }
}

/**
 * Accès effectif d'un membre : réglage individuel s'il existe, sinon défaut de
 * son rôle, sinon tout. null = tous les dossiers.
 */
function dossiers_effectifs(array $membre): ?array {
    $perso = membre_dossiers_autorises((int) $membre['id']);
    if ($perso !== null) return $perso;                 // override individuel
    return role_dossiers_autorises((string) ($membre['role'] ?? ''));
}

/** Le membre voit-il tous les dossiers ? (personnel avec droits B.O. = oui) */
function membre_voit_tout(array $membre): bool {
    return !empty(ROLES[$membre['role']]['droits'] ?? []);
}

/* ---- Types de fichiers --------------------------------------------- */

/** Type MIME d'après l'extension. */
function biblio_mime(string $type): string {
    return [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif'  => 'image/gif', 'webp' => 'image/webp',
        'mp4'  => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls'  => 'application/vnd.ms-excel',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc'  => 'application/msword',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip'  => 'application/zip',
    ][$type] ?? 'application/octet-stream';
}

/** S'ouvre dans l'onglet (inline) ou se télécharge ? */
function biblio_inline(string $type): bool {
    return in_array($type, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'], true);
}
