<?php
declare(strict_types=1);

// En-têtes de sécurité HTTP, émis avant toute sortie (voir securite.php).
require_once __DIR__ . '/securite.php';

// Erreurs PHP dans un fichier récupérable par SFTP (voir journal-erreurs.php).
require_once __DIR__ . '/journal-erreurs.php';

/* ------------------------------------------------------------------
   Configuration generale du site Saumur Air Club
   ------------------------------------------------------------------ */

const CLUB = [
    'nom'          => 'Saumur Air Club',
    'adresse_1'    => 'Aérodrome de Saumur Terrefort',
    'adresse_2'    => 'Route de Marson, 49400 SAUMUR',
    'email'        => 'secretaire-general@saumurairclub.fr',
    'email_vols'   => 'voler@saumurairclub.fr',
    'email_president' => 'president@saumurairclub.fr',
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
    'siret'        => '302 864 913 00025',
    'directeur'    => 'Jonathan Robert',
    'email_admin'  => 'admin@saumur-airclub.aero',
    'tel_admin'    => '06 27 36 04 46',
];

/**
 * Réseaux sociaux officiels du club (vérifiés le 28/09/2026 : pages liées
 * depuis la fiche du club sur ot-saumur.fr, même téléphone que le club).
 */
const RESEAUX_SOCIAUX = [
    'facebook'  => ['Facebook',  'https://www.facebook.com/SaumurAirClub/',            'Saumur Air Club'],
    'instagram' => ['Instagram', 'https://www.instagram.com/aeroclub_saumurairclub/',  '@aeroclub_saumurairclub'],
];

/** Horaires d'ouverture du club (page Contact). null = fermé. */
const HORAIRES_OUVERTURE = [
    'lundi'    => null,
    'mardi'    => null,
    'mercredi' => null,
    'jeudi'    => null,
    'vendredi' => null,
    'samedi'   => '14:00–18:00',
    'dimanche' => '14:00–18:00',
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

/* Prix des vols et des cotisations : voir inc/tarifs.php (catalogue
   unique, modifiable depuis le back-office « Tarifs et prix »). */

/** Vols découverte : [nb passagers => centimes]. */
function vols_decouverte(): array
{
    require_once __DIR__ . '/tarifs.php';
    return [1 => tarif('vol.decouverte.1'), 2 => tarif('vol.decouverte.2'), 3 => tarif('vol.decouverte.3')];
}

/** Prix du bon cadeau « vitrine » : le vol découverte 1 passager. */
function prix_bon_cadeau(): int
{
    return vols_decouverte()[1];
}

/** Vols d'initiation vendus en bon cadeau : [durée => centimes]. */
function vols_initiation(): array
{
    require_once __DIR__ . '/tarifs.php';
    return ['1h30' => tarif('vol.initiation.1h30'), '3h' => tarif('vol.initiation.3h')];
}

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
    if ($type === 'initiation') return vols_initiation()[$duree] ?? vols_initiation()['1h30'];
    return vols_decouverte()[$nb] ?? vols_decouverte()[1];   // découverte
}

/* ------------------------------------------------------------------
   Grille de cotisations — reprise de la Fiche d'inscription 2027
   (version 08-10/2026). Montants en centimes, modifiables au B.O.
   ------------------------------------------------------------------ */
const COTISATION_ANNEE        = 2027;

/** A — Membre Club : obligatoire, sauf pour les programmes FFA (E) seuls. */
function cotisation_membre(): int
{
    require_once __DIR__ . '/tarifs.php';
    return tarif('cotis.membre');
}

/**
 * B et E — options (une seule au choix), dans l'ordre de la fiche 2027.
 * Les clés restent celles des dossiers déjà enregistrés (opt4 = Jeunes
 * Ailes, opt5 = Passeport, opt6 = Membre non pilote) : seuls les libellés
 * suivent la nouvelle numérotation de la fiche.
 * [clé => [libellé, centimes]].
 */
function cotisation_options(): array
{
    require_once __DIR__ . '/tarifs.php';
    return [
        'opt1' => ['Option 1 — Pilote', tarif('cotis.opt1')],
        'opt2' => ['Option 2 — Pilote −25 ans', tarif('cotis.opt2')],
        'opt3' => ['Option 3 — Pilote de passage (2ᵉ club, licence FFA hors club requise)', tarif('cotis.opt3')],
        'opt6' => ['Option 4 — Membre non pilote', tarif('cotis.opt6')],
        'opt4' => ['Option 6 — Licence Jeunes Ailes (programme FFA)', tarif('cotis.opt4')],
        'opt5' => ['Option 7 — Passeport FFA (programme FFA)', tarif('cotis.opt5')],
    ];
}

/** Options « Programmes FFA » (E) : la cotisation Membre Club y est facultative. */
const COTISATION_PROGRAMMES_FFA = ['opt4', 'opt5'];

/** Bloc d'heures associé au Passeport FFA (option 7). [clé => [libellé, centimes]]. */
function cotisation_blocs(): array
{
    require_once __DIR__ . '/tarifs.php';
    return [
        ''     => ['Sans bloc d’heures', 0],
        '1h30' => ['Bloc 1 h 30', tarif('cotis.bloc.1h30')],
        '3h'   => ['Bloc 3 h 00', tarif('cotis.bloc.3h')],
    ];
}

/**
 * Suppléments à cocher. [clé => [libellé, centimes, groupe de la fiche]].
 * Info Pilote papier / numérique : l'un OU l'autre.
 */
function cotisation_extras(): array
{
    require_once __DIR__ . '/tarifs.php';
    return [
        'caution_badge'   => ['Option 5 — Caution badge + clef', tarif('cotis.caution_badge'), 'B'],
        'info_pilote'     => ['Info Pilote (papier)', tarif('cotis.info_pilote'), 'C'],
        'info_pilote_num' => ['Info Pilote (numérique)', tarif('cotis.info_pilote_num'), 'C'],
        'licence_ffa'     => ['Licence FFA', tarif('cotis.licence_ffa'), 'D'],
        'pack_basique'    => ['Pack basique (manuel du pilote, carnet de vol, livret de progression, protège check-list, livret d’accueil)', tarif('cotis.pack_basique'), 'F'],
        'elearning'       => ['Abonnement e-learning « aérogligli » (24 mois)', tarif('cotis.elearning'), 'F'],
    ];
}

/**
 * Calcule le total d'une inscription (centimes) à partir des choix.
 * $d : option, passeport_bloc, extras[] (clés cochées ; « sans_membre » =
 * Membre Club non pris, possible seulement avec un programme FFA).
 */
function total_inscription(array $d): int
{
    $opt    = (string) ($d['option'] ?? '');
    $extras = (array) ($d['extras'] ?? []);
    $total  = 0;
    if (!(in_array($opt, COTISATION_PROGRAMMES_FFA, true) && in_array('sans_membre', $extras, true))) {
        $total += cotisation_membre();
    }
    $options = cotisation_options();
    if (isset($options[$opt])) {
        $total += $options[$opt][1];
    }
    if ($opt === 'opt5') {
        $total += cotisation_blocs()[(string) ($d['passeport_bloc'] ?? '')][1] ?? 0;
    }
    $prix = cotisation_extras();
    foreach ($extras as $cle) {
        if (isset($prix[$cle])) {
            $total += $prix[$cle][1];
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
