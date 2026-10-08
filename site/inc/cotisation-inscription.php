<?php
declare(strict_types=1);

/**
 * Bloc « Cotisation » des formulaires d'inscription (pré-inscription et
 * réinscription), dans l'ordre de la fiche papier 2027 : A, B, C, D, E, F.
 * Attend $d (dossier), $extrasChoisis (clés cochées) et $annee.
 * Le total s'affiche dans l'élément #total-cotis de la page ; la fonction
 * window.totalCotisation(form) le recalcule (appelée par la page).
 */
$optCourante = (string) ($d['option_cotisation'] ?? '');
$estProgramme = in_array($optCourante, COTISATION_PROGRAMMES_FFA, true);
$sansMembre = in_array('sans_membre', $extrasChoisis, true);
$extrasPrix = cotisation_extras();
$infoPilote = in_array('info_pilote', $extrasChoisis, true) ? 'info_pilote'
            : (in_array('info_pilote_num', $extrasChoisis, true) ? 'info_pilote_num' : '');
$case = static function (string $cle) use ($extrasPrix, $extrasChoisis): string {
    [$lib, $c] = $extrasPrix[$cle];
    return '<label class="champ-case"><input type="checkbox" name="extras[]" value="' . e($cle) . '"'
        . (in_array($cle, $extrasChoisis, true) ? ' checked' : '') . '><span>' . e($lib) . ' — ' . e(prix($c)) . '</span></label>';
};
$options = cotisation_options();
?>
      <fieldset class="bloc-form cotisation">
        <legend>Cotisation <?= (int) $annee ?></legend>

        <p class="cotisation__titre">A — Membre Club</p>
        <label class="champ-case" id="case-membre">
          <input type="checkbox" name="membre_club" value="1" id="membre_club"
                 <?= !($estProgramme && $sansMembre) ? 'checked' : '' ?><?= !$estProgramme ? ' disabled' : '' ?>>
          <span>Membre Club — <?= e(prix(cotisation_membre())) ?>
            <span class="aide" id="aide-membre"><?= $estProgramme ? '(facultatif avec un programme FFA)' : '(obligatoire)' ?></span></span>
        </label>

        <p class="cotisation__titre">B — Choisissez votre option</p>
        <div class="cotis-options">
          <?php foreach (['opt1', 'opt2', 'opt3', 'opt6', 'opt7'] as $k): [$lib, $c] = $options[$k]; ?>
            <label class="bon-radio"><input type="radio" name="option_cotisation" value="<?= e($k) ?>"<?= $optCourante === $k ? ' checked' : '' ?>>
              <span><?= e($lib) ?> — <?= e(prix($c)) ?></span></label>
          <?php endforeach; ?>
        </div>
        <?= $case('caution_badge') ?>

        <p class="cotisation__titre">C — Info Pilote <span class="aide">(facultatif)</span></p>
        <div class="bon-choix">
          <label class="bon-radio"><input type="radio" name="info_pilote" value=""<?= $infoPilote === '' ? ' checked' : '' ?>><span>Sans abonnement</span></label>
          <?php foreach (['info_pilote', 'info_pilote_num'] as $k): [$lib, $c] = $extrasPrix[$k]; ?>
            <label class="bon-radio"><input type="radio" name="info_pilote" value="<?= e($k) ?>"<?= $infoPilote === $k ? ' checked' : '' ?>><span><?= $k === 'info_pilote' ? 'Papier' : 'Numérique' ?> — <?= e(prix($c)) ?></span></label>
          <?php endforeach; ?>
        </div>

        <p class="cotisation__titre">D — Licence</p>
        <?= $case('licence_ffa') ?>
        <?= $case('licence_ffa_bia') ?>

        <p class="cotisation__titre">E — Programmes FFA <span class="aide">(sans inscription Membre Club : A facultative)</span></p>
        <div class="cotis-options">
          <?php foreach (['opt4', 'opt5'] as $k): [$lib, $c] = $options[$k]; ?>
            <label class="bon-radio"><input type="radio" name="option_cotisation" value="<?= e($k) ?>"<?= $optCourante === $k ? ' checked' : '' ?>>
              <span><?= e(str_replace(' (programme FFA)', '', $lib)) ?> — <?= e(prix($c)) ?></span></label>
          <?php endforeach; ?>
        </div>
        <div class="champ" id="bloc-passeport"<?= $optCourante === 'opt5' ? '' : ' style="display:none"' ?>>
          <label for="passeport_bloc">Bloc d’heures (Passeport FFA)</label>
          <select id="passeport_bloc" name="passeport_bloc">
            <?php foreach (cotisation_blocs() as $k => [$lib, $c]): ?>
              <option value="<?= e($k) ?>"<?= ($d['passeport_bloc'] ?? '') === $k ? ' selected' : '' ?>><?= e($lib) ?><?= $c ? ' — ' . e(prix($c)) : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <p class="cotisation__titre">F — Formation <span class="aide">(facultatif)</span></p>
        <?= $case('pack_basique') ?>
        <?= $case('elearning') ?>
      </fieldset>

      <script>
      /* Total de la cotisation : même calcul que total_inscription() (config.php). */
      /* D : une seule des deux licences FFA à la fois. */
      document.addEventListener('change', function (ev) {
        var t = ev.target;
        if (!t || t.name !== 'extras[]' || (t.value !== 'licence_ffa' && t.value !== 'licence_ffa_bia') || !t.checked) return;
        var autre = t.form.querySelector('input[name="extras[]"][value="' + (t.value === 'licence_ffa' ? 'licence_ffa_bia' : 'licence_ffa') + '"]');
        if (autre) autre.checked = false;
      });
      window.totalCotisation = function (f) {
        var P = <?= json_encode([
            'membre'     => cotisation_membre(),
            'options'    => array_map(fn($o) => $o[1], $options),
            'blocs'      => array_map(fn($b) => $b[1], cotisation_blocs()),
            'extras'     => array_map(fn($x) => $x[1], $extrasPrix),
            'programmes' => COTISATION_PROGRAMMES_FFA,
        ]) ?>;
        var opt = (f.querySelector('input[name="option_cotisation"]:checked') || {}).value || '';
        var prog = P.programmes.indexOf(opt) !== -1;
        var membre = f.querySelector('#membre_club');
        if (!prog) membre.checked = true;
        membre.disabled = !prog;
        document.getElementById('aide-membre').textContent = prog ? '(facultatif avec un programme FFA)' : '(obligatoire)';
        var t = membre.checked ? P.membre : 0;
        if (P.options[opt] != null) t += P.options[opt];
        document.getElementById('bloc-passeport').style.display = (opt === 'opt5') ? '' : 'none';
        if (opt === 'opt5') t += P.blocs[f.querySelector('#passeport_bloc').value] || 0;
        f.querySelectorAll('input[name="extras[]"]:checked').forEach(function (x) { t += P.extras[x.value] || 0; });
        var ip = (f.querySelector('input[name="info_pilote"]:checked') || {}).value || '';
        if (ip) t += P.extras[ip] || 0;
        return t;
      };
      </script>
