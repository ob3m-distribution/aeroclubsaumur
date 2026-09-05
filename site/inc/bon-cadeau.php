<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

/* ==================================================================
   Bons cadeaux — validation, creation, references
   ================================================================== */

/**
 * Reference / code : alphabet sans caracteres ambigus (ni 0/O, ni 1/I/L).
 * Format SAC-2026-A7K2.
 */
function code_aleatoire(string $prefixe = 'SAC'): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $suffixe = '';
    for ($i = 0; $i < 4; $i++) {
        $suffixe .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return sprintf('%s-%s-%s', $prefixe, date('Y'), $suffixe);
}

/** Reference unique en base — on retente en cas de collision. */
function reference_unique(): string
{
    $stmt = db()->prepare('SELECT 1 FROM bons_cadeaux WHERE reference = ? LIMIT 1');
    for ($essai = 0; $essai < 12; $essai++) {
        $ref = code_aleatoire();
        $stmt->execute([$ref]);
        if (!$stmt->fetchColumn()) {
            return $ref;
        }
    }
    // Improbable : on rallonge plutot que d'echouer.
    return code_aleatoire() . '-' . bin2hex(random_bytes(2));
}

/**
 * Valide les champs du formulaire.
 * Retourne [donnees_nettoyees, erreurs] — erreurs vide si tout va bien.
 */
function valider_demande(array $post): array
{
    $err = [];
    $d   = [];

    $texte = static function (?string $v): string {
        // On neutralise les caracteres de controle et on normalise les espaces.
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $v);
        return trim(preg_replace('/\s+/u', ' ', $v));
    };

    $d['prenom'] = $texte($post['prenom'] ?? '');
    if ($d['prenom'] === '') {
        $err['prenom'] = 'Merci d’indiquer votre prénom.';
    } elseif (mb_strlen($d['prenom']) > 80) {
        $err['prenom'] = 'Ce prénom est trop long (80 caractères maximum).';
    }

    $d['nom'] = $texte($post['nom'] ?? '');
    if ($d['nom'] === '') {
        $err['nom'] = 'Merci d’indiquer votre nom.';
    } elseif (mb_strlen($d['nom']) > 80) {
        $err['nom'] = 'Ce nom est trop long (80 caractères maximum).';
    }

    $d['email'] = $texte($post['email'] ?? '');
    if ($d['email'] === '') {
        $err['email'] = 'Merci d’indiquer votre adresse email.';
    } elseif (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        $err['email'] = 'Cette adresse email ne semble pas valide.';
    } elseif (mb_strlen($d['email']) > 180) {
        $err['email'] = 'Cette adresse email est trop longue.';
    }

    $d['telephone'] = $texte($post['telephone'] ?? '');
    $chiffres = preg_replace('/\D/', '', $d['telephone']);
    if ($d['telephone'] === '') {
        $err['telephone'] = 'Merci d’indiquer un numéro de téléphone.';
    } elseif (strlen($chiffres) < 9 || strlen($chiffres) > 15) {
        $err['telephone'] = 'Ce numéro de téléphone ne semble pas valide.';
    }

    $d['message'] = $texte($post['message'] ?? '');
    if (mb_strlen($d['message']) > 1000) {
        $err['message'] = 'Votre message est trop long (1000 caractères maximum).';
    }

    // ---- Type de vol et options -------------------------------------
    $type = (string) ($post['type_vol'] ?? '');
    if (!in_array($type, ['decouverte', 'initiation'], true)) {
        $err['type_vol'] = 'Merci de choisir le type de vol.';
        $type = 'decouverte';
    }
    $d['type_vol'] = $type;
    $d['nb_passagers'] = null;
    $d['duree_initiation'] = null;
    $d['offert_a'] = null;

    if ($type === 'decouverte') {
        $nb = (int) ($post['nb_passagers'] ?? 0);
        if (!in_array($nb, [1, 2, 3], true)) { $err['nb_passagers'] = 'Choisissez le nombre de personnes.'; $nb = 1; }
        $d['nb_passagers'] = $nb;
        // Noms des bénéficiaires (autant que de passagers).
        $prenoms = (array) ($post['benef_prenom'] ?? []);
        $noms    = (array) ($post['benef_nom'] ?? []);
        $liste = [];
        for ($i = 0; $i < $nb; $i++) {
            $nomComplet = trim($texte($prenoms[$i] ?? '') . ' ' . $texte($noms[$i] ?? ''));
            if ($nomComplet !== '') $liste[] = $nomComplet;
        }
        $d['offert_a'] = $liste ? implode(', ', $liste) : null;
    } else {
        $duree = (string) ($post['duree_initiation'] ?? '');
        if (!in_array($duree, ['1h30', '3h'], true)) { $err['duree_initiation'] = 'Choisissez la durée du vol.'; $duree = '1h30'; }
        $d['duree_initiation'] = $duree;
    }
    $d['montant_cents'] = prix_bon($d['type_vol'], $d['nb_passagers'], $d['duree_initiation']);

    $d['cgv'] = !empty($post['cgv']);
    if (!$d['cgv']) {
        $err['cgv'] = 'Merci d’accepter les conditions générales de vente.';
    }

    return [$d, $err];
}

