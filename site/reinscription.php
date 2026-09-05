<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/inscription.php';
require_once __DIR__ . '/inc/mail.php';
session_demarrer();

$page = 'reinscription';
$titre = 'Réinscription ' . COTISATION_ANNEE;
$description = 'Renouvelez votre adhésion au Saumur Air Club en ligne.';

$membre = membre_connecte();
if (!$membre) {
    $_SESSION['apres_connexion'] = '/reinscription';
    header('Location: ' . url('adherents'), true, 303);
    exit;
}

$annee   = COTISATION_ANNEE;
$dossierDocs = __DIR__ . '/docs-inscriptions/' . (int) $membre['id'];
$erreurs = [];
$info    = null;
$etape   = 'formulaire';   // formulaire | paiement | recu

/** Enregistre un document envoyé, retourne le chemin relatif ou null. */
$enregistrerDoc = static function (string $champ, string $prefixe) use ($dossierDocs, $annee, &$erreurs, $membre): ?string {
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
    $nom = $annee . '-' . $prefixe . '.' . $ext;
    if (!move_uploaded_file($tmp, $dossierDocs . '/' . $nom)) return null;
    return (int) $membre['id'] . '/' . $nom;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $erreurs['csrf'] = 'Votre session a expiré. Merci de renvoyer le formulaire.';
        $d = inscription_depuis_post($_POST);
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'paiement') {
            // Choix du mode de règlement sur une inscription déjà complète.
            $ins = inscription_annee((int) $membre['id'], $annee);
            $mode = ($_POST['mode'] ?? '') === 'virement' ? 'virement' : '';
            if ($ins && $ins['statut'] !== 'brouillon' && $mode === 'virement') {
                db()->prepare('UPDATE inscriptions SET mode_paiement = ? WHERE id = ?')
                    ->execute(['virement', (int) $ins['id']]);
                journaliser('inscription.virement', 'inscription#' . $ins['id']);
                $etape = 'recu';
                $d = inscription_annee((int) $membre['id'], $annee);
            } else {
                $etape = 'paiement';
                $d = $ins ?: inscription_prefill($membre, $annee);
            }
        } else {
            // Enregistrement (brouillon) ou soumission (complet).
            $d = inscription_depuis_post($_POST);

            $lic = $enregistrerDoc('licence_fichier', 'licence');
            $med = $enregistrerDoc('visite_medicale_fichier', 'visite-medicale');
            // On conserve un document déjà présent si aucun nouveau n'est envoyé.
            $ancien = inscription_annee((int) $membre['id'], $annee);
            $ligneLic = $lic ?? ($ancien['licence_fichier'] ?? null);
            $ligneMed = $med ?? ($ancien['visite_medicale_fichier'] ?? null);

            if (!$erreurs) {
                if ($action === 'soumettre') {
                    $erreurs = inscription_manquants($d);
                    // Licence + visite médicale sont obligatoires pour un dossier complet.
                    if (!$ligneLic) $erreurs['licence_fichier'] = 'Licence pilote obligatoire (à joindre).';
                    if (!$ligneMed) $erreurs['visite_medicale_fichier'] = 'Visite médicale obligatoire (à joindre).';
                }
                $statut = (!$erreurs && $action === 'soumettre') ? 'complet' : 'brouillon';
                $id = inscription_enregistrer((int) $membre['id'], $annee, $d, $statut);
                // Documents (colonnes hors INSCRIPTION_CHAMPS) mis à jour à part.
                db()->prepare('UPDATE inscriptions SET licence_fichier = ?, visite_medicale_fichier = ? WHERE id = ?')
                    ->execute([$ligneLic, $ligneMed, $id]);

                if ($statut === 'complet') {
                    $etape = 'paiement';
                    $info  = 'Votre dossier est complet. Dernière étape : le règlement.';
                } else {
                    $info = $action === 'soumettre'
                        ? 'Il manque quelques informations : votre saisie est enregistrée en brouillon.'
                        : 'Brouillon enregistré. Vous pourrez le reprendre à tout moment.';
                }
                $d = inscription_annee((int) $membre['id'], $annee);
            }
        }
    }
} else {
    $d = inscription_prefill($membre, $annee);
    if (($d['statut'] ?? 'brouillon') !== 'brouillon' && ($_GET['paiement'] ?? '') === '1') {
        $etape = 'paiement';
    }
}

