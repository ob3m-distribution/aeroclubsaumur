<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/contenu.php';
exiger_droit('contenus.gerer');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (jeton_csrf_valide($_POST['csrf'] ?? null) && ($_POST['action'] ?? '') === 'reinitialiser') {
        $cle = (string) ($_POST['cle'] ?? '');
        if ($cle !== '') {
            reinitialiser_contenu($cle);
            journaliser('contenu.reinitialise', $cle);
            $_SESSION['message_succes'] = 'Contenu d’origine rétabli.';
        }
    }
    header('Location: /admin/contenus.php', true, 303);
    exit;
}

$modifies = [];
foreach (db()->query('SELECT cle, valeur, type, modifie_le FROM contenus') as $l) {
    $modifies[$l['cle']] = $l;
}

/* Pages ouvertes à la modification — source unique : inc/contenu.php */
$notes = [
    'accueil'     => 'Grand titre, accroche, photos, présentation, tuiles et cartes',
    'aerodrome'   => 'Bandeau, chiffres clés, informations terrain, club house',
    'vols'        => 'Bandeau, formules, tarifs, bon cadeau, étapes',
    'avions'      => 'Photos et descriptions des trois appareils',
    'partenaires' => 'Bandeau, logos, noms et descriptions',
    'tarifs'      => 'Grilles tarifaires, formations, textes',
    'adherents'   => 'Titre et texte de la page',
    'contact'     => 'Coordonnées, horaires, réseaux sociaux',
];
$pages = [];
foreach (pages_contenus() as $prefixe => $infos) {
    $pages[] = [
        'url'  => $infos['url'],
        'nom'  => $infos['nom'],
        'note' => $notes[$prefixe] ?? '',
    ];
}

$enCours = mode_edition();

$titre = 'Contenus du site';
$actif = 'contenus';
require __DIR__ . '/inc/entete.php';
?>

<?php if ($enCours): ?>
  <div class="message message--succes" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
    <strong style="flex:1">✎ Une session de modification est en cours.</strong>
    <a class="btn btn--contour btn--petit" href="/" target="_blank" rel="noopener">Revenir au site ↗</a>
    <form method="post" action="/admin/mode-edition.php" style="display:inline">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <input type="hidden" name="action" value="terminer">
      <button type="submit" class="btn btn--petit">Terminer les modifications</button>
    </form>
  </div>
<?php endif; ?>

<div class="bloc" style="border-left:3px solid var(--rouge)">
  <h2>Choisissez la page à modifier</h2>
  <p style="max-width:62ch;color:var(--gris-700)">
    Cliquez sur une page : vous arrivez dessus en <strong>mode modification</strong>.
    Tout ce qui est modifiable s’entoure de pointillés rouges — vous cliquez sur un
    texte ou une photo, vous changez, c’est enregistré.
  </p>
  <p style="max-width:62ch;color:var(--gris-700)">
    <strong>C’est le seul chemin possible.</strong> En consultant le site normalement,
    rien n’est modifiable : aucun risque de changer quelque chose par mégarde.
  </p>

  <div class="grille grille--2" style="margin-top:1.5rem">
    <?php foreach ($pages as $p): ?>
      <form method="post" action="/admin/mode-edition.php"
            style="display:flex;align-items:center;gap:1rem;padding:.9rem 1rem;
                   border:1px solid var(--gris-200);border-radius:var(--rayon);background:var(--blanc)">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="page" value="<?= e($p['url']) ?>">
        <span style="flex:1;min-width:0">
          <strong style="display:block"><?= e($p['nom']) ?></strong>
          <span style="font-size:.8125rem;color:var(--gris-500)"><?= e($p['note']) ?></span>
        </span>
        <button type="submit" class="btn btn--petit" style="white-space:nowrap">Modifier ✎</button>
      </form>
    <?php endforeach; ?>
  </div>

  <div class="message message--info" style="margin-top:1.5rem;margin-bottom:0">
    <strong>Rien n’est irréversible.</strong> Chaque texte et chaque photo conserve sa
    version d’origine : un bouton « Remettre d’origine » est toujours disponible, et
    vider un champ suffit à rétablir le texte initial.
    La session de modification se referme d’elle-même au bout de deux heures.
  </div>
</div>

<div class="bloc">
  <div class="bloc__titre">
    <h2>Police du site</h2>
    <a class="btn btn--contour btn--petit" href="/admin/polices.php">Changer la police</a>
  </div>
  <p style="color:var(--gris-700);margin-bottom:0">
    Cinq polices au choix, à essayer sur vos vraies pages avant de trancher.
  </p>
</div>

<div class="bloc">
  <div class="bloc__titre">
    <h2>Ce que vous avez modifié</h2>
    <span class="etat <?= $modifies ? 'etat--paye' : 'etat--expire' ?>">
      <?= count($modifies) ?> modification<?= count($modifies) > 1 ? 's' : '' ?>
    </span>
  </div>

  <?php if (!$modifies): ?>
    <p class="vide">
      Aucune modification pour l’instant : le site affiche ses textes et photos d’origine.
    </p>
  <?php else: ?>
    <div class="tableau">
      <table>
        <thead>
          <tr><th>Emplacement</th><th>Contenu actuel</th><th>Modifié le</th><th></th></tr>
        </thead>
        <tbody>
        <?php
          foreach ($modifies as $cle => $c):
        ?>
          <tr>
            <td>
              <strong><?= e(page_du_contenu($cle)) ?></strong><br>
              <?= e(libelle_contenu($cle)) ?>
              <br><span style="color:var(--gris-500);font-size:.75rem"><?= e($cle) ?></span>
            </td>
            <td style="max-width:26rem">
              <?php if ($c['type'] === 'image'): ?>
                <img src="<?= e((string) $c['valeur']) ?>" alt=""
                     style="max-height:52px;border-radius:3px">
              <?php else: ?>
                <span style="font-size:.875rem;color:var(--gris-700)">
                  <?= e(mb_strimwidth((string) $c['valeur'], 0, 140, '…')) ?>
                </span>
              <?php endif; ?>
            </td>
            <td style="font-size:.8125rem;white-space:nowrap">
              <?= e(date('d/m/Y à H:i', strtotime((string) $c['modifie_le']))) ?>
            </td>
            <td class="nombre">
              <form method="post">
                <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                <input type="hidden" name="action" value="reinitialiser">
                <input type="hidden" name="cle" value="<?= e($cle) ?>">
                <button type="submit" class="btn btn--contour btn--petit"
                        data-confirmer="Remettre le contenu d’origine ? Votre version sera perdue.">
                  Remettre d’origine
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="bloc">
  <h2>Ce qui est modifiable</h2>
  <p style="color:var(--gris-700);max-width:62ch">
    Sur chaque page : <strong>tous les textes et toutes les photos</strong> — titres,
    paragraphes, chiffres clés, étiquettes, grilles tarifaires, logos.
  </p>
  <p style="color:var(--gris-700);max-width:62ch">
    La structure des pages et le design restent figés : c’est ce qui garantit que
    le site ne se déforme jamais, quoi que vous écriviez.
  </p>
  <div class="message message--info" style="margin-bottom:0">
    Un élément vous résiste ? Dites-le-moi : soit il m’a échappé, soit c’est une
    donnée gérée ailleurs (coordonnées du club, prix du bon cadeau).
  </div>
</div>

<?php require __DIR__ . '/inc/pied.php'; ?>
