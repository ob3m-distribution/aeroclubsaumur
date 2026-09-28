<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/bon-cadeau.php';
require_once __DIR__ . '/../inc/mail.php';
exiger_droit('bons.gerer');

/* Vol découverte / d'initiation vendu au club ou par téléphone : même
   formulaire que la page publique /vols-decouvertes, plus le mode de
   paiement. Voir creer_bon_manuel() pour ce que fait chaque mode. */

$moi     = membre_connecte();
$erreurs = [];
$valeurs = ['mode' => 'especes', 'envoyer' => '1'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $mode = (string) ($_POST['mode'] ?? '');
    $envoyer = !empty($_POST['envoyer']);
    // Les CGV sont acceptées au comptoir : la case n'existe pas ici.
    [$d, $erreurs] = valider_demande(['cgv' => '1'] + $_POST);
    $valeurs = $d + ['mode' => $mode, 'envoyer' => $envoyer ? '1' : '',
                     'benef_prenom' => (array) ($_POST['benef_prenom'] ?? []),
                     'benef_nom' => (array) ($_POST['benef_nom'] ?? [])];

    if (!isset(MODES_PAIEMENT[$mode])) {
        $erreurs['mode'] = 'Choisissez le mode de paiement.';
    }
    // Réglé au club sans envoi par e-mail : e-mail et téléphone facultatifs.
    $emailUtile = !in_array($mode, MODES_AU_CLUB, true) || $envoyer;
    if (!$emailUtile && ($d['email'] ?? '') === '') unset($erreurs['email']);
    if (($d['telephone'] ?? '') === '') unset($erreurs['telephone']);
    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $erreurs['csrf'] = 'Session expirée. Merci de renvoyer le formulaire.';
    }

    if (!$erreurs) {
        try {
            $bon = creer_bon_manuel($d, $mode, (int) $moi['id']);
            $id  = (int) $bon['id'];
            journaliser('bon.ajout_bo', 'bon#' . $id, MODES_PAIEMENT[$mode][1] ?? $mode);

            if (in_array($mode, MODES_AU_CLUB, true)) {
                $msg = 'Bon ' . $bon['numero_bon'] . ' créé (' . statut_bon($bon)[0] . ').';
                if ($envoyer && $bon['acheteur_email'] !== '') {
                    if (email_bon_cadeau($bon)) {
                        bon_marquer_envoye($id);
                        $msg .= ' Envoyé par e-mail à ' . $bon['acheteur_email'] . '.';
                    } else {
                        $msg .= ' L’e-mail n’a pas pu partir : utilisez « Renvoyer le bon par e-mail ».';
                    }
                }
            } elseif ($mode === 'virement') {
                $msg = email_virement_bon($bon)
                    ? 'Demande enregistrée (en attente virement). Le RIB du club a été envoyé à ' . $bon['acheteur_email'] . '.'
                    : 'Demande enregistrée (en attente virement), mais l’e-mail avec le RIB n’a pas pu partir.';
            } else {
                $msg = email_lien_paiement_bon($bon, lien_paiement_bon($bon))
                    ? 'Demande enregistrée (en attente Stripe). Le lien de paiement a été envoyé à ' . $bon['acheteur_email'] . '.'
                    : 'Demande enregistrée (en attente Stripe), mais l’e-mail n’a pas pu partir : utilisez « Renvoyer le lien de paiement ».';
            }
            $_SESSION['message_succes'] = $msg;
            header('Location: /admin/bon.php?id=' . $id, true, 303);
            exit;
        } catch (Throwable $ex) {
            error_log('Bon manuel : ' . $ex->getMessage());
            $erreurs['technique'] = 'Enregistrement impossible : ' . $ex->getMessage();
        }
    }
}

$typeSel  = $valeurs['type_vol'] ?? 'decouverte';
$nbSel    = (int) ($valeurs['nb_passagers'] ?? 1) ?: 1;
$dureeSel = $valeurs['duree_initiation'] ?? '1h30';
$modeSel  = (string) ($valeurs['mode'] ?? 'especes');
$v = static fn(string $c): string => e((string) ($valeurs[$c] ?? ''));

$titre = 'Ajouter un vol';
$actif = 'bons';
require __DIR__ . '/inc/entete.php';
?>

<p><a href="/admin/bons.php">← Retour aux bons cadeaux</a></p>

