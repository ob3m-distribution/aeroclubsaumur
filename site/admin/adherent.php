<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/inscription.php';
require_once __DIR__ . '/../inc/mail.php';
exiger_droit('membres.gerer');

$id = (int) ($_GET['i'] ?? 0);

/** Étapes de traitement d'une demande de pré-inscription. */
const DEMANDE_ETAPES = [
    'rencontre'  => 'Rencontre avec le bureau effectuée',
    'licence'    => 'Licence pilote fournie',
    'medicale'   => 'Visite médicale fournie',
    'cotisation' => 'Paiement de la cotisation réalisé',
];

/** Colonnes modifiables depuis la fiche B.O. */
const DOSSIER_CHAMPS = [
    'nom', 'prenom', 'nationalite', 'date_naissance', 'lieu_naissance', 'profession', 'adresse',
    'tel_perso', 'tel_pro', 'tel_mobile', 'courriel', 'urgence',
    'lapl_num', 'lapl_date', 'ppl_num', 'ppl_date', 'validite_licence', 'validite_sep',
    'autres_qualifs', 'validite_visite_medicale', 'num_ffa', 'pere_nom', 'mere_nom',
];
const DOSSIER_DATES = ['date_naissance', 'lapl_date', 'ppl_date', 'validite_licence', 'validite_sep', 'validite_visite_medicale'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $id) {
    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $_SESSION['message_erreur'] = 'Session expirée, rien enregistré.';
        header('Location: /admin/adherent.php?i=' . $id, true, 303);
        exit;
    }
    $action = (string) ($_POST['action'] ?? 'enregistrer');
    $moiId = (int) (membre_connecte()['id'] ?? 0);

    /** Enregistre un fichier envoyé sous docs-inscriptions/<id>/, retourne le chemin relatif ou null. */
    $uploadFichier = function (string $champ, string $prefixe) use ($id): ?string {
        if (empty($_FILES[$champ]['name']) || ($_FILES[$champ]['error'] ?? 1) !== UPLOAD_ERR_OK
            || !is_uploaded_file($_FILES[$champ]['tmp_name'])) return null;
        $ext = strtolower(pathinfo((string) $_FILES[$champ]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic'], true)) return null;
        if ((int) filesize($_FILES[$champ]['tmp_name']) > 8 * 1024 * 1024) return null;
        $dir = __DIR__ . '/../docs-inscriptions/' . $id;
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $nom = $prefixe . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($_FILES[$champ]['tmp_name'], $dir . '/' . $nom)) return null;
        return $id . '/' . $nom;
    };

    if ($action === 'ajouter_doc') {
        $rel = $uploadFichier('document', 'doc');
        $nom = trim((string) ($_POST['nom_doc'] ?? '')) ?: 'Document';
        if ($rel) {
            db()->prepare('INSERT INTO inscription_documents (inscription_id, nom, fichier) VALUES (?,?,?)')
                ->execute([$id, mb_substr($nom, 0, 150), $rel]);
            journaliser('inscription.doc_ajoute', 'inscription#' . $id, $nom);
            $_SESSION['message_succes'] = 'Document ajouté : ' . $nom;
        } else {
            $_SESSION['message_erreur'] = 'Document refusé (PDF, JPG, PNG, WEBP ou HEIC, 8 Mo max).';
        }
        header('Location: /admin/adherent.php?i=' . $id, true, 303);
        exit;
    }

    if ($action === 'supprimer_doc') {
        $docId = (int) ($_POST['doc_id'] ?? 0);
        $q = db()->prepare('SELECT fichier FROM inscription_documents WHERE id = ? AND inscription_id = ?');
        $q->execute([$docId, $id]);
        $f = (string) ($q->fetchColumn() ?: '');
        if ($f !== '') {
            db()->prepare('DELETE FROM inscription_documents WHERE id = ?')->execute([$docId]);
            if (preg_match('#^\d+/[\w.\-]+$#', $f)) @unlink(__DIR__ . '/../docs-inscriptions/' . $f);
            $_SESSION['message_succes'] = 'Document supprimé.';
        }
        header('Location: /admin/adherent.php?i=' . $id, true, 303);
        exit;
    }

    if ($action === 'traiter') {
        // Cases de suivi d'une demande, avec traçabilité (qui/quand).
        foreach (array_keys(DEMANDE_ETAPES) as $nom) {
            $v = !empty($_POST[$nom . '_ok']) ? 1 : 0;
            db()->prepare("UPDATE inscriptions SET
                    {$nom}_ok = ?,
                    {$nom}_ok_le  = CASE WHEN ? = 1 THEN COALESCE({$nom}_ok_le, NOW()) ELSE NULL END,
                    {$nom}_ok_par = CASE WHEN ? = 1 THEN COALESCE({$nom}_ok_par, ?) ELSE NULL END
                  WHERE id = ?")->execute([$v, $v, $v, $moiId, $id]);
        }
        journaliser('demande.traitee', 'inscription#' . $id);
        $_SESSION['message_succes'] = 'Suivi de la demande mis à jour.';
        header('Location: /admin/adherent.php?i=' . $id, true, 303);
        exit;
    }

    if ($action === 'convertir') {
        $dem = db()->prepare('SELECT * FROM inscriptions WHERE id = ? AND type = "demande"');
        $dem->execute([$id]);
        $dem = $dem->fetch();
        $manque = $dem ? array_filter(array_keys(DEMANDE_ETAPES), fn($k) => !$dem[$k . '_ok']) : ['*'];
        if (!$dem) {
            $_SESSION['message_erreur'] = 'Demande introuvable.';
        } elseif ($manque) {
            $_SESSION['message_erreur'] = 'Toutes les étapes ne sont pas validées.';
        } elseif (!filter_var($dem['courriel'], FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message_erreur'] = 'La demande n’a pas d’e-mail valide.';
        } else {
            $email = mb_strtolower(trim((string) $dem['courriel']));
            $ex = db()->prepare('SELECT id FROM membres WHERE email = ? LIMIT 1');
            $ex->execute([$email]);
            $mid = (int) ($ex->fetchColumn() ?: 0);
            if (!$mid) {
                db()->prepare('INSERT INTO membres (prenom, nom, email, mot_de_passe_hash, role, actif)
                               VALUES (?,?,?,?,\'adherent\',0)')
                    ->execute([$dem['prenom'], $dem['nom'], $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
                $mid = (int) db()->lastInsertId();
                $token = creer_token_reset($mid, 72);
                $lien = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'dev.aeroclub-saumur.fr') . '/reinitialiser-mot-de-passe?token=' . $token;
                @email_lien_mot_de_passe($email, (string) $dem['prenom'], $lien, true);
            }
            db()->prepare('UPDATE inscriptions SET membre_id = ?, type = "renouvellement", statut = "valide",
                           valide_le = COALESCE(valide_le, NOW()), valide_par = COALESCE(valide_par, ?) WHERE id = ?')
                ->execute([$mid, $moiId, $id]);
            journaliser('demande.convertie', 'inscription#' . $id, $email);
            $_SESSION['message_succes'] = 'Demande convertie en membre officiel. Une invitation à créer '
                . 'son mot de passe a été envoyée à ' . $email . '.';
            header('Location: /admin/suivi-membres.php?onglet=reinscription#i-' . $id, true, 303);
            exit;
        }
        header('Location: /admin/adherent.php?i=' . $id, true, 303);
        exit;
    }

    if ($action === 'lien_paiement') {
        $dem = db()->prepare('SELECT * FROM inscriptions WHERE id = ?');
        $dem->execute([$id]);
        $dem = $dem->fetch();
        $mode = ($_POST['mode'] ?? '') === 'carte' ? 'carte' : 'virement';
        if (!$dem || !filter_var($dem['courriel'], FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message_erreur'] = 'Ce dossier n’a pas d’e-mail valide.';
        } else {
            $ref = 'INSCRIPTION ' . (int) $dem['annee'] . ' — ' . $dem['nom'];
            $ok = email_lien_paiement((string) $dem['courriel'], (string) $dem['prenom'], $mode,
                                      (int) $dem['total_cents'], $ref);
            db()->prepare('UPDATE inscriptions SET mode_paiement = ? WHERE id = ?')->execute([$mode, $id]);
            journaliser('inscription.lien_paiement', 'inscription#' . $id, $mode);
            $_SESSION['message_succes'] = $ok
                ? 'Lien de paiement (' . $mode . ') envoyé à ' . $dem['courriel'] . '.'
                : 'L’e-mail n’a pas pu être envoyé.';
        }
        header('Location: /admin/adherent.php?i=' . $id, true, 303);
        exit;
    }

    $set = [];
    $vals = [];
    foreach (DOSSIER_CHAMPS as $c) {
        $v = trim((string) ($_POST[$c] ?? ''));
        if ($v === '' && in_array($c, DOSSIER_DATES, true)) $v = null;
        $set[] = "$c = ?";
        $vals[] = $v;
    }

    // Photo (upload facultatif).
    if (!empty($_FILES['photo']['name']) && ($_FILES['photo']['error'] ?? 1) === UPLOAD_ERR_OK
        && is_uploaded_file($_FILES['photo']['tmp_name'])) {
        $ext = strtolower(pathinfo((string) $_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic'], true) && (int) filesize($_FILES['photo']['tmp_name']) <= 8 * 1024 * 1024) {
            $dir = __DIR__ . '/../docs-inscriptions/photos';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $nom = $id . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . '/' . $nom)) {
                $set[] = 'photo = ?';
                $vals[] = 'photos/' . $nom;
            }
        } else {
            $_SESSION['message_erreur'] = 'Photo refusée (JPG/PNG/WEBP/HEIC, 8 Mo max).';
        }
    }

    // Licence + certificat médical (remplacement / ajout depuis la fiche).
    if ($lic = $uploadFichier('licence_fichier', 'licence'))          { $set[] = 'licence_fichier = ?';          $vals[] = $lic; }
    if ($med = $uploadFichier('visite_medicale_fichier', 'medicale')) { $set[] = 'visite_medicale_fichier = ?'; $vals[] = $med; }

    $vals[] = $id;
    db()->prepare('UPDATE inscriptions SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals);
    journaliser('inscription.dossier_maj', 'inscription#' . $id);
    if (empty($_SESSION['message_erreur'])) $_SESSION['message_succes'] = 'Dossier enregistré.';
    header('Location: /admin/adherent.php?i=' . $id, true, 303);
    exit;
}

$s = db()->prepare(
    'SELECT i.*, m.email AS m_email, v.prenom AS v_prenom, v.nom AS v_nom
       FROM inscriptions i
       LEFT JOIN membres m ON m.id = i.membre_id
       LEFT JOIN membres v ON v.id = i.valide_par
      WHERE i.id = ?'
);
$s->execute([$id]);
$a = $s->fetch();
if (!$a) {
    $_SESSION['message_erreur'] = 'Dossier introuvable.';
    header('Location: /admin/suivi-membres.php', true, 303);
    exit;
}

$estDemande = ($a['type'] === 'demande');
$titre = 'Dossier — ' . trim($a['prenom'] . ' ' . $a['nom']);
$actif = 'suivi';
require __DIR__ . '/inc/entete.php';

$val = static fn(string $c): string => e((string) ($a[$c] ?? ''));
$nomsValideurs = inscription_noms_membres([$a['cotisation_ok_par'] ?? 0, $a['licence_ok_par'] ?? 0, $a['medicale_ok_par'] ?? 0, $a['rencontre_ok_par'] ?? 0]);
$dq = db()->prepare('SELECT * FROM inscription_documents WHERE inscription_id = ? ORDER BY cree_le');
$dq->execute([$id]);
$docsSupp = $dq->fetchAll();
$statuts = ['brouillon'=>['Brouillon','attente'],'complet'=>['Complet','planifie'],'paye'=>['Payé','paye'],'valide'=>['Validé','paye']];
[$stLib, $stCls] = $statuts[$a['statut']] ?? [$a['statut'], 'attente'];
$champ = static function (string $c, string $label, string $type = 'text') use ($val): string {
    return '<div class="champ"><label for="' . $c . '">' . e($label) . '</label>'
         . '<input type="' . $type . '" id="' . $c . '" name="' . $c . '" value="' . $val($c) . '"'
         . ($type === 'text' ? ' maxlength="255"' : '') . '></div>';
};
?>

<p><a href="/admin/suivi-membres.php?onglet=<?= $estDemande ? 'nouvelle' : 'reinscription' ?>">← Retour à la liste</a></p>

<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
  <input type="hidden" name="action" value="enregistrer">

  <div class="detail">
    <div>
      <div class="bloc">
        <div class="bloc__titre">
          <h2>État civil</h2>
          <span class="etat etat--<?= e($stCls) ?>"><?= e($stLib) ?><?= $estDemande ? ' · demande' : '' ?></span>
        </div>
        <div class="champs champs--duo"><?= $champ('nom', 'Nom') . $champ('prenom', 'Prénom') ?></div>
        <div class="champs champs--duo"><?= $champ('nationalite', 'Nationalité') . $champ('date_naissance', 'Date de naissance', 'date') ?></div>
        <div class="champs champs--duo"><?= $champ('lieu_naissance', 'Lieu de naissance') . $champ('profession', 'Profession') ?></div>
        <?= $champ('adresse', 'Adresse') ?>
        <div class="champs champs--duo"><?= $champ('tel_perso', 'Tél. personnel', 'tel') . $champ('tel_pro', 'Tél. professionnel', 'tel') ?></div>
        <div class="champs champs--duo"><?= $champ('tel_mobile', 'Mobile', 'tel') . $champ('courriel', 'Courriel', 'email') ?></div>
        <?= $champ('urgence', 'Personne à prévenir / urgence') ?>
      </div>

      <div class="bloc">
        <h2>Titres aéronautiques</h2>
        <div class="champs champs--duo"><?= $champ('lapl_num', 'LAPL n°') . $champ('lapl_date', 'Obtention LAPL', 'date') ?></div>
        <div class="champs champs--duo"><?= $champ('ppl_num', 'PPL n°') . $champ('ppl_date', 'Obtention PPL', 'date') ?></div>
        <div class="champs champs--duo"><?= $champ('validite_licence', 'Validité licence', 'date') . $champ('validite_sep', 'Validité SEP', 'date') ?></div>
        <?= $champ('autres_qualifs', 'Autres qualifications') ?>
        <div class="champs champs--duo"><?= $champ('validite_visite_medicale', 'Validité visite médicale', 'date') . $champ('num_ffa', 'N° FFA') ?></div>
      </div>

      <div class="bloc">
        <h2>Représentants légaux (mineur)</h2>
        <div class="champs champs--duo"><?= $champ('pere_nom', 'Nom / prénom du père') . $champ('mere_nom', 'Nom / prénom de la mère') ?></div>
      </div>
    </div>

    <div>
      <div class="bloc">
        <h2>Photo</h2>
        <div class="dossier-photo">
          <?php if (!empty($a['photo'])): ?>
            <img src="/admin/doc-inscription.php?i=<?= $id ?>&t=photo&x=<?= urlencode((string) $a['maj_le']) ?>" alt="Photo du membre">
          <?php else: ?>
            <div class="dossier-photo__vide">Aucune photo</div>
          <?php endif; ?>
        </div>
        <div class="champ" style="margin-top:.6rem">
          <label for="photo">Ajouter / remplacer la photo</label>
          <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp,.heic">
          <p class="aide">JPG, PNG, WEBP ou HEIC — 8 Mo maximum.</p>
        </div>
      </div>

      <div class="bloc">
        <h2>Cotisation <?= (int) $a['annee'] ?></h2>
        <p><?= e(inscription_resume_cotisation($a)) ?></p>
        <p class="recap-total" style="margin:.4rem 0 0">Total <strong><?= e(prix((int) $a['total_cents'])) ?></strong>
          <?php if ($a['mode_paiement']): ?><span class="muet"> · <?= e($a['mode_paiement']) ?></span><?php endif; ?></p>
        <p style="margin:.5rem 0 0"><span class="etat etat--<?= e($stCls) ?>"><?= e($stLib) ?></span></p>
      </div>

      <div class="bloc">
        <h2>Documents obligatoires</h2>
        <div class="champ">
          <label for="licence_fichier">Licence pilote (FFA / SEP)
            <?php if ($a['licence_fichier']): ?><a href="/admin/doc-inscription.php?i=<?= $id ?>&t=licence" target="_blank" rel="noopener" class="etat etat--paye">voir</a><?php else: ?><span class="muet">non fournie</span><?php endif; ?></label>
          <input type="file" id="licence_fichier" name="licence_fichier" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic">
        </div>
        <div class="champ" style="margin-top:.6rem">
          <label for="visite_medicale_fichier">Certificat médical
            <?php if ($a['visite_medicale_fichier']): ?><a href="/admin/doc-inscription.php?i=<?= $id ?>&t=medicale" target="_blank" rel="noopener" class="etat etat--paye">voir</a><?php else: ?><span class="muet">non fourni</span><?php endif; ?></label>
          <input type="file" id="visite_medicale_fichier" name="visite_medicale_fichier" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic">
        </div>
        <p class="aide" style="margin:.4rem 0 0">Le remplacement est pris en compte au clic sur « Enregistrer le dossier ».</p>
      </div>

      <?php if (!$estDemande): ?>
      <div class="bloc">
        <h2>Validation</h2>
        <dl class="paire">
          <?php foreach (['cotisation' => 'Cotisation reçue', 'licence' => 'Licence à jour', 'medicale' => 'Médicale à jour'] as $cle => $lib):
              $tr = inscription_trace($a, $cle, $nomsValideurs); ?>
            <dt><?= $lib ?></dt>
            <dd><?= $a[$cle . '_ok'] ? 'Oui' . ($tr ? ' <span class="muet" style="font-size:.8rem">' . e($tr) . '</span>' : '') : '<span class="muet">Non</span>' ?></dd>
          <?php endforeach; ?>
        </dl>
        <p style="margin:.5rem 0 0"><a class="btn btn--contour btn--petit" href="/admin/suivi-membres.php?onglet=reinscription#i-<?= $id ?>">Modifier la validation →</a></p>
      </div>
      <?php endif; ?>

      <div class="bloc">
        <h2>Suivi</h2>
        <dl class="paire">
          <dt>Type</dt><dd><?= $estDemande ? 'Demande d’inscription' : 'Renouvellement' ?></dd>
          <dt>Compte</dt><dd><?= $a['m_email'] ? e($a['m_email']) : '<span class="muet">aucun compte lié</span>' ?></dd>
          <dt>Reçu le</dt><dd><?= e(date('d/m/Y à H:i', strtotime((string) $a['cree_le']))) ?></dd>
        </dl>
      </div>
    </div>
  </div>

  <div class="actions" style="margin-top:1rem;padding:.75rem 0">
    <button type="submit" class="btn">Enregistrer le dossier</button>
    <a class="btn btn--contour" href="/admin/suivi-membres.php?onglet=<?= $estDemande ? 'nouvelle' : 'reinscription' ?>">Annuler</a>
  </div>
</form>

<div class="bloc">
  <h2>Autres documents</h2>
  <p class="aide" style="margin:0 0 .75rem">Ajoutez d’autres pièces au dossier (attestation, autorisation
    parentale, RIB…) en leur donnant un nom.</p>
  <?php if ($docsSupp): ?>
    <ul class="docs-supp">
      <?php foreach ($docsSupp as $ds): ?>
        <li>
          <span class="docs-supp__nom"><?= e($ds['nom']) ?></span>
          <span class="muet" style="font-size:.75rem"><?= e(date('d/m/Y', strtotime((string) $ds['cree_le']))) ?></span>
          <a class="btn btn--contour btn--petit" href="/admin/doc-inscription.php?d=<?= (int) $ds['id'] ?>" target="_blank" rel="noopener">Voir</a>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
            <input type="hidden" name="action" value="supprimer_doc">
            <input type="hidden" name="doc_id" value="<?= (int) $ds['id'] ?>">
            <button type="submit" class="btn btn--danger btn--petit" data-confirmer="Supprimer ce document ?">Supprimer</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="docs-supp__ajout">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
    <input type="hidden" name="action" value="ajouter_doc">
    <input type="text" name="nom_doc" placeholder="Nom du document" maxlength="150" required>
    <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic" required>
    <button type="submit" class="btn">Ajouter</button>
  </form>
</div>

<?php if ($estDemande):
    $pret = $a['rencontre_ok'] && $a['licence_ok'] && $a['medicale_ok'] && $a['cotisation_ok'];
    $faits = count(array_filter(array_keys(DEMANDE_ETAPES), fn($k) => $a[$k . '_ok']));
    // Dossier complet = tous les champs requis + les deux documents obligatoires.
    $manqueDossier = inscription_manquants($a);
    $dossierComplet = !$manqueDossier && $a['licence_fichier'] && $a['visite_medicale_fichier'];
?>
<div class="bloc" style="border-left:3px solid var(--or,#b08d2c)">
  <div class="bloc__titre">
    <h2>Traitement de la demande</h2>
    <span class="etat etat--<?= $pret ? 'paye">Prête à valider' : 'planifie">' . $faits . '/4 étape' . ($faits > 1 ? 's' : '') ?></span>
  </div>
  <p class="aide" style="margin:0 0 .8rem">
    Un membre du bureau contacte la personne, la reçoit, vérifie son dossier et ses documents,
    encaisse la cotisation, puis coche les étapes ci-dessous. Une fois tout validé, convertissez
    la demande en membre officiel : un compte est créé et une invitation lui est envoyée.
  </p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
    <input type="hidden" name="action" value="traiter">
    <?php foreach (DEMANDE_ETAPES as $cle => $lib): $tr = inscription_trace($a, $cle, $nomsValideurs); ?>
      <label style="display:flex;gap:.5rem;align-items:flex-start;padding:.3rem 0">
        <input type="checkbox" name="<?= $cle ?>_ok" value="1"<?= $a[$cle . '_ok'] ? ' checked' : '' ?>>
        <span><?= e($lib) ?><?php if ($tr): ?><br><span class="muet" style="font-size:.75rem"><?= e($tr) ?></span><?php endif; ?></span>
      </label>
    <?php endforeach; ?>
    <div class="actions" style="margin-top:.8rem">
      <button type="submit" class="btn btn--contour">Enregistrer le suivi</button>
    </div>
  </form>

  <hr style="border:none;border-top:1px solid var(--gris-200);margin:1rem 0">
  <?php if ($pret): ?>
    <form method="post" onsubmit="return confirm('Convertir cette demande en membre officiel ? Un compte adhérent sera créé et une invitation envoyée.');">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <input type="hidden" name="action" value="convertir">
      <button type="submit" class="btn">✓ Valider et convertir en membre officiel</button>
    </form>
  <?php else: ?>
    <p class="muet" style="margin:0">La conversion en membre sera possible une fois les 4 étapes cochées.</p>
  <?php endif; ?>
</div>

<div class="bloc">
  <div class="bloc__titre">
    <h2>Lien de paiement</h2>
    <span class="etat etat--<?= $dossierComplet ? 'paye">Dossier complet' : 'attente">Dossier incomplet' ?></span>
  </div>
  <?php if ($dossierComplet): ?>
    <p class="aide" style="margin:0 0 .8rem">
      Le dossier est complet (informations + licence + visite médicale). Vous pouvez envoyer au
      futur membre son lien de règlement — cotisation de <strong><?= e(prix((int) $a['total_cents'])) ?></strong>.
    </p>
    <form method="post" class="suivi-lien">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <input type="hidden" name="action" value="lien_paiement">
      <label class="champ" style="margin:0">
        <span style="font-size:.85rem;font-weight:500">Mode de paiement</span>
        <select name="mode" style="font-family:inherit;padding:.45rem .6rem;border:1px solid var(--gris-300);border-radius:6px">
          <option value="virement">Virement bancaire</option>
          <option value="carte">Carte bancaire (Stripe)</option>
        </select>
      </label>
      <button type="submit" class="btn">Envoyer le lien de paiement</button>
    </form>
    <?php if ($a['mode_paiement']): ?>
      <p class="muet" style="margin:.6rem 0 0;font-size:.8rem">Dernier lien envoyé : <?= e($a['mode_paiement']) ?>.</p>
    <?php endif; ?>
  <?php else: ?>
    <p class="muet" style="margin:0">
      Le lien de paiement pourra être envoyé une fois le dossier complet :
      <?php $il = [];
        foreach ($manqueDossier as $m) { $il[] = $m; }
        if (!$a['licence_fichier']) $il[] = 'Licence pilote à joindre.';
        if (!$a['visite_medicale_fichier']) $il[] = 'Certificat médical à joindre.';
        echo e(implode(' ', $il) ?: 'informations à compléter.');
      ?>
    </p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/inc/pied.php'; ?>