/**
 * Limitation de debit : pas plus de 5 demandes par heure et par IP.
 * Evite qu'un robot ne remplisse la base.
 */
function trop_de_demandes(string $ip): bool
{
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return false;
    }
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM bons_cadeaux
         WHERE ip_creation = ? AND cree_le > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    );
    $stmt->execute([$bin]);
    return (int) $stmt->fetchColumn() >= 5;
}

/**
 * Enregistre la demande. Retourne la ligne creee.
 * Le statut reste « en_attente_paiement » : le paiement Stripe viendra ensuite.
 */
function creer_demande(array $d, string $ip): array
{
    $reference = reference_unique();
    $bin = @inet_pton($ip) ?: null;

    $stmt = db()->prepare(
        'INSERT INTO bons_cadeaux
            (reference, acheteur_prenom, acheteur_nom, acheteur_email,
             acheteur_telephone, message, type_vol, nb_passagers, duree_initiation,
             offert_a, montant_cents, statut, cgv_acceptees_le, ip_creation, cree_le)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())'
    );
    $stmt->execute([
        $reference,
        $d['prenom'],
        $d['nom'],
        $d['email'],
        $d['telephone'],
        $d['message'] !== '' ? $d['message'] : null,
        $d['type_vol'] ?? 'decouverte',
        $d['nb_passagers'] ?? null,
        $d['duree_initiation'] ?? null,
        $d['offert_a'] ?? null,
        $d['montant_cents'] ?? PRIX_BON_CADEAU_CENTIMES,
        'en_attente_paiement',
        $bin,
    ]);

    return [
        'id'        => (int) db()->lastInsertId(),
        'reference' => $reference,
    ] + $d;
}


/** Les statuts possibles d'un bon, avec leur libelle. */
const STATUTS_BON = [
    'en_attente_paiement' => ['En attente', 'attente'],
    'paye'                => ['Payé par Stripe', 'paye'],
    'utilise'             => ['Utilisé', 'utilise'],
    'expire'              => ['Expiré', 'expire'],
    'annule'              => ['Annulé', 'annule'],
    'rembourse'           => ['Remboursé', 'annule'],
];

/**
 * Construit la clause de filtrage de la liste des bons.
 *
 * Cette logique etait dupliquee entre l'affichage et l'export CSV :
 * modifier l'un sans l'autre aurait produit un export ne correspondant
 * pas a ce qui est a l'ecran. Une seule source, desormais.
 *
 * @return array{0:string, 1:array} [clause SQL (avec WHERE), parametres]
 */