$total = inscription_total($d);
$mineur = inscription_est_mineur($d);

// À l'étape paiement, on mémorise l'inscription à régler (pour /paiement-inscription).
if ($etape === 'paiement' && !empty($d['id'])) {
    $_SESSION['inscription_en_cours'] = (int) $d['id'];
}

/** Helpers d'affichage. */
$val = static fn(string $c): string => e((string) ($d[$c] ?? ''));
$errCls = static fn(string $c): string => isset($erreurs[$c]) ? ' champ--erreur' : '';
$extrasChoisis = explode(',', (string) ($d['extras'] ?? ''));

require __DIR__ . '/inc/header.php';
?>
<section class="section">
  <div class="conteneur conteneur--etroit">

    <p class="surtitre">Espace adhérents</p>
    <h1>Réinscription <?= $annee ?></h1>
    <p class="chapo">
      Bonjour <?= e($membre['prenom']) ?>, renouvelez votre adhésion en quelques minutes.
      Vos informations de l’an dernier sont déjà pré-remplies : vérifiez-les et corrigez ce qui a changé.
    </p>

    <?php if ($info): ?><div class="alerte alerte--info" role="status"><?= e($info) ?></div><?php endif; ?>
    <?php if (isset($erreurs['csrf'])): ?><div class="alerte alerte--erreur" role="alert"><?= e($erreurs['csrf']) ?></div><?php endif; ?>
    <?php if ($erreurs && !isset($erreurs['csrf'])): ?>
      <div class="alerte alerte--erreur" role="alert">
        <strong>Il manque quelques informations pour valider le dossier.</strong>
        Vous pouvez tout de même l’enregistrer en brouillon.
      </div>
    <?php endif; ?>

<?php if ($etape === 'recu'): ?>

    <div class="carte-recu">
      <h2>Merci, votre demande est enregistrée</h2>
      <p>Vous avez choisi le règlement par <strong>virement bancaire</strong>. Voici les coordonnées :</p>
      <dl class="paire">
        <dt>Bénéficiaire</dt><dd><?= e(CLUB['nom']) ?></dd>
        <dt>IBAN</dt><dd class="code-bon">FR76 —— à compléter par le club ——</dd>
        <dt>Référence à indiquer</dt><dd class="code-bon">RÉINSCRIPTION <?= $annee ?> — <?= e($membre['nom']) ?></dd>
        <dt>Montant</dt><dd><strong><?= e(prix($total)) ?></strong></dd>
      </dl>
      <p class="aide">Votre adhésion sera activée dès réception du virement par le secrétariat.</p>
      <a class="bouton bouton--secondaire" href="<?= e(url('adherents')) ?>">Retour à mon espace</a>
    </div>

<?php elseif ($etape === 'paiement'): ?>

    <div class="carte-recap">
      <h2>Récapitulatif</h2>
      <p><?= e(inscription_resume_cotisation($d)) ?></p>
      <p class="recap-total">Total à régler <strong><?= e(prix($total)) ?></strong></p>
    </div>

    <div class="paiement-choix">
      <div class="paiement-option">
        <h3>Carte bancaire</h3>
        <p class="aide">Paiement en ligne sécurisé (Stripe).</p>
        <a class="bouton" href="/paiement-inscription">Payer <?= e(prix($total)) ?></a>
      </div>
      <div class="paiement-option">
        <h3>Virement bancaire</h3>
        <p class="aide">Vous recevez les coordonnées et réglez depuis votre banque.</p>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <input type="hidden" name="action" value="paiement">
          <input type="hidden" name="mode" value="virement">
          <button class="bouton bouton--secondaire" type="submit">Choisir le virement</button>
        </form>
      </div>
    </div>
    <p style="margin-top:1rem"><a href="/reinscription">← Modifier mon dossier</a></p>

