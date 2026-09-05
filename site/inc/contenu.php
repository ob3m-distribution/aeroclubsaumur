<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/* ==================================================================
   Contenus modifiables.

   Principe : on écrit dans le gabarit
       <h1><?= texte('accueil.titre', 'Le ciel n’est pas la limite') ?></h1>
   et le texte devient modifiable directement sur le site, en cliquant
   dessus — à condition d'être connecté en super administrateur et
   d'avoir activé le mode édition.

   La valeur par défaut reste dans le code : si la base est vide, le
   site s'affiche normalement. Rien ne peut « disparaître ».
   ================================================================== */

/** Tous les contenus enregistrés, chargés en une seule requête. */
function contenus_charges(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        foreach (db()->query('SELECT cle, valeur FROM contenus') as $l) {
            $cache[$l['cle']] = $l['valeur'];
        }
    } catch (Throwable $e) {
        // Base indisponible : on retombe sur les valeurs par défaut du code.
        error_log('Contenus : ' . $e->getMessage());
    }
    return $cache;
}

/** Valeur brute d'un contenu, sans habillage d'édition. */
function contenu_brut(string $cle, string $defaut = ''): string
{
    $tous = contenus_charges();
    $v = $tous[$cle] ?? null;
    return ($v === null || $v === '') ? $defaut : (string) $v;
}

/**
 * Durée d'une session d'édition, en secondes.
 * Passé ce délai, il faut repasser par le back-office.
 */
const EDITION_DUREE = 7200;   // 2 heures

/**
 * Le mode édition est-il actif ?
 *
 * Il ne s'active QUE depuis le back-office (section « Contenus du site ») :
 * arriver directement sur le site, même en super administrateur, ne permet
 * rien de modifier. C'est volontaire — on ne veut pas risquer de modifier
 * le site par inadvertance en le consultant.
 */
function mode_edition(): bool
{
    static $actif = null;
    if ($actif !== null) {
        return $actif;
    }
    $actif = false;

    // Un visiteur sans session ne coûte pas une requête en base.
    if (empty($_COOKIE[session_name()])) {
        return $actif;
    }
    require_once __DIR__ . '/auth.php';
    session_demarrer();

    $depuis = (int) ($_SESSION['mode_edition_depuis'] ?? 0);
    if ($depuis <= 0) {
        return $actif;
    }

    // Session d'édition expirée : on la referme proprement.
    if (time() - $depuis > EDITION_DUREE) {
        unset($_SESSION['mode_edition_depuis']);
        return $actif;
    }

    $actif = peut('contenus.gerer');
    return $actif;
}

/**
 * Texte modifiable.
 *
 * @param string $cle    Identifiant stable, ex. « accueil.titre ».
 * @param string $defaut Valeur affichée tant que rien n'a été saisi.
 * @param string $type   'court' (une ligne) ou 'long' (plusieurs lignes).
 */
function texte(string $cle, string $defaut = '', string $type = 'court'): string
{
    $valeur = contenu_brut($cle, $defaut);

    if (!mode_edition()) {
        return $type === 'long' ? nl2br(e($valeur)) : e($valeur);
    }

    // En mode édition, on enveloppe pour rendre la zone cliquable.
    return sprintf(
        '<span class="zone-editable" data-cle="%s" data-type="%s" tabindex="0" role="button" '
        . 'aria-label="Modifier ce texte">%s<span class="zone-editable__crayon" aria-hidden="true">✎</span></span>',
        e($cle),
        e($type),
        $type === 'long' ? nl2br(e($valeur)) : e($valeur)
    );
}

/**
 * Image modifiable. Retourne l'URL — à placer dans src="".
 * L'habillage d'édition est ajouté par balise_image().
 */
function image_url(string $cle, string $defaut): string
{
    return contenu_brut($cle, $defaut);
}

/**
 * Balise <img> complète et modifiable.
 *
 * @param array $attributs alt, class, width, height, loading, fetchpriority…
 */
function image(string $cle, string $defaut, array $attributs = []): string
{
    $url = image_url($cle, $defaut);

    // Si une variante « -900 » existe, on laisse le navigateur choisir :
    // inutile d'envoyer 1600 px de large a un telephone.
    if (!isset($attributs['srcset']) && preg_match('/^(.+)\.(jpe?g|png)$/i', $url, $m)) {
        $petite = $m[1] . '-900.' . $m[2];
        if (is_file(__DIR__ . '/..' . $petite)) {
            $attributs['srcset'] = $petite . ' 900w, ' . $url . ' 1600w';
            $attributs['sizes'] ??= '100vw';
        }
    }

    $attrs = '';
    foreach ($attributs as $nom => $valeur) {
        if ($valeur === null || $valeur === '') {
            continue;
        }
        $attrs .= sprintf(' %s="%s"', e((string) $nom), e((string) $valeur));
    }

    $balise = sprintf('<img src="%s"%s>', e($url), $attrs);

    if (!mode_edition()) {
        return $balise;
    }

    return sprintf(
        '<span class="zone-image" data-cle="%s" tabindex="0" role="button" aria-label="Remplacer cette photo">'
        . '%s<span class="zone-image__action" aria-hidden="true">📷 Remplacer</span></span>',
        e($cle),
        $balise
    );
}

