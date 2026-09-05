<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/inscription.php';
require_once __DIR__ . '/inc/mail.php';
session_demarrer();

$page = 'preinscription';
$titre = 'Demande d’inscription';
$description = 'Rejoignez le Saumur Air Club — demande d’inscription en ligne.';

$annee   = COTISATION_ANNEE;
$erreurs = [];
$envoye  = false;
$d = ['convocation_ag' => 'courriel'];

$dossierDocs = __DIR__ . '/docs-inscriptions/demandes';

/** Enregistre un document facultatif, retourne son chemin relatif ou null. */
$enregistrerDoc = static function (string $champ, string $prefixe) use ($dossierDocs, &$erreurs): ?string {
    if (empty($_FILES[$champ]['name']) || ($_FILES[$champ]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $tmp = $_FILES[$champ]['tmp_name'];
    if (!is_uploaded_file($tmp)) return null;
    $ext = strtolower(pathinfo((string) $_FILES[$champ]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic'], true)) {
        $erreurs[$champ] = 'Format accepté : PDF, JPG, PNG, WEBP ou HEIC.';
        return null;
    }
    if ((int) filesize($tmp) > 8 * 1024 * 1024) {
        $erreurs[$champ] = 'Fichier trop lourd (8 Mo maximum).';
        return null;
    }
    if (!is_dir($dossierDocs)) { @mkdir($dossierDocs, 0755, true); }
    $nom = $prefixe . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dossierDocs . '/' . $nom)) return null;
    return 'demandes/' . $nom;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $erreurs['csrf'] = 'Votre session a expiré. Merci de renvoyer le formulaire.';
        $d = inscription_depuis_post($_POST);
    } else {
        $d = inscription_depuis_post($_POST);
        $lic = $enregistrerDoc('licence_fichier', 'licence');
        $med = $enregistrerDoc('visite_medicale_fichier', 'visite-medicale');
        if (!$erreurs) {
            $erreurs = inscription_manquants($d);
            // Licence pilote + certificat médical désormais obligatoires.
            if (!$lic) $erreurs['licence_fichier'] = 'Licence pilote obligatoire (à joindre).';
            if (!$med) $erreurs['visite_medicale_fichier'] = 'Certificat médical obligatoire (à joindre).';
            if (!$erreurs) {
                $id = preinscription_creer($annee, $d, $lic, $med);
                @email_notification_club([
                    'reference' => 'PRÉINSCRIPTION #' . $id,
                    'montant_affiche' => prix(inscription_total($d)),
                    'prenom' => $d['prenom'] ?? '', 'nom' => $d['nom'] ?? '',
                    'email' => $d['courriel'] ?? '', 'telephone' => $d['tel_mobile'] ?? '',
                    'message' => '',
                ]);
                journaliser('preinscription.demande', 'inscription#' . $id, (string) ($d['courriel'] ?? ''));
                $_SESSION['inscription_en_cours'] = (int) $id;   // pour le paiement carte
                $envoye = true;
            }
        }
    }
}

$mineur = inscription_est_mineur($d);
$total = inscription_total($d);
$val = static fn(string $c): string => e((string) ($d[$c] ?? ''));
$errCls = static fn(string $c): string => isset($erreurs[$c]) ? ' champ--erreur' : '';
$extrasChoisis = explode(',', (string) ($d['extras'] ?? ''));

require __DIR__ . '/inc/header.php';
?>
<section class="section">
  <div class="conteneur conteneur--etroit">

    <p class="surtitre">Nous rejoindre</p>
    <h1>Demande d’inscription</h1>

<?php if ($envoye): ?>

    <div class="carte-recu">
      <h2>Merci, votre demande est enregistrée</h2>
      <p>Votre dossier est complet. Dernière étape : le règlement de votre cotisation
        de <strong><?= e(prix($total)) ?></strong>.</p>
    </div>

    <div class="paiement-choix">
      <div class="paiement-option">
        <h3>Carte bancaire</h3>
        <p class="aide">Paiement en ligne sécurisé (Stripe).</p>
        <a class="bouton" href="/paiement-inscription">Payer <?= e(prix($total)) ?></a>
      </div>
      <div class="paiement-option">
        <h3>Virement bancaire</h3>
        <dl class="paire" style="text-align:left">
          <dt>Bénéficiaire</dt><dd><?= e(CLUB['nom']) ?></dd>
          <dt>IBAN</dt><dd class="code-bon">FR76 —— à compléter par le club ——</dd>
          <dt>Référence</dt><dd class="code-bon">INSCRIPTION <?= COTISATION_ANNEE ?> — <?= e((string) ($d['nom'] ?? '')) ?></dd>
          <dt>Montant</dt><dd><strong><?= e(prix($total)) ?></strong></dd>
        </dl>
      </div>
    </div>
    <p class="aide" style="margin-top:1rem">Un membre du bureau vous contactera pour finaliser votre adhésion.
      Vous pouvez aussi régler après votre rencontre. <a href="/">Retour à l’accueil</a></p>

<?php else: ?>

    <p class="chapo">Renseignez vos informations pour rejoindre le Saumur Air Club :
      état civil, titres, cotisation souhaitée, et vos documents obligatoires (licence + visite médicale).</p>

    <?php if (isset($erreurs['csrf'])): ?><div class="alerte alerte--erreur" role="alert"><?= e($erreurs['csrf']) ?></div><?php endif; ?>
    <?php if ($erreurs && !isset($erreurs['csrf'])): ?>
      <div class="alerte alerte--erreur" role="alert"><strong>Quelques informations sont manquantes ou incorrectes.</strong> Merci de vérifier les champs signalés.</div>
    <?php endif; ?>

    <form class="formulaire" method="post" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <input type="hidden" name="type" value="demande">
      <?php
        $docsObligatoires = true;
        $labelTotal = 'Total cotisation';
        $boutonsHtml = '<button type="submit" class="bouton">Envoyer ma demande</button>';
        require __DIR__ . '/inc/champs-inscription.php';
      ?>

<?php endif; ?>

  </div>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
