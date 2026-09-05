<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

/* ==================================================================
   Réinscription en ligne — couche logique.
   Une ligne `inscriptions` par membre et par année (clé unique).
   ================================================================== */

/** Champs libres enregistrables (hors calcul/statut/traçabilité). */
const INSCRIPTION_CHAMPS = [
    'type', 'nom', 'prenom', 'nationalite', 'date_naissance', 'lieu_naissance',
    'profession', 'adresse', 'tel_perso', 'tel_pro', 'tel_mobile', 'courriel', 'urgence',
    'lapl_num', 'lapl_date', 'ppl_num', 'ppl_date', 'validite_licence', 'validite_sep',
    'autres_qualifs', 'validite_visite_medicale', 'num_ffa',
    'pere_nom', 'mere_nom', 'autorisation_parentale',
    'option_cotisation', 'passeport_bloc', 'extras', 'convocation_ag', 'rgpd_accepte',
];

/** Inscription d'un membre pour une année (ou null). */
function inscription_annee(int $membreId, int $annee): ?array
{
    $s = db()->prepare('SELECT * FROM inscriptions WHERE membre_id = ? AND annee = ? LIMIT 1');
    $s->execute([$membreId, $annee]);
    return $s->fetch() ?: null;
}

/**
 * Valeurs de préremplissage : brouillon en cours s'il existe, sinon report de
 * l'inscription de l'an dernier, sinon les infos de base du compte membre.
 */
function inscription_prefill(array $membre, int $annee): array
{
    $courante = inscription_annee((int) $membre['id'], $annee);
    if ($courante) return $courante;

    $precedente = inscription_annee((int) $membre['id'], $annee - 1);
    if ($precedente) {
        // Report d'année : on repart des mêmes infos, statut brouillon, dates de
        // validité et documents remis à zéro (ils changent chaque année).
        $precedente['statut'] = 'brouillon';
        $precedente['annee']  = $annee;
        foreach (['validite_licence', 'validite_sep', 'validite_visite_medicale',
                  'licence_fichier', 'visite_medicale_fichier'] as $c) {
            $precedente[$c] = null;
        }
        $precedente['type'] = 'renouvellement';
        return $precedente;
    }

    return [
        'type' => 'renouvellement',
        'nom' => $membre['nom'] ?? '', 'prenom' => $membre['prenom'] ?? '',
        'courriel' => $membre['email'] ?? '',
        'convocation_ag' => 'courriel',
    ];
}

/** Nettoie et normalise les données issues du formulaire. */
function inscription_depuis_post(array $post): array
{
    $d = [];
    foreach (INSCRIPTION_CHAMPS as $c) {
        $d[$c] = is_array($post[$c] ?? null) ? $post[$c] : trim((string) ($post[$c] ?? ''));
    }
    // Cases à cocher / listes.
    $d['autorisation_parentale'] = !empty($post['autorisation_parentale']) ? 1 : 0;
    $d['rgpd_accepte']           = !empty($post['rgpd_accepte']) ? 1 : 0;
    $d['convocation_ag']         = ($post['convocation_ag'] ?? 'courriel') === 'courrier' ? 'courrier' : 'courriel';
    $d['type']                   = ($post['type'] ?? 'renouvellement') === 'demande' ? 'demande' : 'renouvellement';

    $extras = array_values(array_intersect(
        array_keys(COTISATION_EXTRAS),
        array_map('strval', (array) ($post['extras'] ?? []))
    ));
    $d['extras'] = implode(',', $extras);

    if (!isset(COTISATION_OPTIONS[$d['option_cotisation']])) $d['option_cotisation'] = '';
    if (!isset(COTISATION_PASSEPORT_BLOCS[$d['passeport_bloc']])) $d['passeport_bloc'] = '';

    // Dates : chaîne vide -> null.
    foreach (['date_naissance', 'lapl_date', 'ppl_date', 'validite_licence',
              'validite_sep', 'validite_visite_medicale'] as $c) {
        if (($d[$c] ?? '') === '') $d[$c] = null;
    }
    return $d;
}

