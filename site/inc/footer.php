</main>

<footer class="pied">
  <div class="pied__inner">

    <div class="pied__bloc pied__bloc--marque">
      <p class="marque"><span class="marque__saumur">Saumur</span> <span class="marque__club">Air Club</span></p>
      <p class="pied__baseline">Le ciel n’est pas la limite, c’est notre terrain de jeu.</p>
    </div>

    <?php
    /* Chevron réutilisé pour les accordéons du footer (mobile). */
    $piedChevron = '<svg class="pied-acc__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
      . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
    ?>

    <details class="pied__bloc pied-acc" open>
      <summary><h2>Nous trouver</h2><?= $piedChevron ?></summary>
      <div class="pied-acc__corps">
        <address>
          <?= e(CLUB['adresse_1']) ?><br>
          <?= e(str_replace('SAUMUR', 'Saumur', CLUB['adresse_2'])) ?>
        </address>
        <p class="pied__coords">
          <?= e(CLUB['lat_dms']) ?> · <?= e(CLUB['lon_dms']) ?>
        </p>
      </div>
    </details>

    <details class="pied__bloc pied-acc" open>
      <summary><h2>Nous joindre</h2><?= $piedChevron ?></summary>
      <div class="pied-acc__corps">
        <p>
          <a href="tel:<?= e(tel_lien(CLUB['tel_mobile'])) ?>"><?= e(CLUB['tel_mobile']) ?></a><br>
          <a href="tel:<?= e(tel_lien(CLUB['tel_fixe'])) ?>"><?= e(CLUB['tel_fixe']) ?></a><br>
          <a href="mailto:<?= e(CLUB['email_vols']) ?>"><?= e(CLUB['email_vols']) ?></a>
        </p>
      </div>
    </details>

    <details class="pied__bloc pied-acc" open>
      <summary><h2>Le club</h2><?= $piedChevron ?></summary>
      <div class="pied-acc__corps">
        <ul class="pied__liens">
          <li><a href="<?= e(url('vols-decouvertes')) ?>">Offrir un vol</a></li>
          <li><a href="<?= e(url('tarifs-inscriptions')) ?>">Nous rejoindre</a></li>
          <li><a href="<?= e(url('contact')) ?>">Contact</a></li>
        </ul>
      </div>
    </details>

  </div>

  <div class="pied__bas">
    <p>© <?= date('Y') ?> <?= e(CLUB['nom']) ?></p>
    <p class="pied__legal">
      <a href="/mentions-legales">Mentions légales</a> ·
      <a href="/cgv">CGV</a> ·
      <a href="/confidentialite">Confidentialité</a>
    </p>
  </div>
</footer>

<?php
/* Barre d'édition : ne s'affiche qu'au super administrateur connecté. */
require __DIR__ . '/barre-edition.php';
?>

<script>
/* Footer : accordéons sur mobile (≤560px), tout déplié sur desktop. */
(function(){
  var mq = window.matchMedia('(min-width:1280px)');
  var accs = [].slice.call(document.querySelectorAll('.pied-acc'));
  function sync(){ accs.forEach(function(d){ d.open = mq.matches; }); }
  sync();
  if (mq.addEventListener) mq.addEventListener('change', sync); else mq.addListener(sync);
})();
</script>
<script src="/assets/js/site.js?v=5" defer></script>
</body>
</html>
