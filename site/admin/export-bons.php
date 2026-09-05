<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/bon-cadeau.php';
exiger_droit('bons.voir');

$statut    = (string) ($_GET['statut'] ?? '');
$recherche = trim((string) ($_GET['q'] ?? ''));
[$clause, $args] = filtre_bons($statut, $recherche);
$sql = 'SELECT * FROM bons_cadeaux' . $clause . ' ORDER BY cree_le DESC';

$stmt = db()->prepare($sql);
$stmt->execute($args);

journaliser('bons.export', $statut !== '' ? 'statut=' . $statut : 'tous');

$nom = 'bons-cadeaux-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nom . '"');

$sortie = fopen('php://output', 'w');
// BOM UTF-8 : sans lui, Excel massacre les accents.
fwrite($sortie, "\xEF\xBB\xBF");


// Point-virgule : le séparateur attendu par Excel en français.
fputcsv($sortie, [
    'Référence', 'Code', 'Statut', 'Montant (€)',
    'Prénom', 'Nom', 'Email', 'Téléphone',
    'Créé le', 'Payé le', 'Expire le', 'Utilisé le', 'Note',
], ';');

foreach ($stmt as $b) {
    fputcsv($sortie, [
        $b['reference'],
        $b['code'] ?? '',
        (STATUTS_BON[$b['statut']][0] ?? $b['statut']),
        number_format((int) $b['montant_cents'] / 100, 2, ',', ''),
        $b['acheteur_prenom'],
        $b['acheteur_nom'],
        $b['acheteur_email'],
        $b['acheteur_telephone'],
        $b['cree_le'] ? date('d/m/Y H:i', strtotime((string) $b['cree_le'])) : '',
        $b['paye_le'] ? date('d/m/Y H:i', strtotime((string) $b['paye_le'])) : '',
        $b['expire_le'] ? date('d/m/Y', strtotime((string) $b['expire_le'])) : '',
        $b['utilise_le'] ? date('d/m/Y', strtotime((string) $b['utilise_le'])) : '',
        (string) ($b['message'] ?? ''),
    ], ';');
}

fclose($sortie);