/** Total en centimes calculé à partir des choix de cotisation. */
function inscription_total(array $d): int
{
    return total_inscription([
        'option'         => $d['option_cotisation'] ?? '',
        'passeport_bloc' => $d['passeport_bloc'] ?? '',
        'extras'         => explode(',', (string) ($d['extras'] ?? '')),
    ]);
}

/** Le membre est-il mineur au regard de la date de naissance saisie ? */
function inscription_est_mineur(array $d): bool
{
    if (empty($d['date_naissance'])) return false;
    $ts = strtotime((string) $d['date_naissance']);
    if ($ts === false) return false;
    return (int) floor((time() - $ts) / (365.25 * 86400)) < 18;
}

/**
 * Champs manquants pour considérer le dossier « complet » (prêt au paiement).
 * Retourne un tableau clé => message.
 */
function inscription_manquants(array $d): array
{
    $m = [];
    if (($d['nom'] ?? '') === '')            $m['nom'] = 'Nom obligatoire.';
    if (($d['prenom'] ?? '') === '')         $m['prenom'] = 'Prénom obligatoire.';
    if (empty($d['date_naissance']))         $m['date_naissance'] = 'Date de naissance obligatoire.';
    if (($d['adresse'] ?? '') === '')        $m['adresse'] = 'Adresse obligatoire.';
    if (($d['tel_mobile'] ?? '') === '' && ($d['tel_perso'] ?? '') === '') {
        $m['tel_mobile'] = 'Au moins un téléphone.';
    }
    if (!filter_var($d['courriel'] ?? '', FILTER_VALIDATE_EMAIL)) $m['courriel'] = 'E-mail valide obligatoire.';
    if (($d['option_cotisation'] ?? '') === '') $m['option_cotisation'] = 'Choisissez une option de cotisation.';
    if (empty($d['rgpd_accepte']))           $m['rgpd_accepte'] = 'Acceptation du règlement obligatoire.';
    if (inscription_est_mineur($d) && ($d['pere_nom'] ?? '') === '' && ($d['mere_nom'] ?? '') === '') {
        $m['pere_nom'] = 'Représentant légal obligatoire pour un mineur.';
    }
    return $m;
}

/** Construit [colonnes, valeurs] pour un enregistrement (null -> '' hors dates). */
function inscription_colonnes_valeurs(array $d): array
{
    $d['total_cents'] = inscription_total($d);
    $datesNullables = ['date_naissance', 'lapl_date', 'ppl_date', 'validite_licence',
                       'validite_sep', 'validite_visite_medicale'];
    $cols = array_merge(INSCRIPTION_CHAMPS, ['total_cents']);
    $vals = [];
    foreach ($cols as $c) {
        $v = $d[$c] ?? null;
        if ($v === null && !in_array($c, $datesNullables, true)) $v = '';
        $vals[] = $v;
    }
    return [$cols, $vals];
}

/**
 * Insère une DEMANDE de pré-inscription (nouveau membre, sans compte).
 * membre_id NULL, type 'demande'. Retourne l'id créé.
 */
function preinscription_creer(int $annee, array $d, ?string $licence, ?string $medicale): int
{
    $d['type'] = 'demande';
    [$cols, $vals] = inscription_colonnes_valeurs($d);
    $colNoms = implode(', ', $cols);
    $place   = implode(', ', array_fill(0, count($cols), '?'));
    $sql = "INSERT INTO inscriptions (membre_id, annee, statut, licence_fichier, visite_medicale_fichier, $colNoms)
            VALUES (NULL, ?, 'complet', ?, ?, $place)";
    db()->prepare($sql)->execute(array_merge([$annee, $licence, $medicale], $vals));
    return (int) db()->lastInsertId();
}

/**
 * Enregistre (upsert) l'inscription d'un membre pour une année.
 * $statut : 'brouillon' ou 'complet'. Retourne l'id de la ligne.
 */
