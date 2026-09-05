<?php
declare(strict_types=1);

/**
 * Formulaire d'achat du bon cadeau.
 * Attend $erreurs (tableau champ => message) et $valeurs (saisie a reafficher).
 */
$erreurs = $erreurs ?? [];
$valeurs = $valeurs ?? [];

/** Valeur a reafficher apres une erreur, pour ne pas faire retaper l'utilisateur. */
$v = static fn(string $c): string => e($valeurs[$c] ?? '');
/** Classe et attributs d'un champ en erreur. */
$classe = static fn(string $c): string => isset($erreurs[$c]) ? ' champ--erreur' : '';
$decrit = static fn(string $c): string => isset($erreurs[$c]) ? ' aria-describedby="err-' . $c . '" aria-invalid="true"' : '';
?>

<form class="formulaire" method="post" action="#formulaire" novalidate id="formulaire">

  <?php if ($erreurs): ?>
    <div class="alerte alerte--erreur" role="alert" tabindex="-1" id="recap-erreurs">
      <strong>Votre demande n’a pas pu être envoyée.</strong>
      <ul>
        <?php foreach ($erreurs as $champ => $message): ?>
          <li><a href="#<?= e($champ) ?>"><?= e($message) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

  <!-- Piège à robots : un humain ne le voit pas, un script le remplit. -->
  <div class="piege" aria-hidden="true">
    <label for="site-web">Ne pas remplir ce champ</label>
    <input type="text" id="site-web" name="site_web" tabindex="-1" autocomplete="off">
  </div>

  <div class="champs">

    <?php $typeSel = $valeurs['type_vol'] ?? 'decouverte'; $nbSel = (int) ($valeurs['nb_passagers'] ?? 1);
          $dureeSel = $valeurs['duree_initiation'] ?? '1h30'; ?>

    <fieldset class="champ bon-type<?= $classe('type_vol') ?>">
      <legend>Type de vol <span class="obligatoire" aria-hidden="true">*</span></legend>
      <div class="bon-choix">
        <label class="bon-radio"><input type="radio" name="type_vol" value="decouverte" <?= $typeSel === 'decouverte' ? 'checked' : '' ?>><span>Vol découverte</span></label>
        <label class="bon-radio"><input type="radio" name="type_vol" value="initiation" <?= $typeSel === 'initiation' ? 'checked' : '' ?>><span>Vol d’initiation</span></label>
      </div>
      <?php if (isset($erreurs['type_vol'])): ?><p class="champ__erreur"><?= e($erreurs['type_vol']) ?></p><?php endif; ?>
    </fieldset>

    <div class="champ bon-opt" data-opt="decouverte">
      <label for="nb_passagers">Nombre de personnes</label>
      <select id="nb_passagers" name="nb_passagers">
        <?php foreach (VOLS_DECOUVERTE as $nb => $c): ?>
          <option value="<?= $nb ?>"<?= $nbSel === $nb ? ' selected' : '' ?>><?= $nb ?> personne<?= $nb > 1 ? 's' : '' ?> — <?= e(prix($c)) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($erreurs['nb_passagers'])): ?><p class="champ__erreur"><?= e($erreurs['nb_passagers']) ?></p><?php endif; ?>
    </div>

    <div class="champ bon-opt" data-opt="decouverte">
      <label>À qui offrez-vous ce vol ? <span class="aide">(facultatif)</span></label>
      <div id="benefs">
        <?php for ($i = 0; $i < 3; $i++): ?>
          <div class="bon-benef" data-i="<?= $i ?>">
            <input type="text" name="benef_prenom[]" placeholder="Prénom" value="<?= e($valeurs['benef_prenom'][$i] ?? '') ?>">
            <input type="text" name="benef_nom[]" placeholder="Nom" value="<?= e($valeurs['benef_nom'][$i] ?? '') ?>">
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <div class="champ bon-opt" data-opt="initiation">
      <label for="duree_initiation">Durée du vol d’initiation</label>
      <select id="duree_initiation" name="duree_initiation">
        <?php foreach (VOLS_INITIATION as $dur => $c): ?>
          <option value="<?= e($dur) ?>"<?= $dureeSel === $dur ? ' selected' : '' ?>><?= e(str_replace('h', ' h ', $dur)) ?> — <?= e(prix($c)) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($erreurs['duree_initiation'])): ?><p class="champ__erreur"><?= e($erreurs['duree_initiation']) ?></p><?php endif; ?>
    </div>

    <div class="champs champs--duo">
      <div class="champ<?= $classe('prenom') ?>">
        <label for="prenom">Prénom <span class="obligatoire" aria-hidden="true">*</span></label>
        <input type="text" id="prenom" name="prenom" value="<?= $v('prenom') ?>"
               autocomplete="given-name" maxlength="80" required<?= $decrit('prenom') ?>>
        <?php if (isset($erreurs['prenom'])): ?>
          <p class="champ__erreur" id="err-prenom"><?= e($erreurs['prenom']) ?></p>
        <?php endif; ?>
      </div>

      <div class="champ<?= $classe('nom') ?>">
        <label for="nom">Nom <span class="obligatoire" aria-hidden="true">*</span></label>
        <input type="text" id="nom" name="nom" value="<?= $v('nom') ?>"
               autocomplete="family-name" maxlength="80" required<?= $decrit('nom') ?>>
        <?php if (isset($erreurs['nom'])): ?>
          <p class="champ__erreur" id="err-nom"><?= e($erreurs['nom']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <div class="champ<?= $classe('email') ?>">
      <label for="email">Adresse email <span class="obligatoire" aria-hidden="true">*</span></label>
      <input type="email" id="email" name="email" value="<?= $v('email') ?>"
             autocomplete="email" maxlength="180" required<?= $decrit('email') ?>>
      <p class="aide">C’est à cette adresse que le bon cadeau vous sera envoyé.</p>
      <?php if (isset($erreurs['email'])): ?>
        <p class="champ__erreur" id="err-email"><?= e($erreurs['email']) ?></p>
      <?php endif; ?>
    </div>

    <div class="champ<?= $classe('telephone') ?>">
      <label for="telephone">Téléphone <span class="obligatoire" aria-hidden="true">*</span></label>
      <input type="tel" id="telephone" name="telephone" value="<?= $v('telephone') ?>"
             autocomplete="tel" maxlength="30" required<?= $decrit('telephone') ?>>
      <p class="aide">Le club vous rappelle si besoin.</p>
      <?php if (isset($erreurs['telephone'])): ?>
        <p class="champ__erreur" id="err-telephone"><?= e($erreurs['telephone']) ?></p>
      <?php endif; ?>
    </div>

    <div class="champ<?= $classe('message') ?>">
      <label for="message">Message <span class="aide">(facultatif)</span></label>
      <textarea id="message" name="message" maxlength="1000"<?= $decrit('message') ?>><?= $v('message') ?></textarea>
      <p class="aide">Une occasion particulière, une question ? Dites-nous tout.</p>
      <?php if (isset($erreurs['message'])): ?>
        <p class="champ__erreur" id="err-message"><?= e($erreurs['message']) ?></p>
      <?php endif; ?>
    </div>

    <div class="champ<?= $classe('cgv') ?>">
      <label class="champ-case<?= isset($erreurs['cgv']) ? ' champ-case--erreur' : '' ?>" for="cgv">
        <input type="checkbox" id="cgv" name="cgv" value="1"
               <?= !empty($valeurs['cgv']) ? 'checked' : '' ?> required<?= $decrit('cgv') ?>>
        <span>
          J’ai lu et j’accepte les <a href="/cgv" target="_blank" rel="noopener">conditions
          générales de vente</a> et la <a href="/confidentialite" target="_blank" rel="noopener">politique
          de confidentialité</a>. <span class="obligatoire" aria-hidden="true">*</span>
        </span>
      </label>
      <?php if (isset($erreurs['cgv'])): ?>
        <p class="champ__erreur" id="err-cgv"><?= e($erreurs['cgv']) ?></p>
      <?php endif; ?>
    </div>

  </div>

  <div class="formulaire__pied">
    <p class="formulaire__total">
      Montant à régler<br>
      <strong id="bon-total"><?= e(prix(prix_bon($typeSel, $nbSel, $dureeSel))) ?></strong>
    </p>
    <button type="submit" class="bouton" data-envoi>Procéder au paiement</button>
  </div>

  <script>
  (function () {
    var f = document.getElementById('formulaire');
    var PRIX_DEC = <?= json_encode(VOLS_DECOUVERTE) ?>, PRIX_INI = <?= json_encode(VOLS_INITIATION) ?>;
    function euros(c){ return (c % 100 ? (c/100).toFixed(2).replace('.', ',') : (c/100)) + ' €'; }
    function type(){ var r=f.querySelector('input[name="type_vol"]:checked'); return r ? r.value : 'decouverte'; }
    function maj(){
      var t = type();
      f.querySelectorAll('.bon-opt').forEach(function(o){ o.style.display = (o.getAttribute('data-opt') === t) ? '' : 'none'; });
      var nb = parseInt(f.querySelector('#nb_passagers').value, 10) || 1;
      f.querySelectorAll('#benefs .bon-benef').forEach(function(b){ b.style.display = (parseInt(b.getAttribute('data-i'),10) < nb) ? '' : 'none'; });
      var c = (t === 'initiation') ? PRIX_INI[f.querySelector('#duree_initiation').value] : PRIX_DEC[nb];
      var tot = document.getElementById('bon-total'); if (tot && c) tot.textContent = euros(c);
    }
    f.querySelectorAll('input[name="type_vol"], #nb_passagers, #duree_initiation').forEach(function(el){ el.addEventListener('change', maj); });
    maj();
  })();
  </script>

  <p class="aide" style="margin-top:1rem">
    <span class="obligatoire" aria-hidden="true">*</span> Champs obligatoires.
    Vous réglerez par carte bancaire à l’étape suivante. Vos coordonnées
    bancaires ne transitent jamais par notre site.
  </p>
</form>
