/* En-tête translucide dès que la page défile. */
(function () {
  'use strict';

  var entete = document.querySelector('.entete');
  if (!entete) return;

  var SEUIL = 8;        // quelques pixels suffisent : dès qu'on quitte le haut
  var defile = null;    // dernier état appliqué, pour n'écrire dans le DOM qu'au changement

  function majuster() {
    var doitDefiler = (window.scrollY || document.documentElement.scrollTop) > SEUIL;
    if (doitDefiler === defile) return;
    defile = doitDefiler;
    entete.classList.toggle('entete--defile', doitDefiler);
  }

  // Toggle direct, sans requestAnimationFrame : rAF est suspendu dans un onglet
  // en arrière-plan, ce qui figerait l'en-tête. Le garde `defile` suffit à
  // éviter les écritures DOM inutiles.
  window.addEventListener('scroll', majuster, { passive: true });
  window.addEventListener('resize', majuster, { passive: true });

  majuster();   // état correct si la page est rechargée en cours de défilement
})();

/* Formulaire : anti-double-envoi + focus sur le récapitulatif d'erreurs. */
(function () {
  'use strict';

  // Si le serveur a renvoyé des erreurs, on y amène l'utilisateur.
  var recap = document.getElementById('recap-erreurs');
  if (recap) {
    recap.focus();
    recap.scrollIntoView({ block: 'center' });
  }

  var form = document.getElementById('formulaire');
  if (!form) return;

  form.addEventListener('submit', function () {
    var bouton = form.querySelector('[data-envoi]');
    if (!bouton) return;
    // aria-disabled plutôt que disabled : un bouton désactivé n'est pas
    // envoyé avec le formulaire, et le nôtre n'a pas de name — mais le
    // lecteur d'écran doit quand même annoncer l'état.
    bouton.setAttribute('aria-disabled', 'true');
    bouton.textContent = 'Envoi en cours…';
  });
})();

/* Menu en tiroir — burger + voile, sur tous les écrans. */
(function () {
  'use strict';

  var burger = document.querySelector('.burger');
  var nav = document.getElementById('menu-principal');
  var voile = document.getElementById('nav-voile');
  if (!burger || !nav) return;

  function etat(ouvert) {
    nav.classList.toggle('est-ouvert', ouvert);
    if (voile) voile.classList.toggle('est-ouvert', ouvert);
    burger.setAttribute('aria-expanded', String(ouvert));
    burger.setAttribute('aria-label', ouvert ? 'Fermer le menu' : 'Ouvrir le menu');
    // On empêche la page de défiler derrière le tiroir ouvert.
    document.body.style.overflow = ouvert ? 'hidden' : '';
  }

  burger.addEventListener('click', function () {
    etat(!nav.classList.contains('est-ouvert'));
  });

  if (voile) voile.addEventListener('click', function () { etat(false); burger.focus(); });

  // Un clic sur une entrée referme le tiroir (navigation interne).
  nav.addEventListener('click', function (ev) {
    if (ev.target.closest('a')) etat(false);
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && nav.classList.contains('est-ouvert')) {
      etat(false);
      burger.focus();
    }
  });
})();