function filtre_bons(string $statut, string $recherche): array
{
    $where = [];
    $args  = [];

    if (isset(STATUTS_BON[$statut])) {
        $where[] = 'statut = ?';
        $args[]  = $statut;
    }

    $recherche = trim($recherche);
    if ($recherche !== '') {
        $where[] = '(reference LIKE ? OR code LIKE ? OR numero_bon LIKE ? OR acheteur_nom LIKE ?
                     OR acheteur_prenom LIKE ? OR acheteur_email LIKE ? OR offert_a LIKE ?)';
        $motif = '%' . $recherche . '%';
        array_push($args, $motif, $motif, $motif, $motif, $motif, $motif, $motif);
    }

    return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $args];
}

/** Paliers de relance avant expiration (en jours), du plus lointain au plus proche. */
const RELANCES_JOURS = [90, 30, 10, 3, 1];

/**
 * Envoie les relances d'expiration dues (payé + non utilisé). Idempotent :
 * chaque palier envoyé est mémorisé dans la colonne `relances`, jamais renvoyé.
 * À appeler une fois par jour (tâche planifiée). Retourne le nombre d'emails envoyés.
 */
function envoyer_relances_bons(): int
{
    require_once __DIR__ . '/mail.php';
    $bons = db()->query(
        "SELECT * FROM bons_cadeaux
          WHERE statut = 'paye' AND date_fin_validite IS NOT NULL
            AND date_fin_validite >= CURDATE()"
    )->fetchAll();

    $maj = db()->prepare('UPDATE bons_cadeaux SET relances = ? WHERE id = ?');
    $envoyes = 0;

    foreach ($bons as $b) {
        $jours = (int) ((strtotime((string) $b['date_fin_validite']) - strtotime('today')) / 86400);
        $deja  = array_filter(array_map('intval', explode(',', (string) ($b['relances'] ?? ''))));
        // Paliers dus : seuil >= jours restants, pas encore envoyés.
        $dus = array_filter(RELANCES_JOURS, fn($t) => $jours <= $t && !in_array($t, $deja, true));
        if (!$dus) continue;

        // Un seul email par passage. On ne marque les paliers comme traités QUE si
        // l'envoi a réussi : sinon la relance est retentée au prochain passage.
        if (!email_relance_bon($b, $jours)) continue;
        $envoyes++;

        // Tous les paliers dépassés sont marqués d'un coup (pas de spam rétroactif).
        $tous = array_values(array_unique(array_merge($deja, $dus)));
        sort($tous);
        $maj->execute([implode(',', $tous), (int) $b['id']]);
    }

    return $envoyes;
}

/** Libellé lisible du vol offert : « Découverte · 2 pers. » / « Initiation · 1h30 ». */
function libelle_vol_bon(array $bon): string
{
    if (($bon['type_vol'] ?? 'decouverte') === 'initiation') {
        $d = (string) ($bon['duree_initiation'] ?? '');
        return 'Initiation' . ($d !== '' ? ' · ' . str_replace('h', ' h', $d) : '');
    }
    $n = (int) ($bon['nb_passagers'] ?? 1) ?: 1;
    return 'Découverte · ' . $n . ' pers.';
}

/** Recupere un bon par son identifiant. */
function bon_par_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM bons_cadeaux WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Recupere un bon par son intention de paiement Stripe. */
function bon_par_intention(string $pi): ?array
{
    $stmt = db()->prepare('SELECT * FROM bons_cadeaux WHERE stripe_payment_intent_id = ? LIMIT 1');
    $stmt->execute([$pi]);
    return $stmt->fetch() ?: null;
}

/** Rattache une intention de paiement a un bon. */
function bon_associer_intention(int $id, string $pi): void
{
    $stmt = db()->prepare('UPDATE bons_cadeaux SET stripe_payment_intent_id = ? WHERE id = ?');
    $stmt->execute([$pi, $id]);
}

