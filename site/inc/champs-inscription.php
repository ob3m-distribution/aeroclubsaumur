<?php
/**
 * Champs communs aux formulaires de réinscription (adhérent connecté) et de
 * pré-inscription (public). Attend, définis par la page appelante :
 *   $d, $val, $errCls, $mineur, $extrasChoisis, $annee
 *   $docsObligatoires (bool)  — licence + médicale requis ou non
 *   $boutonsHtml (string)     — les boutons du pied de formulaire
 *   $labelTotal (string)      — libellé du total (ex. « Total 2027 »)
 */
declare(strict_types=1);
?>
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

      <?php require __DIR__ . '/cotisation-inscription.php'; ?>

      <fieldset class="bloc-form">
        <legend>Documents<?= $docsObligatoires ? ' obligatoires' : '' ?></legend>
        <?php if ($docsObligatoires): ?>
          <p class="aide">Licence pilote <strong>et</strong> visite médicale, à jour au 31/12/<?= $annee ?>.
            PDF ou photo, 8 Mo maximum. <strong>Obligatoires pour valider et payer</strong> — sans eux,
            vous pouvez seulement enregistrer un brouillon.</p>
        <?php else: ?>
          <p class="aide">Licence pilote et visite médicale (facultatif à ce stade — ils seront
            demandés à l’inscription définitive). PDF ou photo, 8 Mo maximum.</p>
        <?php endif; ?>
        <div class="champs champs--duo">
          <div class="champ<?= $errCls('licence_fichier') ?>">
            <label for="licence_fichier">Licence pilote (FFA / SEP)<?= $docsObligatoires ? ' <span class="obligatoire" aria-hidden="true">*</span>' : '' ?>
              <?php if (!empty($d['licence_fichier'])): ?><span class="etat etat--paye">déjà envoyée</span><?php endif; ?></label>
            <input type="file" id="licence_fichier" name="licence_fichier" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic">
            <?php if (isset($erreurs['licence_fichier'])): ?><p class="champ__erreur"><?= e($erreurs['licence_fichier']) ?></p><?php endif; ?>
          </div>
          <div class="champ<?= $errCls('visite_medicale_fichier') ?>">
            <label for="visite_medicale_fichier">Certificat médical<?= $docsObligatoires ? ' <span class="obligatoire" aria-hidden="true">*</span>' : '' ?>
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
        <p class="formulaire__total"><?= e($labelTotal) ?><br><strong id="total-cotis"><?= e(prix(inscription_total($d))) ?></strong></p>
        <div class="actions"><?= $boutonsHtml ?></div>
      </div>
    </form>

    <script>
    (function () {

      var f = document.querySelector('.formulaire');
      function euros(c){ return (c % 100 ? (c/100).toFixed(2).replace('.', ',') : (c/100)) + ' €'; }
      function maj() {
        var t = window.totalCotisation(f);
        document.getElementById('total-cotis').textContent = euros(t);
        var dn = f.querySelector('#date_naissance').value, mineur = false;
        if (dn) { var age = (Date.now() - new Date(dn).getTime()) / (365.25*864e5); mineur = age < 18; }
        document.getElementById('bloc-mineur').style.display = mineur ? '' : 'none';
      }
      f.addEventListener('change', maj); maj();
    })();
    </script>