<form method="post" class="bloc bon-ajout" id="formulaire" data-unique novalidate>
  <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

  <?php if ($erreurs): ?>
    <div class="message message--erreur" role="alert">
      <strong>Le bon n’a pas été créé :</strong>
      <ul style="margin:.35rem 0 0;padding-left:1.1rem">
        <?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <h2>Le vol</h2>
  <div class="bon-choix">
    <label class="bon-radio"><input type="radio" name="type_vol" value="decouverte" <?= $typeSel === 'decouverte' ? 'checked' : '' ?>><span>Vol découverte</span></label>
    <label class="bon-radio"><input type="radio" name="type_vol" value="initiation" <?= $typeSel === 'initiation' ? 'checked' : '' ?>><span>Vol d’initiation</span></label>
  </div>

  <div class="champ bon-opt" data-opt="decouverte">
    <label for="nb_passagers">Nombre de personnes</label>
    <select id="nb_passagers" name="nb_passagers">
      <?php foreach (vols_decouverte() as $nb => $c): ?>
        <option value="<?= $nb ?>"<?= $nbSel === $nb ? ' selected' : '' ?>><?= $nb ?> personne<?= $nb > 1 ? 's' : '' ?> — <?= e(prix($c)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="champ bon-opt" data-opt="decouverte">
    <label>Bénéficiaire(s) <span class="aide">(facultatif)</span></label>
    <div id="benefs">
      <?php for ($i = 0; $i < 3; $i++): ?>
        <div class="champs champs--duo bon-benef" data-i="<?= $i ?>">
          <input type="text" name="benef_prenom[]" placeholder="Prénom" value="<?= e($valeurs['benef_prenom'][$i] ?? '') ?>">
          <input type="text" name="benef_nom[]" placeholder="Nom" value="<?= e($valeurs['benef_nom'][$i] ?? '') ?>">
        </div>
      <?php endfor; ?>
    </div>
  </div>

  <div class="champ bon-opt" data-opt="initiation">
    <label for="duree_initiation">Durée du vol d’initiation</label>
    <select id="duree_initiation" name="duree_initiation">
      <?php foreach (vols_initiation() as $dur => $c): ?>
        <option value="<?= e($dur) ?>"<?= $dureeSel === $dur ? ' selected' : '' ?>><?= e(str_replace('h', ' h ', $dur)) ?> — <?= e(prix($c)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <h2 style="margin-top:1.5rem">L’acheteur</h2>
  <div class="champs champs--duo">
    <div class="champ"><label for="prenom">Prénom *</label><input type="text" id="prenom" name="prenom" maxlength="80" required value="<?= $v('prenom') ?>"></div>
    <div class="champ"><label for="nom">Nom *</label><input type="text" id="nom" name="nom" maxlength="80" required value="<?= $v('nom') ?>"></div>
    <div class="champ"><label for="email">E-mail <span class="aide" id="email-aide"></span></label><input type="email" id="email" name="email" maxlength="180" value="<?= $v('email') ?>"></div>
    <div class="champ"><label for="telephone">Téléphone</label><input type="tel" id="telephone" name="telephone" maxlength="30" value="<?= $v('telephone') ?>"></div>
  </div>
  <div class="champ">
    <label for="message">Note <span class="aide">(facultatif, interne)</span></label>
    <textarea id="message" name="message" maxlength="1000" rows="2"><?= $v('message') ?></textarea>
  </div>

  <h2 style="margin-top:1.5rem">Le paiement</h2>
  <?php
    /* [icône, titre court, ce qui se passe] — affichage en deux familles. */
    $cartesPaiement = [
      'especes'  => ['💶', 'Espèces',              'Réglé sur place'],
      'cheque'   => ['🧾', 'Chèque',               'Réglé sur place'],
      'cb_club'  => ['💳', 'Carte bancaire (TPE)', 'Réglé sur place'],
      'virement' => ['🏦', 'Virement bancaire',    'Le RIB du club part par e-mail'],
      'stripe'   => ['🔗', 'Carte bancaire en ligne', 'Un lien de paiement part par e-mail'],
    ];
    $famillesPaiement = [
      ['Le client paie maintenant, au club', 'Le bon est payé et numéroté tout de suite.', MODES_AU_CLUB],
      ['Le client paiera plus tard, à distance', 'Le bon reste « en attente » jusqu’au paiement, sans numéro.', ['virement', 'stripe']],
    ];
  ?>
  <div class="paiement-familles">
    <?php foreach ($famillesPaiement as [$titreFam, $sousTitreFam, $modesFam]): ?>
      <fieldset class="paiement-famille">
        <legend><?= e($titreFam) ?></legend>
        <p class="paiement-famille__aide"><?= e($sousTitreFam) ?></p>
        <div class="paiement-cartes">
          <?php foreach ($modesFam as $k): [$ico, $titreMode, $effet] = $cartesPaiement[$k]; ?>
            <label class="paiement-carte">
              <input type="radio" name="mode" value="<?= e($k) ?>" <?= $modeSel === $k ? 'checked' : '' ?>>
              <span class="paiement-carte__ico" aria-hidden="true"><?= $ico ?></span>
              <span class="paiement-carte__txt"><strong><?= e($titreMode) ?></strong><small><?= e($effet) ?></small></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>
    <?php endforeach; ?>
  </div>

  <div class="paiement-resultat" id="paiement-resultat" aria-live="polite">
    <p class="paiement-resultat__titre">Ce qui va se passer</p>
    <ul id="mode-aide"></ul>
    <label class="champ-case" id="envoyer-case">
      <input type="checkbox" name="envoyer" value="1" <?= !empty($valeurs['envoyer']) ? 'checked' : '' ?>>
      <span>Envoyer aussi le bon cadeau (PDF) par e-mail à l’acheteur</span>
    </label>
  </div>

  <div class="actions" style="margin-top:1.5rem;align-items:center">
    <button type="submit" class="btn">Créer le bon</button>
    <span>Montant : <strong id="bon-total"><?= e(prix(prix_bon($typeSel, $nbSel, $dureeSel))) ?></strong></span>
  </div>
</form>

<script>
(function () {
  var f = document.getElementById('formulaire');
  var PRIX_DEC = <?= json_encode(vols_decouverte()) ?>, PRIX_INI = <?= json_encode(vols_initiation()) ?>;
  var AIDE = {
    especes:  ['Le bon est créé au statut « Payé en espèces », avec son numéro (CLUB-…).',
               'Vous pouvez l’imprimer depuis sa fiche, ou l’envoyer par e-mail (case ci-dessous).'],
    cheque:   ['Le bon est créé au statut « Payé en chèque », avec son numéro (CLUB-…).',
               'Vous pouvez l’imprimer depuis sa fiche, ou l’envoyer par e-mail (case ci-dessous).'],
    cb_club:  ['Le bon est créé au statut « Payé en CB au club », avec son numéro (CLUB-…).',
               'Vous pouvez l’imprimer depuis sa fiche, ou l’envoyer par e-mail (case ci-dessous).'],
    virement: ['Le bon est créé au statut « En attente virement », sans numéro.',
               'Le client reçoit par e-mail le RIB du club et le libellé à indiquer.',
               'À réception du virement : ouvrez la fiche du bon → « Valider le paiement ». Il est alors numéroté et envoyé au client.'],
    stripe:   ['Le bon est créé au statut « En attente Stripe », sans numéro.',
               'Le client reçoit par e-mail un lien pour payer par carte.',
               'Dès qu’il a payé, le bon passe « Payé par Stripe » et lui est envoyé automatiquement.']
  };
  function euros(c){ return (c % 100 ? (c/100).toFixed(2).replace('.', ',') : (c/100)) + ' €'; }
  function val(n){ var r = f.querySelector('input[name="' + n + '"]:checked'); return r ? r.value : ''; }
  function maj(){
    var t = val('type_vol') || 'decouverte', m = val('mode');
    f.querySelectorAll('.bon-opt').forEach(function(o){ o.style.display = (o.getAttribute('data-opt') === t) ? '' : 'none'; });
    var nb = parseInt(f.querySelector('#nb_passagers').value, 10) || 1;
    f.querySelectorAll('#benefs .bon-benef').forEach(function(b){ b.style.display = (parseInt(b.getAttribute('data-i'),10) < nb) ? '' : 'none'; });
    var c = (t === 'initiation') ? PRIX_INI[f.querySelector('#duree_initiation').value] : PRIX_DEC[nb];
    if (c) document.getElementById('bon-total').textContent = euros(c);
    var auClub = ['especes', 'cheque', 'cb_club'].indexOf(m) !== -1;
    var ul = document.getElementById('mode-aide');
    ul.textContent = '';
    (AIDE[m] || ['Choisissez un mode de paiement.']).forEach(function (t) {
      var li = document.createElement('li'); li.textContent = t; ul.appendChild(li);
    });
    document.getElementById('paiement-resultat').className = 'paiement-resultat' + (m ? (auClub ? ' paiement-resultat--paye' : ' paiement-resultat--attente') : '');
    document.getElementById('envoyer-case').style.display = auClub ? '' : 'none';
    var envoi = f.querySelector('input[name="envoyer"]').checked;
    document.getElementById('email-aide').textContent = (!auClub || envoi) ? '(obligatoire)' : '(facultatif)';
  }
  f.querySelectorAll('input[name="type_vol"], input[name="mode"], input[name="envoyer"], #nb_passagers, #duree_initiation')
   .forEach(function(el){ el.addEventListener('change', maj); });
  maj();
})();
</script>

<?php require __DIR__ . '/inc/pied.php'; ?>
