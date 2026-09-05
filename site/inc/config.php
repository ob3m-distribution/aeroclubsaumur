<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
   Configuration generale du site Saumur Air Club
   ------------------------------------------------------------------ */

const CLUB = [
    'nom'          => 'Saumur Air Club',
    'adresse_1'    => 'Aérodrome de Saumur Terrefort',
    'adresse_2'    => 'Route de Marson, 49400 SAUMUR',
    'email'        => 'secretaire-general@saumurairclub.fr',
    'email_vols'   => 'voler@saumurairclub.fr',
    'tel_mobile'   => '06 27 36 04 46',
    'tel_fixe'     => '02 41 50 20 27',
    'lat'          => '47.25848000',
    'lon'          => '-0.11650000',
    'lat_dms'      => '47° 15′ 24″ N',
    'lon_dms'      => '000° 06′ 49″ W',
    'frequence'    => '120.605',
    'piste'        => '1450 × 30 m',

    // Donnees legales, reprises de saumur-airclub.aero/mentions_legales_SAC.php
    'forme'        => 'Association Loi 1901',
    'siren'        => '302864913',
    'directeur'    => 'Jonathan Robert',
    'email_admin'  => 'admin@saumur-airclub.aero',
    'tel_admin'    => '06 27 36 04 46',
];

/** Hebergeur du site — mention legale OBLIGATOIRE. */
const HEBERGEUR = [
    'nom'     => 'IONOS SARL',
    'adresse' => '7 place de la Gare, BP 70109, 57200 Sarreguemines Cedex, France',
    'tel'     => '0970 808 911',
    'site'    => 'https://www.ionos.fr',
];

/** Realisation technique du site — mention legale. */
const REALISATION = [
    'raison_sociale' => 'OB3M Distribution (OB3MDIS)',
    'forme'          => 'SAS – Société par actions simplifiée',
    'capital'        => '300 €',
    'adresse'        => '2 impasse des Vendangeurs, 17610 Chaniers, France',
    'tel'            => '+33 7 81 07 09 94',
    'email'          => 'ob3m.distribution@gmail.com',
    'rcs'            => '103 916 656 – RCS Saintes',
    'tva'            => 'FR 31 103 916 656',
];

/**
 * Prix des vols decouverte, en centimes. Jamais de decimal pour de l'argent.
 * Releves sur saumur-airclub.aero le 17/07/2026 — A FAIRE CONFIRMER par le club.
 */
const VOLS_DECOUVERTE = [
    1 => 13000,   // 1 passager  : 130 €
    2 => 18000,   // 2 passagers : 180 €
    3 => 24000,   // 3 passagers : 240 €
];

/**
 * Prix du bon cadeau : 130 €, soit le vol decouverte 1 passager.
 * Confirme par Cyrille le 18/07/2026 — un seul passager, pas de version
 * 2 ou 3 places. Un seul produit vendu en ligne.
 */
const PRIX_BON_CADEAU_CENTIMES = 13000;

/**
 * Prix des vols d'initiation offerts en bon cadeau, en centimes.
 * Repris de la grille tarifs (Passeport FFA) — A CONFIRMER par le club.
 */
const VOLS_INITIATION = [
    '1h30' => 32300,   // 323 €
    '3h'   => 64500,   // 645 €
];

/** Pilotes du club (liste déroulante « Pilote » sur les bons cadeaux au B.O.). */
const PILOTES = [
    'Jean-Christophe Lafilay',
    'Yves Couffon',
    'Benjamin Tortorici',
    'Maxime Malagu',
    'Jonathan Robert',
    'Valentin Vasseur',
    'Philippe Berthon',
];

/** Prix d'un bon cadeau selon le type de vol et l'option choisie (en centimes). */
function prix_bon(string $type, ?int $nb, ?string $duree): int
{
    if ($type === 'initiation') return VOLS_INITIATION[$duree] ?? VOLS_INITIATION['1h30'];
    return VOLS_DECOUVERTE[$nb] ?? VOLS_DECOUVERTE[1];   // découverte
}

/* ------------------------------------------------------------------
   Grille de cotisations — reprise de la Fiche d'inscription 2026
   (version 03-10/2025). Montants en centimes.
   ------------------------------------------------------------------ */
