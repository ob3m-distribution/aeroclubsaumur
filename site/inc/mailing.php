<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mail.php';

/* ==================================================================
   Envois groupés (mailing) — ciblage par rôle / adhésion / paiement.
   ================================================================== */

const MAILING_FILTRES = [
    // Un membre peut cumuler plusieurs rôles : il reçoit l'envoi dès qu'il
    // a le rôle choisi (table membre_roles).
    'role' => [
        'tous'           => 'Tous les membres',
        'adherent'       => 'Rôle Adhérents',
        'administrateur' => 'Rôle Administrateurs',
        'instructeur'    => 'Rôle Instructeurs',
        'bureau'         => 'Rôle Bureau',
        'bons_cadeaux'   => 'Rôle Bons cadeaux',
    ],
    'adhesion' => [
        'tous'     => 'Peu importe l’adhésion',
        'ajour'    => 'Adhésion à jour (validée)',
        'nonajour' => 'Adhésion non à jour',
    ],
    'paiement' => [
        'tous'      => 'Peu importe le paiement',
        'recue'     => 'Cotisation reçue',
        'nonrecue'  => 'Cotisation non reçue',
    ],
];

/** Normalise les filtres issus du formulaire. */
function mailing_filtres(array $post): array
{
    $f = [];
    foreach (MAILING_FILTRES as $cle => $choix) {
        $v = (string) ($post[$cle] ?? 'tous');
        $f[$cle] = isset($choix[$v]) ? $v : 'tous';
    }
    return $f;
}

/** Construit la requête d'audience et retourne les destinataires. */
function mailing_destinataires(array $f, int $annee): array
{
    $sql = 'SELECT DISTINCT m.id, m.email, m.prenom, m.nom
              FROM membres m
              LEFT JOIN inscriptions i ON i.membre_id = m.id AND i.annee = ?
             WHERE m.actif = 1 AND m.email <> ?';
    $args = [$annee, ''];

    if (in_array($f['role'], ROLES_CUMULABLES, true)) {
        $sql .= ' AND EXISTS (SELECT 1 FROM membre_roles r WHERE r.membre_id = m.id AND r.role = ?)';
        $args[] = $f['role'];
    }

    if ($f['adhesion'] === 'ajour')      $sql .= " AND i.statut = 'valide'";
    elseif ($f['adhesion'] === 'nonajour') $sql .= " AND (i.id IS NULL OR i.statut <> 'valide')";

    if ($f['paiement'] === 'recue')      $sql .= " AND i.cotisation_ok = 1";
    elseif ($f['paiement'] === 'nonrecue') $sql .= " AND (i.id IS NULL OR i.cotisation_ok = 0)";

    $sql .= ' ORDER BY m.nom, m.prenom';

    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

/** Résumé lisible des filtres d'une campagne. */
function mailing_filtres_libelle(array $f): string
{
    $parts = [];
    foreach (MAILING_FILTRES as $cle => $choix) {
        if (($f[$cle] ?? 'tous') === 'tous') continue;
        // Anciens envois (avant les rôles cumulables) : « personnel »…
        $parts[] = $choix[$f[$cle]] ?? ['personnel' => 'Équipe / bureau seulement'][$f[$cle]] ?? $f[$cle];
    }
    return $parts ? implode(' · ', $parts) : 'Tous les membres actifs';
}

/**
 * Envoie une campagne à la liste fournie. {prenom} est personnalisé.
 * Retourne [nb_destinataires, nb_envoyes].
 */
function mailing_envoyer(string $sujet, string $corps, array $destinataires): array
{
    $envoyes = 0;
    foreach ($destinataires as $d) {
        $perso = str_replace('{prenom}', (string) ($d['prenom'] ?? ''), $corps);
        if (envoyer_email((string) $d['email'], $sujet, $perso)) {
            $envoyes++;
        }
    }
    return [count($destinataires), $envoyes];
}
