/* ==================================================================
   Mode édition — clic sur un texte ou une photo pour le modifier.
   Aucune dépendance. Le serveur revalide tout : ce fichier ne fait
   que l'interface.
   ================================================================== */
(function () {
  'use strict';

  var MAX_OCTETS = 8 * 1024 * 1024;   // 8 Mo, comme le serveur

  /* ---------- Petits utilitaires ---------------------------------- */

  function bulle(message, erreur) {
    var b = document.createElement('div');
    b.className = 'bulle' + (erreur ? ' bulle--erreur' : '');
    b.setAttribute('role', 'status');
    b.textContent = message;
    document.body.appendChild(b);
    setTimeout(function () { b.remove(); }, 2600);
  }

  function fermer(fond, declencheur) {
    fond.remove();
    document.removeEventListener('keydown', fond._echap);
    if (declencheur && declencheur.focus) declencheur.focus();
  }

  /** Crée la fenêtre et gère fermeture au clic extérieur / Échap. */
  function modale(titre, sousTitre, declencheur) {
    var fond = document.createElement('div');
    fond.className = 'modale-fond';
    fond.innerHTML =
      '<div class="modale" role="dialog" aria-modal="true" aria-label="' + titre + '">' +
        '<div class="modale__entete"><h2></h2><p></p></div>' +
        '<div class="modale__corps"></div>' +
        '<div class="modale__pied"></div>' +
      '</div>';
    fond.querySelector('h2').textContent = titre;
    fond.querySelector('.modale__entete p').textContent = sousTitre || '';

    fond.addEventListener('mousedown', function (ev) {
      if (ev.target === fond) fermer(fond, declencheur);
    });
    fond._echap = function (ev) {
      if (ev.key === 'Escape') fermer(fond, declencheur);
    };
    document.addEventListener('keydown', fond._echap);

    document.body.appendChild(fond);
    return fond;
  }

  function erreurDansModale(fond, message) {
    var corps = fond.querySelector('.modale__corps');
    var e = corps.querySelector('.modale__erreur');
    if (!e) {
      e = document.createElement('div');
      e.className = 'modale__erreur';
      e.setAttribute('role', 'alert');
      corps.insertBefore(e, corps.firstChild);
    }
    e.textContent = message;
  }

  function envoyer(url, corps) {
    return fetch(url, {
      method: 'POST',
      body: corps,
      credentials: 'same-origin',
      headers: { 'X-CSRF': window.SAC_CSRF || '' }
    }).then(function (r) {
      return r.json().catch(function () {
        throw new Error('Réponse inattendue du serveur.');
      }).then(function (j) {
        if (!r.ok || !j.ok) throw new Error(j.erreur || 'Enregistrement impossible.');
        return j;
      });
    });
  }

  /* ---------- Modifier un texte ------------------------------------ */

  function editerTexte(zone) {
    var cle = zone.getAttribute('data-cle');
    var type = zone.getAttribute('data-type') || 'court';
    var long = type === 'long';

    // On repart du texte affiché, sans le crayon.
    var copie = zone.cloneNode(true);
    var crayon = copie.querySelector('.zone-editable__crayon');
    if (crayon) crayon.remove();
    var actuel = copie.innerHTML.replace(/<br\s*\/?>/gi, '\n');
    actuel = copie.textContent === copie.innerHTML
      ? copie.textContent
      : actuel.replace(/<[^>]+>/g, '');

    var fond = modale('Modifier ce texte', 'Il apparaîtra tel quel sur le site.', zone);
    var corps = fond.querySelector('.modale__corps');
    var pied = fond.querySelector('.modale__pied');

    corps.innerHTML =
      '<label for="champ-edition">Texte affiché</label>' +
      (long
        ? '<textarea id="champ-edition"></textarea>'
        : '<input type="text" id="champ-edition">');

    var champ = corps.querySelector('#champ-edition');
    champ.value = actuel.trim();

    pied.innerHTML =
      '<button type="button" class="b b--discret" data-defaut>Remettre le texte d’origine</button>' +
      '<span class="espace"></span>' +
      '<button type="button" class="b b--sobre" data-annuler>Annuler</button>' +
      '<button type="button" class="b" data-valider>Enregistrer</button>';

    champ.focus();
    champ.select();

    function enregistrer(valeur, remiseADefaut) {
      var b = pied.querySelector('[data-valider]');
      b.disabled = true;
      b.textContent = 'Enregistrement…';

      var donnees = new FormData();
      donnees.append('cle', cle);
      donnees.append('type', long ? 'texte_long' : 'texte');
      if (remiseADefaut) {
        donnees.append('reinitialiser', '1');
      } else {
        donnees.append('valeur', valeur);
      }

      envoyer('/admin/api-contenu.php', donnees)
        .then(function () {
          fermer(fond, zone);
          bulle('Modification enregistrée');
          // Rechargement : la page reflète exactement ce qui est stocké.
          setTimeout(function () { window.location.reload(); }, 400);
        })
        .catch(function (err) {
          b.disabled = false;
          b.textContent = 'Enregistrer';
          erreurDansModale(fond, err.message);
        });
    }

    pied.querySelector('[data-valider]').addEventListener('click', function () {
      enregistrer(champ.value, false);
    });
    pied.querySelector('[data-annuler]').addEventListener('click', function () {
      fermer(fond, zone);
    });
    pied.querySelector('[data-defaut]').addEventListener('click', function () {
      if (window.confirm('Remettre le texte d’origine ? Votre version sera perdue.')) {
        enregistrer('', true);
      }
    });

    // Entrée valide un texte court ; Ctrl+Entrée un texte long.
    champ.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' && (!long || ev.ctrlKey || ev.metaKey)) {
        ev.preventDefault();
        enregistrer(champ.value, false);
      }
    });
  }

  /* ---------- Remplacer une photo ----------------------------------- */

  function editerImage(zone) {
    var cle = zone.getAttribute('data-cle');

    var fond = modale('Remplacer cette photo',
      'La photo sera redimensionnée automatiquement. JPEG, PNG ou WebP, 8 Mo maximum.', zone);
    var corps = fond.querySelector('.modale__corps');
    var pied = fond.querySelector('.modale__pied');

    corps.innerHTML =
      '<div class="depot" tabindex="0" role="button">' +
        '<strong>Choisir une photo</strong>' +
        '<small>Cliquez ici, ou faites glisser un fichier</small>' +
      '</div>' +
      '<input type="file" accept="image/jpeg,image/png,image/webp" hidden>' +
      '<div class="apercu" hidden><img alt="Aperçu de la photo choisie"></div>';

    pied.innerHTML =
      '<button type="button" class="b b--discret" data-defaut>Remettre la photo d’origine</button>' +
      '<span class="espace"></span>' +
      '<button type="button" class="b b--sobre" data-annuler>Annuler</button>' +
      '<button type="button" class="b" data-valider disabled>Enregistrer</button>';

    var depot = corps.querySelector('.depot');
    var input = corps.querySelector('input[type=file]');
    var apercu = corps.querySelector('.apercu');
    var img = apercu.querySelector('img');
    var valider = pied.querySelector('[data-valider]');
    var fichier = null;

    function choisir(f) {
      if (!f) return;
      if (!/^image\/(jpeg|png|webp)$/.test(f.type)) {
        erreurDansModale(fond, 'Ce fichier n’est pas une image JPEG, PNG ou WebP.');
        return;
      }
      if (f.size > MAX_OCTETS) {
        erreurDansModale(fond, 'Cette image dépasse 8 Mo. Choisissez-en une plus légère.');
        return;
      }
      fichier = f;
      img.src = URL.createObjectURL(f);
      apercu.hidden = false;
      valider.disabled = false;
      depot.querySelector('strong').textContent = f.name;
    }

    depot.addEventListener('click', function () { input.click(); });
    depot.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); input.click(); }
    });
    input.addEventListener('change', function () { choisir(input.files[0]); });

    ['dragenter', 'dragover'].forEach(function (t) {
      depot.addEventListener(t, function (ev) { ev.preventDefault(); depot.classList.add('survol'); });
    });
    ['dragleave', 'drop'].forEach(function (t) {
      depot.addEventListener(t, function (ev) { ev.preventDefault(); depot.classList.remove('survol'); });
    });
    depot.addEventListener('drop', function (ev) {
      if (ev.dataTransfer.files && ev.dataTransfer.files[0]) choisir(ev.dataTransfer.files[0]);
    });

    valider.addEventListener('click', function () {
      if (!fichier) return;
      valider.disabled = true;
      valider.textContent = 'Envoi…';

      var donnees = new FormData();
      donnees.append('cle', cle);
      donnees.append('image', fichier);

      envoyer('/admin/api-image.php', donnees)
        .then(function () {
          fermer(fond, zone);
          bulle('Photo remplacée');
          setTimeout(function () { window.location.reload(); }, 400);
        })
        .catch(function (err) {
          valider.disabled = false;
          valider.textContent = 'Enregistrer';
          erreurDansModale(fond, err.message);
        });
    });

    pied.querySelector('[data-annuler]').addEventListener('click', function () {
      fermer(fond, zone);
    });
    pied.querySelector('[data-defaut]').addEventListener('click', function () {
      if (!window.confirm('Remettre la photo d’origine ?')) return;
      var donnees = new FormData();
      donnees.append('cle', cle);
      donnees.append('reinitialiser', '1');
      envoyer('/admin/api-contenu.php', donnees)
        .then(function () {
          fermer(fond, zone);
          bulle('Photo d’origine rétablie');
          setTimeout(function () { window.location.reload(); }, 400);
        })
        .catch(function (err) { erreurDansModale(fond, err.message); });
    });
  }

  /* ---------- Branchement ---------------------------------------------- */

  function brancher(selecteur, action) {
    document.querySelectorAll(selecteur).forEach(function (zone) {
      zone.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        action(zone);
      });
      zone.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' || ev.key === ' ') {
          ev.preventDefault();
          action(zone);
        }
      });
    });
  }

  brancher('.zone-editable', editerTexte);
  brancher('.zone-image', editerImage);

  // En mode édition, les liens ne doivent pas emporter l'utilisateur
  // ailleurs quand il vise une zone modifiable à l'intérieur.
  document.querySelectorAll('a .zone-editable, a .zone-image').forEach(function (z) {
    z.closest('a').addEventListener('click', function (ev) {
      if (ev.target.closest('.zone-editable, .zone-image')) ev.preventDefault();
    });
  });
})();