/** Code de bon cadeau unique, genere seulement une fois le paiement confirme. */
function code_unique(): string
{
    $stmt = db()->prepare('SELECT 1 FROM bons_cadeaux WHERE code = ? LIMIT 1');
    for ($essai = 0; $essai < 12; $essai++) {
        $code = code_aleatoire('BON');
        $stmt->execute([$code]);
        if (!$stmt->fetchColumn()) {
            return $code;
        }
    }
    return code_aleatoire('BON') . '-' . bin2hex(random_bytes(2));
}

/** Numéro de bon lisible : WEB-AAAA-MM-JJ-NNN (numérotation par jour). */
function numero_bon_unique(): string
{
    $prefixe = 'WEB-' . date('Y-m-d') . '-';
    $stmt = db()->prepare('SELECT COUNT(*) FROM bons_cadeaux WHERE numero_bon LIKE ?');
    $stmt->execute([$prefixe . '%']);
    $n = (int) $stmt->fetchColumn() + 1;
    return $prefixe . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
}

/** Mise à jour depuis le B.O. : date de réalisation, pilote, bénéficiaire. */
function maj_bon_bo(int $id, ?string $dateRea, ?string $pilote, ?string $offertA): void
{
    $dateRea = ($dateRea !== null && $dateRea !== '') ? $dateRea : null;
    $pilote  = trim((string) $pilote) !== '' ? trim((string) $pilote) : null;
    $offertA = trim((string) $offertA) !== '' ? trim((string) $offertA) : null;

    $maj = db()->prepare(
        'UPDATE bons_cadeaux
            SET date_realisation = ?, pilote = ?, offert_a = ?,
                statut = CASE
                    WHEN ? IS NOT NULL AND statut IN (\'paye\', \'utilise\') THEN \'utilise\'
                    WHEN ? IS NULL AND statut = \'utilise\' THEN \'paye\'
                    ELSE statut END,
                utilise_le = CASE WHEN ? IS NOT NULL THEN COALESCE(utilise_le, NOW()) ELSE NULL END
          WHERE id = ?'
    );
    $maj->execute([$dateRea, $pilote, $offertA, $dateRea, $dateRea, $dateRea, $id]);
}

/**
 * Marque un bon comme paye. IDEMPOTENT : appele deux fois (retour navigateur
 * ET webhook), il ne genere qu'un seul code et ne renvoie qu'un seul email.
 *
 * Retourne [le bon a jour, true si c'est ce passage qui l'a valide].
 */
function bon_marquer_paye(int $id): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Verrou de ligne : deux appels simultanes ne peuvent pas passer ensemble.
        $stmt = $pdo->prepare('SELECT * FROM bons_cadeaux WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $bon = $stmt->fetch();

        if (!$bon) {
            $pdo->rollBack();
            throw new RuntimeException('Bon introuvable : ' . $id);
        }

        // Deja paye : on ne refait rien.
        if ($bon['statut'] !== 'en_attente_paiement') {
            $pdo->commit();
            return [$bon, false];
        }

        $code   = code_unique();
        $numero = numero_bon_unique();
        $maj = $pdo->prepare(
            'UPDATE bons_cadeaux
                SET statut = ?, code = ?, numero_bon = ?, paye_le = NOW(),
                    expire_le = DATE_ADD(CURDATE(), INTERVAL 1 YEAR),
                    date_fin_validite = DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
              WHERE id = ?'
        );
        $maj->execute(['paye', $code, $numero, $id]);

        $stmt = $pdo->prepare('SELECT * FROM bons_cadeaux WHERE id = ?');
        $stmt->execute([$id]);
        $bon = $stmt->fetch();

        $pdo->commit();
        return [$bon, true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Note l'envoi du bon, pour pouvoir le renvoyer sans doublon involontaire. */
function bon_marquer_envoye(int $id): void
{
    $stmt = db()->prepare('UPDATE bons_cadeaux SET pdf_envoye_le = NOW() WHERE id = ?');
    $stmt->execute([$id]);
}