const COTISATION_ANNEE        = 2026;
const COTISATION_MEMBRE_CLUB  = 1000;   // A — Membre Club, obligatoire (10 €)

/** B — Options (une seule au choix). [libellé, centimes]. */
const COTISATION_OPTIONS = [
    'opt1' => ['Pilote', 23000],
    'opt2' => ['Pilote −25 ans', 11000],
    'opt3' => ['Pilote de passage (2ᵉ club)', 11000],
    'opt4' => ['Licence Jeunes Ailes', 1600],
    'opt5' => ['Passeport FFA', 1600],
    'opt6' => ['Membre non pilote', 0],
];

/** Bloc d'heures associé au Passeport FFA (option 5). [libellé, centimes]. */
const COTISATION_PASSEPORT_BLOCS = [
    ''     => ['Sans bloc d’heures', 0],
    '1h30' => ['Bloc 1 h 30', 26400],
    '3h'   => ['Bloc 3 h 00', 52800],
];

/** C/D/E + caution — cases à cocher indépendantes. [libellé, centimes, groupe]. */
const COTISATION_EXTRAS = [
    'info_pilote'   => ['Info Pilote', 4900, 'C'],
    'licence_ffa'   => ['Licence FFA', 9600, 'D'],
    'pack_basique'  => ['Pack basique (formation)', 11500, 'E'],
    'elearning'     => ['Abonnement e-learning « aérogligli » (24 mois)', 5600, 'E'],
    'caution_badge' => ['Caution badge + clef', 5000, 'B'],
];

/**
 * Calcule le total d'une inscription (centimes) à partir des choix.
 * $d : tableau associatif (option, passeport_bloc, extras[] de clés cochées).
 */
function total_inscription(array $d): int
{
    $total = COTISATION_MEMBRE_CLUB;
    $opt = (string) ($d['option'] ?? '');
    if (isset(COTISATION_OPTIONS[$opt])) {
        $total += COTISATION_OPTIONS[$opt][1];
    }
    if ($opt === 'opt5') {
        $bloc = (string) ($d['passeport_bloc'] ?? '');
        $total += COTISATION_PASSEPORT_BLOCS[$bloc][1] ?? 0;
    }
    foreach ((array) ($d['extras'] ?? []) as $cle) {
        if (isset(COTISATION_EXTRAS[$cle])) {
            $total += COTISATION_EXTRAS[$cle][1];
        }
    }
    return $total;
}

/** Les pages, dans l'ordre du menu. */
const PAGES = [
    'index'               => ['titre' => 'Accueil',                 'menu' => 'Accueil'],
    'aerodrome'           => ['titre' => 'Aérodrome & Club House',  'menu' => 'Aérodrome'],
    'vols-decouvertes'    => ['titre' => 'Vols découvertes & initiations', 'menu' => 'Vols découvertes & initiations'],
    'avions'              => ['titre' => 'Nos avions',              'menu' => 'Avions'],
    'partenaires'         => ['titre' => 'Partenaires',             'menu' => 'Partenaires'],
    'tarifs-inscriptions' => ['titre' => 'Tarifs & inscriptions',   'menu' => 'Tarifs'],
    'faq'                 => ['titre' => 'Questions fréquentes',    'menu' => 'FAQ'],
    'adherents'           => ['titre' => 'Espace adhérents',        'menu' => 'Adhérents'],
    'contact'             => ['titre' => 'Contact',                 'menu' => 'Contact'],
];

/** Echappement HTML — a utiliser sur TOUTE sortie. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formate un montant en centimes vers « 100 € » ou « 99,50 € ». */
function prix(int $centimes): string
{
    $entier = intdiv($centimes, 100);
    $reste  = $centimes % 100;
    return $reste === 0
        ? $entier . ' €'
        : $entier . ',' . str_pad((string) $reste, 2, '0', STR_PAD_LEFT) . ' €';
}

/** URL propre d'une page : index -> /, le reste -> /slug */
function url(string $slug): string
{
    return $slug === 'index' ? '/' : '/' . $slug;
}

/** Numero de telephone nettoye pour un lien tel: */
function tel_lien(string $numero): string
{
    return '+33' . ltrim(preg_replace('/\D/', '', $numero), '0');
}
