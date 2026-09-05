/* Back-office — menu mobile, confirmations, anti-double-envoi. */
(function () {
  'use strict';

  /* Menu latéral sur petit écran */
  var burger = document.querySelector('.bo__burger');
  var menu = document.getElementById('menu-admin');
  if (burger && menu) {
    burger.addEventListener('click', function () {
      var ouvert = menu.classList.toggle('est-ouvert');
      burger.setAttribute('aria-expanded', String(ouvert));
      burger.setAttribute('aria-label', ouvert ? 'Fermer le menu' : 'Ouvrir le menu');
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && menu.classList.contains('est-ouvert')) {
        menu.classList.remove('est-ouvert');
        burger.setAttribute('aria-expanded', 'false');
        burger.focus();
      }
    });
  }

  /* Confirmation avant une action irréversible */
  document.querySelectorAll('[data-confirmer]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      if (!window.confirm(el.getAttribute('data-confirmer'))) {
        ev.preventDefault();
      }
    });
  });

  /* Un formulaire envoyé ne doit pas pouvoir l'être deux fois */
  document.querySelectorAll('form[data-unique]').forEach(function (form) {
    form.addEventListener('submit', function () {
      var b = form.querySelector('button[type="submit"]');
      if (!b) return;
      b.setAttribute('aria-disabled', 'true');
      b.textContent = 'Enregistrement…';
    });
  });

  /* Copier un lien (flux iCal de l'agenda) */
  document.querySelectorAll('[data-copier]').forEach(function (b) {
    b.addEventListener('click', function () {
      var cible = document.querySelector(b.getAttribute('data-copier'));
      if (!cible) return;
      var texte = cible.value || cible.textContent;
      navigator.clipboard.writeText(texte).then(function () {
        var avant = b.textContent;
        b.textContent = 'Copié ✓';
        setTimeout(function () { b.textContent = avant; }, 1800);
      });
    });
  });
})();