function inscription_enregistrer(int $membreId, int $annee, array $d, string $statut): int
{
    [$cols, $vals] = inscription_colonnes_valeurs($d);
    $set = array_map(fn($c) => "$c = ?", $cols);

    $existante = inscription_annee($membreId, $annee);
    if ($existante) {
        $sql = 'UPDATE inscriptions SET ' . implode(', ', $set) . ', statut = ? WHERE id = ?';
        $vals[] = $statut;
        $vals[] = (int) $existante['id'];
        db()->prepare($sql)->execute($vals);
        return (int) $existante['id'];
    }

    $colNoms = implode(', ', $cols);
    $place   = implode(', ', array_fill(0, count($cols), '?'));
    $sql = "INSERT INTO inscriptions (membre_id, annee, statut, $colNoms) VALUES (?, ?, ?, $place)";
    $vals = array_merge([$membreId, $annee, $statut], $vals);
    db()->prepare($sql)->execute($vals);
    return (int) db()->lastInsertId();
}

/** Résout une liste d'ids de membres en [id => "Prénom Nom"]. */
function inscription_noms_membres(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $q = db()->prepare("SELECT id, prenom, nom FROM membres WHERE id IN ($in)");
    $q->execute($ids);
    $out = [];
    foreach ($q as $r) $out[(int) $r['id']] = trim($r['prenom'] . ' ' . $r['nom']);
    return $out;
}

/** Trace « ✓ le JJ/MM/AAAA · Nom » d'une étape (ok_le / ok_par), ou ''. */
function inscription_trace(array $ins, string $cle, array $noms): string
{
    $le = $ins[$cle . '_ok_le'] ?? null;
    if (!$le) return '';
    $par = (int) ($ins[$cle . '_ok_par'] ?? 0);
    return '✓ ' . date('d/m/Y', strtotime((string) $le))
         . (isset($noms[$par]) ? ' · ' . $noms[$par] : '');
}

/* ---- Paiement Stripe d'une cotisation --------------------------------- */

/** Associe une intention de paiement Stripe à une inscription. */
function inscription_associer_intention(int $id, string $pi): void
{
    db()->prepare('UPDATE inscriptions SET stripe_payment_intent_id = ? WHERE id = ?')->execute([$pi, $id]);
}

/** Inscription rattachée à une intention de paiement, ou null. */
function inscription_par_intention(string $pi): ?array
{
    $s = db()->prepare('SELECT * FROM inscriptions WHERE stripe_payment_intent_id = ? LIMIT 1');
    $s->execute([$pi]);
    return $s->fetch() ?: null;
}

/**
 * Marque la cotisation d'une inscription comme réglée. Idempotent.
 * Enregistre le mode + la date, et coche « cotisation reçue ».
 */
function inscription_marquer_paye(int $id, string $mode): void
{
    db()->prepare(
        "UPDATE inscriptions
            SET mode_paiement = ?, paye_le = COALESCE(paye_le, NOW()),
                cotisation_ok = 1, cotisation_ok_le = COALESCE(cotisation_ok_le, NOW())
          WHERE id = ?"
    )->execute([$mode, $id]);
}

/** Libellé lisible du niveau de cotisation d'une inscription. */
function inscription_resume_cotisation(array $ins): string
{
    $parts = [];
    $opt = (string) ($ins['option_cotisation'] ?? '');
    if (isset(COTISATION_OPTIONS[$opt])) $parts[] = COTISATION_OPTIONS[$opt][0];
    if ($opt === 'opt5' && !empty($ins['passeport_bloc'])) {
        $parts[] = COTISATION_PASSEPORT_BLOCS[$ins['passeport_bloc']][0] ?? '';
    }
    foreach (explode(',', (string) ($ins['extras'] ?? '')) as $e) {
        if (isset(COTISATION_EXTRAS[$e])) $parts[] = COTISATION_EXTRAS[$e][0];
    }
    return $parts ? implode(' · ', array_filter($parts)) : '—';
}
