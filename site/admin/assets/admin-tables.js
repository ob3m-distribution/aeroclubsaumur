/* Tri + filtre déroulant (cases à cocher) par colonne pour les tableaux du B.O.
   S'applique à chaque <table> dans .tableau (sauf .tableau--matrice).
   - Clic sur le libellé d'un en-tête : trie la colonne (asc puis desc).
   - Bouton ▾ : ouvre un panneau listant les valeurs de la colonne, à cocher/décocher. */
(function () {
  'use strict';

  function texte(cell) { return (cell ? cell.textContent : '').replace(/\s+/g, ' ').trim(); }

  function clef(v) {
    var d = v.match(/^(\d{2})\/(\d{2})\/(\d{4})/);
    if (d) return { n: new Date(+d[3], +d[2] - 1, +d[1]).getTime() };
    var num = v.replace(/[^\d,.-]/g, '').replace(',', '.');
    if (num !== '' && !isNaN(parseFloat(num)) && /\d/.test(v) && v.length < 14) return { n: parseFloat(num) };
    return { s: v.toLowerCase() };
  }
  function comparer(a, b) {
    var ka = clef(a), kb = clef(b);
    if ('n' in ka && 'n' in kb) return ka.n - kb.n;
    return (ka.s || String(ka.n)).localeCompare(kb.s || String(kb.n), 'fr');
  }

  function enhance(table) {
    var thead = table.tHead, tbody = table.tBodies[0];
    if (!thead || !tbody || !thead.rows.length) return;
    var entetes = thead.rows[0].cells, n = entetes.length;
    var filtres = new Array(n).fill(null);   // null = tout ; sinon Set des valeurs autorisées

    function appliquer() {
      [].forEach.call(tbody.rows, function (tr) {
        var ok = true;
        for (var i = 0; i < n && ok; i++) {
          if (filtres[i] && !filtres[i].has(texte(tr.cells[i]))) ok = false;
        }
        tr.style.display = ok ? '' : 'none';
      });
    }

    function trier(col, th) {
      var asc = th.getAttribute('data-tri') !== 'asc';
      [].forEach.call(entetes, function (h) { h.removeAttribute('data-tri'); });
      th.setAttribute('data-tri', asc ? 'asc' : 'desc');
      var rows = [].slice.call(tbody.rows);
      rows.sort(function (r1, r2) {
        var d = comparer(texte(r1.cells[col]), texte(r2.cells[col]));
        return asc ? d : -d;
      });
      rows.forEach(function (r) { tbody.appendChild(r); });
    }

    for (var c = 0; c < n; c++) {
      (function (col) {
        var th = entetes[col];
        if (texte(th) === '') return;                 // colonne d'actions : ni tri ni filtre
        th.classList.add('tbl-triable');
        th.style.position = 'relative';

        var libelle = th.textContent;
        // On enveloppe le libellé pour que seul son clic déclenche le tri.
        th.textContent = '';
        var span = document.createElement('span');
        span.className = 'tbl-lib';
        span.textContent = libelle;
        span.addEventListener('click', function () { trier(col, th); });
        th.appendChild(span);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'tbl-filtre-btn';
        btn.textContent = '▾';
        btn.setAttribute('aria-label', 'Filtrer ' + libelle);

        var pop = document.createElement('div');
        pop.className = 'tbl-filtre-pop';
        pop.hidden = true;

        th.appendChild(btn);
        th.appendChild(pop);

        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          if (pop.hidden) { construire(); fermerAutres(pop); pop.hidden = false; }
          else pop.hidden = true;
        });
        pop.addEventListener('click', function (e) { e.stopPropagation(); });

        function construire() {
          pop.textContent = '';
          // Valeurs uniques de la colonne.
          var vals = [];
          var vus = {};
          [].forEach.call(tbody.rows, function (tr) {
            var v = texte(tr.cells[col]);
            if (!(v in vus)) { vus[v] = 1; vals.push(v); }
          });
          vals.sort(function (a, b) { return comparer(a, b); });
          var f = filtres[col];

          var tete = document.createElement('label');
          tete.className = 'tbl-filtre-tout';
          var cbAll = document.createElement('input');
          cbAll.type = 'checkbox';
          cbAll.checked = !f;
          tete.appendChild(cbAll);
          tete.appendChild(document.createTextNode(' Tout'));
          pop.appendChild(tete);

          var liste = document.createElement('div');
          liste.className = 'tbl-filtre-liste';
          var boxes = [];
          vals.forEach(function (v) {
            var lab = document.createElement('label');
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = !f || f.has(v);
            cb._val = v;
            lab.appendChild(cb);
            lab.appendChild(document.createTextNode(' ' + (v === '' ? '(vide)' : v)));
            liste.appendChild(lab);
            boxes.push(cb);
          });
          pop.appendChild(liste);

          function maj() {
            var coches = boxes.filter(function (b) { return b.checked; }).map(function (b) { return b._val; });
            filtres[col] = (coches.length === boxes.length) ? null : new Set(coches);
            cbAll.checked = (coches.length === boxes.length);
            btn.classList.toggle('actif', !!filtres[col]);
            appliquer();
          }
          cbAll.addEventListener('change', function () {
            boxes.forEach(function (b) { b.checked = cbAll.checked; });
            maj();
          });
          boxes.forEach(function (b) { b.addEventListener('change', maj); });
        }
      })(c);
    }

    function fermerAutres(sauf) {
      thead.querySelectorAll('.tbl-filtre-pop').forEach(function (p) { if (p !== sauf) p.hidden = true; });
    }
    document.addEventListener('click', function () {
      thead.querySelectorAll('.tbl-filtre-pop').forEach(function (p) { p.hidden = true; });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.tableau:not(.tableau--matrice) > table').forEach(enhance);
  });
})();