/** Enregistre un contenu. */
function definir_contenu(string $cle, ?string $valeur, string $type = 'texte'): void
{
    $membre = $_SESSION['membre_id'] ?? null;
    $stmt = db()->prepare(
        'INSERT INTO contenus (cle, valeur, type, modifie_par) VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), modifie_par = VALUES(modifie_par)'
    );
    $stmt->execute([$cle, $valeur, $type, $membre ? (int) $membre : null]);
}

/** Remet un contenu à sa valeur d'origine (celle du code). */
function reinitialiser_contenu(string $cle): void
{
    $stmt = db()->prepare('DELETE FROM contenus WHERE cle = ?');
    $stmt->execute([$cle]);
}

/**
 * Correspondance préfixe de clé → page du site.
 * Sert à ranger les modifications par page dans le back-office.
 * Une liste exhaustive des clés se périmerait à chaque évolution :
 * on se contente donc du rattachement, qui lui reste juste.
 */
function pages_contenus(): array
{
    return [
        'accueil'     => ['nom' => 'Accueil',                'url' => '/'],
        'aerodrome'   => ['nom' => 'Aérodrome & Club House', 'url' => '/aerodrome'],
        'vols'        => ['nom' => 'Vols découvertes',       'url' => '/vols-decouvertes'],
        'avions'      => ['nom' => 'Nos avions',             'url' => '/avions'],
        'partenaires' => ['nom' => 'Partenaires',            'url' => '/partenaires'],
        'tarifs'      => ['nom' => 'Tarifs & inscriptions',  'url' => '/tarifs-inscriptions'],
        'adherents'   => ['nom' => 'Espace adhérents',       'url' => '/adherents'],
        'contact'     => ['nom' => 'Contact',                'url' => '/contact'],
    ];
}

/** Page à laquelle appartient une clé de contenu. */
function page_du_contenu(string $cle): string
{
    $prefixe = explode('.', $cle)[0];
    return pages_contenus()[$prefixe]['nom'] ?? 'Autre';
}

/**
 * Traduit une clé en libellé lisible.
 * « accueil.carte1.titre » → « Carte 1 · titre »
 */
function libelle_contenu(string $cle): string
{
    $mots = [
        'hero' => 'bandeau', 'surtitre' => 'sur-titre', 'titre' => 'titre',
        'accroche' => 'accroche', 'texte' => 'texte', 'texte1' => 'texte 1',
        'texte2' => 'texte 2', 'image' => 'photo', 'photo' => 'photo',
        'libelle' => 'libellé', 'valeur' => 'valeur', 'prix' => 'prix',
        'etiquette' => 'étiquette', 'nom' => 'nom', 'desc' => 'description',
        'cat' => 'catégorie', 'logo' => 'logo', 'duree' => 'durée',
        'mention' => 'mention', 'meteo' => 'météo', 'intro' => 'introduction',
        'adhesion' => 'adhésion', 'horaire' => 'tarif horaire', 'formation' => 'formation',
        'etiq1' => 'étiquette 1', 'etiq2' => 'étiquette 2', 'point1' => 'point 1',
        'point2' => 'point 2', 'point3' => 'point 3', 'point4' => 'point 4',
        'dec' => 'vol découverte', 'init' => 'vol d’initiation', 'bon' => 'bon cadeau',
        'club' => 'club', 'rejoindre' => 'nous rejoindre', 'appel' => 'bandeau d’appel',
        'formules' => 'formules', 'activites' => 'activités', 'commander' => 'commander',
        'etapes' => 'étapes', 'resto' => 'restauration', 'venir' => 'venir', 'pilotes' => 'pilotes',
        'reseaux' => 'réseaux sociaux', 'horaires' => 'horaires', 'secretariat' => 'secrétariat',
        'reserver' => 'réserver', 'carte' => 'carte', 'admin' => 'administratif',
    ];

    $parties = explode('.', $cle);
    array_shift($parties);   // le préfixe de page est déjà affiché à part

    $lisibles = array_map(static function (string $p) use ($mots): string {
        if (isset($mots[$p])) {
            return $mots[$p];
        }
        // carte1 → carte 1, tuile3 → tuile 3, fait2 → fait 2
        return preg_replace('/([a-z]+)(\d+)/', '$1 $2', $p);
    }, $parties);

    return ucfirst(implode(' · ', $lisibles));
}
