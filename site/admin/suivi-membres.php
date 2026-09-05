<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/inscription.php';
require_once __DIR__ . '/../inc/mail.php';
exiger_droit('membres.gerer');

$moi   = membre_connecte();
$annee = COTISATION_ANNEE;

/** Lien absolu vers un formulaire du site. */
function lien_formulaire(string $slug): string
{
    return 'https://' . ($_SERVER['HTTP_HOST'] ?? 'dev.aeroclub-saumur.fr') . '/' . $slug;
}
function lien_reinscription(): string { return lien_formulaire('reinscription'); }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? 'valider');
    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $_SESSION['message_erreur'] = 'Session expirée, action non effectuée.';
    } elseif ($action === 'lien_email') {
        // Envoi du lien de réinscription à une adresse saisie librement.
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message_erreur'] = 'Adresse e-mail invalide.';
        } else {
            $ok = email_lien_reinscription($email, '', lien_reinscription());
            journaliser('reinscription.lien_email', $email);
            $_SESSION['message_succes'] = $ok
                ? 'Lien de réinscription envoyé à ' . $email . '.'
                : 'L’e-mail n’a pas pu être envoyé.';
        }
    } elseif ($action === 'lien_preinscription') {
        // Envoi du lien de pré-inscription à une adresse (nouveau membre potentiel).
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message_erreur'] = 'Adresse e-mail invalide.';
        } else {
            $ok = email_lien_preinscription($email, lien_formulaire('preinscription'));
            journaliser('preinscription.lien_email', $email);
            $_SESSION['message_succes'] = $ok
                ? 'Lien de pré-inscription envoyé à ' . $email . '.'
                : 'L’e-mail n’a pas pu être envoyé.';
        }
    } elseif ($action === 'lien_membre') {
        // Envoi du lien au membre / à l'adresse de la ligne.
        $email  = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $prenom = (string) ($_POST['prenom'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message_erreur'] = 'Ce membre n’a pas d’adresse e-mail valide.';
        } else {
            $ok = email_lien_reinscription($email, $prenom, lien_reinscription());
            journaliser('reinscription.lien_membre', $email);
            $_SESSION['message_succes'] = $ok
                ? 'Lien de réinscription envoyé à ' . $email . '.'
                : 'L’e-mail n’a pas pu être envoyé.';
        }
    } else {
        // Validation manuelle (cotisation / licence / médicale) tracée.
        $id  = (int) ($_POST['id'] ?? 0);
        $moiId = (int) $moi['id'];
        $flags = [
            'cotisation' => !empty($_POST['cotisation_ok']) ? 1 : 0,
            'licence'    => !empty($_POST['licence_ok']) ? 1 : 0,
            'medicale'   => !empty($_POST['medicale_ok']) ? 1 : 0,
        ];
        foreach ($flags as $nom => $v) {
            $sql = "UPDATE inscriptions SET
                        {$nom}_ok = ?,
                        {$nom}_ok_le  = CASE WHEN ? = 1 THEN COALESCE({$nom}_ok_le, NOW()) ELSE NULL END,
                        {$nom}_ok_par = CASE WHEN ? = 1 THEN COALESCE({$nom}_ok_par, ?) ELSE NULL END
                    WHERE id = ?";
            db()->prepare($sql)->execute([$v, $v, $v, $moiId, $id]);
        }
        if ($flags['cotisation']) {
            db()->prepare("UPDATE inscriptions SET statut = 'valide', valide_le = COALESCE(valide_le, NOW()),
                           valide_par = COALESCE(valide_par, ?) WHERE id = ? AND statut IN ('complet','paye')")
                ->execute([$moiId, $id]);
        } else {
            db()->prepare("UPDATE inscriptions SET statut = 'complet', valide_le = NULL, valide_par = NULL
                           WHERE id = ? AND statut = 'valide'")->execute([$id]);
        }
        journaliser('inscription.valide', 'inscription#' . $id, implode(',', array_keys(array_filter($flags))));
        $_SESSION['message_succes'] = 'Suivi mis à jour.';
    }
    $ongletRetour = in_array($action, ['lien_preinscription'], true) ? 'nouvelle' : 'reinscription';
    $ancre = $action === 'valider' ? '#i-' . (int) ($_POST['id'] ?? 0) : '';
    header('Location: /admin/suivi-membres.php?onglet=' . $ongletRetour . $ancre, true, 303);
    exit;
}

$onglet = ($_GET['onglet'] ?? '') === 'nouvelle' ? 'nouvelle' : 'reinscription';

// Réinscriptions : dossiers rattachés à un compte membre.
$lignes = db()->prepare(
    'SELECT i.*, m.prenom AS m_prenom, m.nom AS m_nom, m.email AS m_email,
            v.prenom AS v_prenom, v.nom AS v_nom
       FROM inscriptions i
       LEFT JOIN membres m ON m.id = i.membre_id
       LEFT JOIN membres v ON v.id = i.valide_par
      WHERE i.annee = ? AND i.type <> "demande"
      ORDER BY (i.statut = "valide"), m.nom, m.prenom'
);
$lignes->execute([$annee]);
$lignes = $lignes->fetchAll();

// Nouvelles inscriptions : demandes reçues via le formulaire public.
$demandes = db()->prepare(
    'SELECT * FROM inscriptions WHERE type = "demande" ORDER BY cree_le DESC'
);
$demandes->execute();
$demandes = $demandes->fetchAll();

$fin = mktime(0, 0, 0, 12, 31, $annee); // 31/12 de l'année

// Noms des membres ayant validé une étape (pour la traçabilité).
$idsValideurs = [];
foreach (array_merge($lignes, $demandes) as $l) {
    foreach (['cotisation', 'licence', 'medicale', 'rencontre'] as $k) {
        if (!empty($l[$k . '_ok_par'])) $idsValideurs[] = (int) $l[$k . '_ok_par'];
    }
}
$nomsValideurs = inscription_noms_membres($idsValideurs);

$titre = 'Adhérents';
$actif = 'suivi';
require __DIR__ . '/inc/entete.php';

/** Puce « à jour » selon une date de validité comparée au 31/12. */
$ajour = static function (?string $date) use ($fin): string {
    if (!$date) return '<span class="muet">—</span>';
    $ok = strtotime($date) >= $fin;
    $lib = date('d/m/Y', strtotime($date));
    return '<span class="etat etat--' . ($ok ? 'paye">à jour' : 'annule">expire') . ' ' . e($lib) . '</span>';
};
?>

<div class="bo-onglets">
  <a href="?onglet=reinscription" class="bo-onglet<?= $onglet === 'reinscription' ? ' actif' : '' ?>">Réinscription</a>
  <a href="?onglet=nouvelle" class="bo-onglet<?= $onglet === 'nouvelle' ? ' actif' : '' ?>">Nouvelle inscription</a>
</div>

<?php if ($onglet === 'nouvelle'): ?>

<div class="bloc">
  <h2>Formulaire de pré-inscription</h2>
  <p class="aide" style="margin:.2rem 0 .9rem">
    Formulaire public pour les personnes qui souhaitent rejoindre le club (sans compte ni
    paiement à ce stade). Ouvrez-le, ou envoyez le lien à une adresse.
  </p>
  <div class="suivi-lien">
    <a class="btn btn--contour" href="/preinscription" target="_blank" rel="noopener">Ouvrir le formulaire ↗</a>
    <form method="post" class="suivi-lien__form">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <input type="hidden" name="action" value="lien_preinscription">
      <input type="email" name="email" placeholder="adresse@exemple.fr" required>
      <button type="submit" class="btn">Envoyer le lien à cette adresse</button>
    </form>
  </div>
</div>

<div class="bloc">
  <div class="bloc__titre">
    <h2>Demandes reçues — <?= count($demandes) ?></h2>
  </div>
  <?php if (!$demandes): ?>
    <p class="vide">Aucune demande de pré-inscription pour l’instant.</p>
  <?php else: ?>
    <div class="tableau tableau--filtrable">
      <table>
        <thead>
          <tr><th>Date</th><th>Nom</th><th>Prénom</th><th>Contact</th><th>Souhait</th><th>Statut</th><th>Documents</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($demandes as $dm):
            $faits = (int) $dm['rencontre_ok'] + (int) $dm['licence_ok'] + (int) $dm['medicale_ok'] + (int) $dm['cotisation_ok'];
            $pret = $faits === 4; ?>
          <tr>
            <td><?= e(date('d/m/Y', strtotime((string) $dm['cree_le']))) ?></td>
            <td><a href="/admin/adherent.php?i=<?= (int) $dm['id'] ?>"><?= e($dm['nom']) ?></a></td>
            <td><?= e($dm['prenom']) ?></td>
            <td style="font-size:.8rem"><?= e($dm['courriel']) ?><br><span class="muet"><?= e($dm['tel_mobile']) ?></span></td>
            <td style="font-size:.8rem"><?= e(inscription_resume_cotisation($dm)) ?></td>
            <td><span class="etat etat--<?= $pret ? 'paye">Prête à valider' : ($faits ? 'planifie">En cours ' . $faits . '/4' : 'attente">Reçue') ?></span></td>
            <td style="font-size:.8rem">
              <?= $dm['licence_fichier'] ? '<a href="/admin/doc-inscription.php?i=' . (int) $dm['id'] . '&t=licence" target="_blank" rel="noopener">Licence</a>' : '<span class="muet">licence —</span>' ?><br>
              <?= $dm['visite_medicale_fichier'] ? '<a href="/admin/doc-inscription.php?i=' . (int) $dm['id'] . '&t=medicale" target="_blank" rel="noopener">Médicale</a>' : '<span class="muet">médicale —</span>' ?>
            </td>
            <td class="nombre"><a class="btn btn--contour btn--petit" href="/admin/adherent.php?i=<?= (int) $dm['id'] ?>">Voir le dossier</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php else: ?>

<div class="bloc">
  <h2>Formulaire de réinscription <?= $annee ?></h2>
  <p class="aide" style="margin:.2rem 0 .9rem">
    Les membres renouvellent leur adhésion en ligne via ce formulaire (accessible depuis leur
    espace adhérent). Vous pouvez l’ouvrir, ou envoyer le lien à une adresse précise.
  </p>
  <div class="suivi-lien">
    <a class="btn btn--contour" href="/reinscription" target="_blank" rel="noopener">Ouvrir le formulaire ↗</a>
    <form method="post" class="suivi-lien__form">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <input type="hidden" name="action" value="lien_email">
      <input type="email" name="email" placeholder="adresse@exemple.fr" required>
      <button type="submit" class="btn">Envoyer le lien à cette adresse</button>
    </form>
  </div>
</div>

<div class="bloc">
  <div class="bloc__titre">
    <h2>Adhésions <?= $annee ?> — <?= count($lignes) ?> dossier<?= count($lignes) > 1 ? 's' : '' ?></h2>
  </div>

  <?php if (!$lignes): ?>
    <p class="vide">Aucun dossier de réinscription pour <?= $annee ?> pour l’instant.</p>
  <?php else: ?>

    <?php foreach ($lignes as $l): $email = (string) ($l['m_email'] ?: $l['courriel']); ?>
      <form id="suivif-<?= (int) $l['id'] ?>" method="post" hidden>
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="action" value="valider">
        <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
      </form>
      <form id="lienf-<?= (int) $l['id'] ?>" method="post" hidden>
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="action" value="lien_membre">
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <input type="hidden" name="prenom" value="<?= e((string) ($l['m_prenom'] ?: $l['prenom'])) ?>">
      </form>
    <?php endforeach; ?>

    <div class="tableau tableau--suivi tableau--filtrable">
      <table>
        <thead>
          <tr>
            <th>Membre</th>
            <th>Cotisation</th>
            <th>Licence</th>
            <th>Visite médicale</th>
            <th>Validation</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($lignes as $l): $ff = 'form="suivif-' . (int) $l['id'] . '"'; ?>
          <tr id="i-<?= (int) $l['id'] ?>">
            <td>
              <strong><a href="/admin/adherent.php?i=<?= (int) $l['id'] ?>"><?= e(trim(($l['m_prenom'] ?? $l['prenom']) . ' ' . ($l['m_nom'] ?? $l['nom']))) ?></a></strong>
              <br><span class="muet" style="font-size:.8rem"><?= e($l['m_email'] ?? $l['courriel']) ?></span>
            </td>
            <td style="font-size:.82rem">
              <?= e(inscription_resume_cotisation($l)) ?><br>
              <strong><?= e(prix((int) $l['total_cents'])) ?></strong>
              <?php if ($l['mode_paiement']): ?><span class="muet"> · <?= e($l['mode_paiement']) ?></span><?php endif; ?>
              <br>
              <?php $st = ['brouillon'=>['Brouillon','attente'],'complet'=>['Complet','planifie'],'paye'=>['Payé','paye'],'valide'=>['Validé','paye']][$l['statut']] ?? [$l['statut'],'attente']; ?>
              <span class="etat etat--<?= e($st[1]) ?>"><?= e($st[0]) ?></span>
            </td>
            <td style="font-size:.8rem">
              <?= $ajour($l['validite_licence']) ?>
              <?php if ($l['licence_fichier']): ?>
                <br><a href="/admin/doc-inscription.php?i=<?= (int) $l['id'] ?>&t=licence" target="_blank" rel="noopener">Voir le fichier</a>
              <?php endif; ?>
            </td>
            <td style="font-size:.8rem">
              <?= $ajour($l['validite_visite_medicale']) ?>
              <?php if ($l['visite_medicale_fichier']): ?>
                <br><a href="/admin/doc-inscription.php?i=<?= (int) $l['id'] ?>&t=medicale" target="_blank" rel="noopener">Voir le fichier</a>
              <?php endif; ?>
            </td>
            <td style="font-size:.82rem">
              <?php foreach (['cotisation' => 'Cotisation reçue', 'licence' => 'Licence à jour', 'medicale' => 'Médicale à jour'] as $cle => $lib):
                  $tr = inscription_trace($l, $cle, $nomsValideurs); ?>
                <label style="display:flex;gap:.4rem;align-items:flex-start">
                  <input type="checkbox" name="<?= $cle ?>_ok" value="1" <?= $ff ?> <?= $l[$cle . '_ok'] ? 'checked' : '' ?>>
                  <span><?= $lib ?><?php if ($tr): ?><br><span class="muet" style="font-size:.72rem"><?= e($tr) ?></span><?php endif; ?></span>
                </label>
              <?php endforeach; ?>
            </td>
            <td class="nombre" style="white-space:nowrap">
              <button type="submit" <?= $ff ?> class="btn btn--petit">Enregistrer</button>
              <?php if ($l['m_email'] ?: $l['courriel']): ?>
                <button type="submit" form="lienf-<?= (int) $l['id'] ?>" class="btn btn--contour btn--petit"
                        data-confirmer="Envoyer le lien de réinscription à <?= e((string) ($l['m_email'] ?: $l['courriel'])) ?> ?">Envoyer le lien</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php endif; /* onglet */ ?>

<?php require __DIR__ . '/inc/pied.php'; ?>
