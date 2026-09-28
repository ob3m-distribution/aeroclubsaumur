<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/* ==================================================================
   Tarifs et prix — une seule source pour tout le site.

   Chaque prix a une valeur par défaut ICI (en centimes) ; le back-office
   (admin/tarifs.php) peut la remplacer, la valeur modifiée est alors
   rangée dans la table parametres sous la clé « tarif.<clé> ». Pages,
   formulaires, e-mails et paiements lisent tous tarif().

   Base indisponible : on retombe sur les valeurs par défaut, le site
   s'affiche quand même.
   ================================================================== */

const TARIFS_CATEGORIES = [
    'vols_decouverte' => 'Vols découverte et bon cadeau',
    'vols_initiation' => 'Vols d’initiation',
    'heures_vol'      => 'Heures de vol (location des avions)',
    'cotisation'      => 'Cotisation annuelle',
    'passeport'       => 'Passeport FFA — blocs d’heures',
    'licence_extras'  => 'Licence, revue et options',
];

/** [catégorie, libellé, centimes par défaut, suffixe d'unité]. */
const TARIFS = [
    'vol.decouverte.1'     => ['vols_decouverte', 'Vol découverte 30 min — 1 passager (prix du bon cadeau)', 13000, ''],
    'vol.decouverte.2'     => ['vols_decouverte', 'Vol découverte 30 min — 2 passagers', 18000, ''],
    'vol.decouverte.3'     => ['vols_decouverte', 'Vol découverte 30 min — 3 passagers', 24000, ''],

    'vol.initiation.30min' => ['vols_initiation', 'Vol d’initiation — 30 minutes', 18000, ''],
    'vol.initiation.1h30'  => ['vols_initiation', 'Vol d’initiation Passeport FFA — 1 h 30', 32300, ''],
    'vol.initiation.3h'    => ['vols_initiation', 'Vol d’initiation Passeport FFA — 3 h', 64500, ''],
    'vol.initiation.licence' => ['vols_initiation', 'Licence et assurance du Passeport FFA (en sus)', 1600, ''],

    'avion.evektor'        => ['heures_vol', 'Evektor SportStar G3X', 13800, '/h'],
    'avion.cessna172'      => ['heures_vol', 'Cessna 172 N', 18200, '/h'],
    'avion.dr400'          => ['heures_vol', 'Robin DR 400-180', 21000, '/h'],
    'avion.instruction'    => ['heures_vol', 'Supplément instruction', 4000, '/h'],

    'cotis.membre'         => ['cotisation', 'A — Membre Club (obligatoire)', 1000, ''],
    'cotis.opt1'           => ['cotisation', 'B — Pilote', 23000, ''],
    'cotis.opt2'           => ['cotisation', 'B — Pilote −25 ans', 11000, ''],
    'cotis.opt3'           => ['cotisation', 'B — Pilote de passage (2ᵉ club)', 11000, ''],
    'cotis.opt4'           => ['cotisation', 'B — Licence Jeunes Ailes', 1600, ''],
    'cotis.opt5'           => ['cotisation', 'B — Passeport FFA', 1600, ''],
    'cotis.opt6'           => ['cotisation', 'B — Membre non pilote', 0, ''],

    'cotis.bloc.1h30'      => ['passeport', 'Bloc 1 h 30', 26400, ''],
    'cotis.bloc.3h'        => ['passeport', 'Bloc 3 h 00', 52800, ''],

    'cotis.licence_ffa'    => ['licence_extras', 'D — Licence FFA', 9600, ''],
    'cotis.info_pilote'    => ['licence_extras', 'C — Revue Info Pilote', 4900, ''],
    'cotis.pack_basique'   => ['licence_extras', 'E — Pack basique (formation)', 11500, ''],
    'cotis.elearning'      => ['licence_extras', 'E — Abonnement e-learning « aérogligli » (24 mois)', 5600, ''],
    'cotis.caution_badge'  => ['licence_extras', 'Caution badge + clef', 5000, ''],
];

/** Coordonnées bancaires du club (envoyées pour les virements). */
const RIB_CHAMPS = [
    'rib.titulaire' => ['Titulaire du compte', CLUB['nom']],
    'rib.iban'      => ['IBAN', ''],
    'rib.bic'       => ['BIC', ''],
    'rib.banque'    => ['Banque', ''],
];

/** Valeurs modifiées depuis le B.O. (clé => valeur brute), en une requête. */
function tarifs_modifies(bool $recharger = false): array
{
    static $cache = null;
    if ($cache !== null && !$recharger) {
        return $cache;
    }
    $cache = [];
    try {
        $st = db()->query("SELECT cle, valeur FROM parametres WHERE cle LIKE 'tarif.%' OR cle LIKE 'rib.%'");
        foreach ($st as $l) {
            $cache[$l['cle']] = (string) $l['valeur'];
        }
    } catch (Throwable $e) {
        error_log('Tarifs : ' . $e->getMessage());
    }
    return $cache;
}

/** Prix en centimes. */
function tarif(string $cle): int
{
    $v = tarifs_modifies()['tarif.' . $cle] ?? null;
    if ($v !== null && $v !== '' && ctype_digit($v)) {
        return (int) $v;
    }
    return TARIFS[$cle][2] ?? 0;
}

/** Prix formaté avec son unité : « 138 €/h ». */
function tarif_affiche(string $cle): string
{
    return prix(tarif($cle)) . (TARIFS[$cle][3] ?? '');
}

/** Coordonnée bancaire (IBAN…) ; chaîne vide si non renseignée. */
function rib(string $champ): string
{
    $v = tarifs_modifies()['rib.' . $champ] ?? '';
    return $v !== '' ? $v : (RIB_CHAMPS['rib.' . $champ][1] ?? '');
}

/** IBAN à afficher, ou un texte d'attente tant qu'il n'est pas saisi au B.O. */
function rib_iban_affiche(): string
{
    return rib('iban') !== '' ? rib('iban') : 'communiqué par le club sur simple demande';
}

/**
 * Convertit une saisie « 130 », « 130,50 », « 130.5 € » en centimes.
 * null si la saisie n'est pas un montant valide.
 */
function centimes_depuis_saisie(string $saisie): ?int
{
    $s = str_replace([' ', "\u{00A0}", "\u{202F}", '€'], '', trim($saisie));
    $s = str_replace(',', '.', $s);
    if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $s)) {
        return null;
    }
    return (int) round((float) $s * 100);
}
