<?php
declare(strict_types=1);
http_response_code(404);
$page = '';
require __DIR__ . '/inc/header.php';
?>

<section class="section section--centree">
  <div class="conteneur">
    <p class="surtitre">Erreur 404</p>
    <h1>Cette page n’existe pas</h1>
    <p style="max-width:48ch;margin-inline:auto">
      Le lien est peut-être erroné, ou la page a été déplacée.
    </p>
    <div style="display:flex;gap:.85rem;justify-content:center;flex-wrap:wrap;margin-top:2rem">
      <a class="bouton" href="/">Retour à l’accueil</a>
      <a class="bouton bouton--sombre" href="<?= e(url('contact')) ?>">Nous contacter</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
