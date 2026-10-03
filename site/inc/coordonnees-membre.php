<?php
declare(strict_types=1);

/* Coordonnées d'un membre, modifiables depuis son espace adhérents en dehors
   de la réadhésion. Elles vivent dans la fiche d'inscription la plus récente
   du membre (table inscriptions). Le courriel de connexion (table membres)
   n'est PAS modifiable ici : il passe par le secrétariat. */

require_once __DIR__ . '/db.php';

const MEMBRE_COORDONNEES = [
    'adresse'    => ['Adresse personnelle', 255],
    'tel_perso'  => ['Tél. personnel', 30],
    'tel_pro'    => ['Tél. professionnel', 30],
    'tel_mobile' => ['Mobile', 30],
    'courriel'   => ['Courriel de contact', 180],
    'urgence'    => ['Personne à prévenir / téléphone d’urgence', 255],
];

/** Fiche d'inscription la plus récente du membre (ou null). */
function membre_fiche_recente(int $membreId): ?array
{
    $s = db()->prepare('SELECT * FROM inscriptions WHERE membre_id = ? ORDER BY annee DESC, id DESC LIMIT 1');
    $s->execute([$membreId]);
    return $s->fetch() ?: null;
}

/**
 * Enregistre les coordonnées postées. Retourne [changements, erreurs] :
 * changements = liste de ['libelle', 'avant', 'apres'] (vide si rien de changé).
 */
function membre_coordonnees_enregistrer(int $membreId, array $post): array
{
    $fiche = membre_fiche_recente($membreId);
    if (!$fiche) return [[], ['Aucune fiche d’adhérent n’est rattachée à votre compte : contactez le secrétariat.']];

    $erreurs = [];
    $nouv = [];
    foreach (MEMBRE_COORDONNEES as $champ => [$libelle, $max]) {
        $v = trim((string) ($post[$champ] ?? ''));
        if (mb_strlen($v) > $max) { $erreurs[] = $libelle . ' : ' . $max . ' caractères maximum.'; continue; }
        if ($champ === 'courriel' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $erreurs[] = 'Le courriel de contact n’est pas une adresse valide.'; continue;
        }
        $nouv[$champ] = $v;
    }
    if ($erreurs) return [[], $erreurs];

    $changements = [];
    foreach ($nouv as $champ => $v) {
        $avant = (string) ($fiche[$champ] ?? '');
        if ($avant !== $v) $changements[] = ['libelle' => MEMBRE_COORDONNEES[$champ][0], 'avant' => $avant, 'apres' => $v, 'champ' => $champ];
    }
    if ($changements) {
        $sets = implode(', ', array_map(fn($c) => $c['champ'] . ' = ?', $changements));
        $vals = array_map(fn($c) => $c['apres'], $changements);
        $vals[] = (int) $fiche['id'];
        db()->prepare('UPDATE inscriptions SET ' . $sets . ' WHERE id = ?')->execute($vals);
    }
    return [$changements, []];
}