<?php else: ?>

    <form class="formulaire" method="post" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

      <input type="hidden" name="type" value="renouvellement">

      <fieldset class="bloc-form">
        <legend>État civil</legend>
        <div class="champs champs--duo">
          <div class="champ<?= $errCls('nom') ?>"><label for="nom">Nom</label><input type="text" id="nom" name="nom" value="<?= $val('nom') ?>" maxlength="120"></div>
          <div class="champ<?= $errCls('prenom') ?>"><label for="prenom">Prénom</label><input type="text" id="prenom" name="prenom" value="<?= $val('prenom') ?>" maxlength="120"></div>
        </div>
        <div class="champs champs--duo">
          <div class="champ"><label for="nationalite">Nationalité</label><input type="text" id="nationalite" name="nationalite" value="<?= $val('nationalite') ?>" maxlength="80"></div>
          <div class="champ<?= $errCls('date_naissance') ?>"><label for="date_naissance">Date de naissance</label><input type="date" id="date_naissance" name="date_naissance" value="<?= $val('date_naissance') ?>"></div>
        </div>
        <div class="champs champs--duo">
          <div class="champ"><label for="lieu_naissance">Lieu de naissance</label><input type="text" id="lieu_naissance" name="lieu_naissance" value="<?= $val('lieu_naissance') ?>" maxlength="120"></div>
          <div class="champ"><label for="profession">Profession</label><input type="text" id="profession" name="profession" value="<?= $val('profession') ?>" maxlength="120"></div>
        </div>
        <div class="champ<?= $errCls('adresse') ?>"><label for="adresse">Adresse personnelle</label><input type="text" id="adresse" name="adresse" value="<?= $val('adresse') ?>" maxlength="255"></div>
        <div class="champs champs--duo">
          <div class="champ"><label for="tel_perso">Tél. personnel</label><input type="tel" id="tel_perso" name="tel_perso" value="<?= $val('tel_perso') ?>" maxlength="30"></div>
          <div class="champ"><label for="tel_pro">Tél. professionnel</label><input type="tel" id="tel_pro" name="tel_pro" value="<?= $val('tel_pro') ?>" maxlength="30"></div>
        </div>
        <div class="champs champs--duo">
          <div class="champ<?= $errCls('tel_mobile') ?>"><label for="tel_mobile">Mobile</label><input type="tel" id="tel_mobile" name="tel_mobile" value="<?= $val('tel_mobile') ?>" maxlength="30"></div>
          <div class="champ<?= $errCls('courriel') ?>"><label for="courriel">Courriel</label><input type="email" id="courriel" name="courriel" value="<?= $val('courriel') ?>" maxlength="180"></div>
        </div>
        <div class="champ"><label for="urgence">Personne à prévenir / téléphone d’urgence</label><input type="text" id="urgence" name="urgence" value="<?= $val('urgence') ?>" maxlength="180"></div>
      </fieldset>

      <fieldset class="bloc-form">
        <legend>Titres aéronautiques</legend>
        <div class="champs champs--duo">
          <div class="champ"><label for="lapl_num">LAPL n°</label><input type="text" id="lapl_num" name="lapl_num" value="<?= $val('lapl_num') ?>" maxlength="60"></div>
          <div class="champ"><label for="lapl_date">Date d’obtention LAPL</label><input type="date" id="lapl_date" name="lapl_date" value="<?= $val('lapl_date') ?>"></div>
        </div>
        <div class="champs champs--duo">
          <div class="champ"><label for="ppl_num">PPL n°</label><input type="text" id="ppl_num" name="ppl_num" value="<?= $val('ppl_num') ?>" maxlength="60"></div>
          <div class="champ"><label for="ppl_date">Date d’obtention PPL</label><input type="date" id="ppl_date" name="ppl_date" value="<?= $val('ppl_date') ?>"></div>
        </div>
        <div class="champs champs--duo">
          <div class="champ"><label for="validite_licence">Validité licence</label><input type="date" id="validite_licence" name="validite_licence" value="<?= $val('validite_licence') ?>"></div>
          <div class="champ"><label for="validite_sep">Validité SEP</label><input type="date" id="validite_sep" name="validite_sep" value="<?= $val('validite_sep') ?>"></div>
        </div>
        <div class="champ"><label for="autres_qualifs">Autres qualifications</label><input type="text" id="autres_qualifs" name="autres_qualifs" value="<?= $val('autres_qualifs') ?>" maxlength="255"></div>
        <div class="champs champs--duo">
          <div class="champ"><label for="validite_visite_medicale">Validité visite médicale</label><input type="date" id="validite_visite_medicale" name="validite_visite_medicale" value="<?= $val('validite_visite_medicale') ?>"></div>
          <div class="champ"><label for="num_ffa">N° FFA</label><input type="text" id="num_ffa" name="num_ffa" value="<?= $val('num_ffa') ?>" maxlength="60"></div>
        </div>
      </fieldset>

      <fieldset class="bloc-form" id="bloc-mineur"<?= $mineur ? '' : ' style="display:none"' ?>>
        <legend>Membre mineur — représentants légaux</legend>
        <div class="champs champs--duo">
          <div class="champ<?= $errCls('pere_nom') ?>"><label for="pere_nom">Nom / prénom du père</label><input type="text" id="pere_nom" name="pere_nom" value="<?= $val('pere_nom') ?>" maxlength="120"></div>
          <div class="champ"><label for="mere_nom">Nom / prénom de la mère</label><input type="text" id="mere_nom" name="mere_nom" value="<?= $val('mere_nom') ?>" maxlength="120"></div>
        </div>
        <label class="champ-case"><input type="checkbox" name="autorisation_parentale" value="1" <?= !empty($d['autorisation_parentale']) ? 'checked' : '' ?>>
          <span>J’autorise mon enfant à pratiquer les sports aériens (autorisation parentale jointe).</span></label>
      </fieldset>

      <fieldset class="bloc-form">
        <legend>Cotisation <?= $annee ?></legend>
        <p class="aide">A — Membre Club : <?= e(prix(COTISATION_MEMBRE_CLUB)) ?> (obligatoire, inclus).</p>

        <p style="font-weight:600;margin:.5rem 0 .25rem">B — Choisissez votre option</p>
        <div class="cotis-options">
          <?php foreach (COTISATION_OPTIONS as $k => [$lib, $c]): ?>
            <label class="bon-radio"><input type="radio" name="option_cotisation" value="<?= e($k) ?>"<?= ($d['option_cotisation'] ?? '') === $k ? ' checked' : '' ?>>
              <span><?= e($lib) ?> — <?= e(prix($c)) ?></span></label>
          <?php endforeach; ?>
        </div>
        <div class="champ" id="bloc-passeport"<?= ($d['option_cotisation'] ?? '') === 'opt5' ? '' : ' style="display:none"' ?>>
          <label for="passeport_bloc">Bloc d’heures (Passeport FFA)</label>
          <select id="passeport_bloc" name="passeport_bloc">
            <?php foreach (COTISATION_PASSEPORT_BLOCS as $k => [$lib, $c]): ?>
              <option value="<?= e($k) ?>"<?= ($d['passeport_bloc'] ?? '') === $k ? ' selected' : '' ?>><?= e($lib) ?><?= $c ? ' — ' . e(prix($c)) : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <p style="font-weight:600;margin:.75rem 0 .25rem">Options complémentaires</p>
        <?php foreach (COTISATION_EXTRAS as $k => [$lib, $c, $g]): ?>
          <label class="champ-case"><input type="checkbox" name="extras[]" value="<?= e($k) ?>"<?= in_array($k, $extrasChoisis, true) ? ' checked' : '' ?>>
            <span><?= e($lib) ?> — <?= e(prix($c)) ?></span></label>
        <?php endforeach; ?>
      </fieldset>

      <fieldset class="bloc-form">
        <legend>Documents obligatoires</legend>
        <p class="aide">Licence pilote <strong>et</strong> visite médicale, à jour au 31/12/<?= $annee ?>.
          PDF ou photo, 8 Mo maximum. <strong>Obligatoires pour valider et payer</strong> — sans eux,
          vous pouvez seulement enregistrer un brouillon.</p>
        <div class="champs champs--duo">
          <div class="champ<?= $errCls('licence_fichier') ?>">
            <label for="licence_fichier">Licence pilote (FFA / SEP) <span class="obligatoire" aria-hidden="true">*</span>
              <?php if (!empty($d['licence_fichier'])): ?><span class="etat etat--paye">déjà envoyée</span><?php endif; ?></label>
            <input type="file" id="licence_fichier" name="licence_fichier" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic">
            <?php if (isset($erreurs['licence_fichier'])): ?><p class="champ__erreur"><?= e($erreurs['licence_fichier']) ?></p><?php endif; ?>
          </div>
          <div class="champ<?= $errCls('visite_medicale_fichier') ?>">
            <label for="visite_medicale_fichier">Certificat médical <span class="obligatoire" aria-hidden="true">*</span>
              <?php if (!empty($d['visite_medicale_fichier'])): ?><span class="etat etat--paye">déjà envoyé</span><?php endif; ?></label>
            <input type="file" id="visite_medicale_fichier" name="visite_medicale_fichier" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic">
            <?php if (isset($erreurs['visite_medicale_fichier'])): ?><p class="champ__erreur"><?= e($erreurs['visite_medicale_fichier']) ?></p><?php endif; ?>
          </div>
        </div>
      </fieldset>

      <fieldset class="bloc-form">
        <legend>Assemblée générale &amp; règlement</legend>
        <div class="champ">
          <label>Convocation à l’assemblée générale par</label>
          <div class="bon-choix">
            <label class="bon-radio"><input type="radio" name="convocation_ag" value="courriel" <?= ($d['convocation_ag'] ?? 'courriel') !== 'courrier' ? 'checked' : '' ?>><span>Courriel</span></label>
            <label class="bon-radio"><input type="radio" name="convocation_ag" value="courrier" <?= ($d['convocation_ag'] ?? '') === 'courrier' ? 'checked' : '' ?>><span>Courrier postal</span></label>
          </div>
        </div>
        <label class="champ-case<?= isset($erreurs['rgpd_accepte']) ? ' champ-case--erreur' : '' ?>">
          <input type="checkbox" name="rgpd_accepte" value="1" <?= !empty($d['rgpd_accepte']) ? 'checked' : '' ?>>
          <span>Je reconnais avoir pris connaissance des statuts, du règlement intérieur et du RGPD,
            et m’engage à m’y conformer.</span>
        </label>
      </fieldset>

      <div class="formulaire__pied">
        <p class="formulaire__total">Total <?= $annee ?><br><strong id="total-cotis"><?= e(prix($total)) ?></strong></p>
        <div class="actions">
          <button type="submit" name="action" value="enregistrer" class="bouton bouton--secondaire">Enregistrer le brouillon</button>
          <button type="submit" name="action" value="soumettre" class="bouton">Valider et payer</button>
        </div>
      </div>
    </form>

    <script>
    (function () {
      var PRIX = <?= json_encode([
          'membre' => COTISATION_MEMBRE_CLUB,
          'options' => array_map(fn($o) => $o[1], COTISATION_OPTIONS),
          'blocs' => array_map(fn($b) => $b[1], COTISATION_PASSEPORT_BLOCS),
          'extras' => array_map(fn($x) => $x[1], COTISATION_EXTRAS),
      ]) ?>;
      var f = document.querySelector('.formulaire');
      function euros(c){ return (c % 100 ? (c/100).toFixed(2).replace('.', ',') : (c/100)) + ' €'; }
      function maj() {
        var t = PRIX.membre;
        var opt = (f.querySelector('input[name="option_cotisation"]:checked')||{}).value || '';
        if (PRIX.options[opt] != null) t += PRIX.options[opt];
        var pass = document.getElementById('bloc-passeport');
        pass.style.display = (opt === 'opt5') ? '' : 'none';
        if (opt === 'opt5') { var b = f.querySelector('#passeport_bloc').value; t += PRIX.blocs[b] || 0; }
        f.querySelectorAll('input[name="extras[]"]:checked').forEach(function(x){ t += PRIX.extras[x.value] || 0; });
        document.getElementById('total-cotis').textContent = euros(t);
        // Bloc mineur selon l'âge.
        var dn = f.querySelector('#date_naissance').value, mineur = false;
        if (dn) { var age = (Date.now() - new Date(dn).getTime()) / (365.25*864e5); mineur = age < 18; }
        document.getElementById('bloc-mineur').style.display = mineur ? '' : 'none';
      }
      f.addEventListener('change', maj); maj();
    })();
    </script>

<?php endif; ?>

  </div>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
