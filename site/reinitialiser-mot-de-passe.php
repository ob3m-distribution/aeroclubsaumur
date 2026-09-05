<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';
session_demarrer();

$page = 'reinitialiser-mot-de-passe';
$titre = 'Nouveau mot de passe';
$description = 'Choisissez votre nouveau mot de passe.';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$membre = membre_par_token_reset($token);
$erreur = null;
$ok = false;

if ($membre && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $erreur = 'Votre session a expiré. Merci de réessayer.';
    } else {
        $mdp  = (string) ($_POST['mdp'] ?? '');
        $mdp2 = (string) ($_POST['mdp2'] ?? '');
        if ($mdp !== $mdp2) {
            $erreur = 'Les deux mots de passe ne sont pas identiques.';
        } elseif ($e = mot_de_passe_valide($mdp)) {
            $erreur = $e;
        } else {
            definir_mot_de_passe((int) $membre['id'], $mdp);
            journaliser('mot_de_passe.defini', 'membre#' . $membre['id'], $membre['email']);
            $ok = true;
        }
    }
}

require __DIR__ . '/inc/header.php';
?>
<section class="section section--centree">
  <div class="conteneur">
    <div class="connexion">
      <img class="connexion__logo" src="/assets/img/logo.png?v=2" alt="<?= e(CLUB['nom']) ?>" width="752" height="184">
      <div class="formulaire connexion__carte">
        <p class="surtitre">Espace adhérents</p>
        <h1 class="connexion__titre">Nouveau mot de passe</h1>

        <?php if ($ok): ?>
          <p class="bib-ok" role="status">Votre mot de passe est enregistré. Vous pouvez maintenant vous connecter.</p>
          <a class="bouton" href="<?= e(url('adherents')) ?>">Se connecter</a>
        <?php elseif (!$membre): ?>
          <p class="connexion__erreur" role="alert">Ce lien est invalide ou expiré. Merci de refaire une demande.</p>
          <p class="connexion__aide"><a href="/mot-de-passe-oublie">Nouvelle demande</a></p>
        <?php else: ?>
          <?php if ($erreur !== null): ?><p class="connexion__erreur" role="alert"><?= e($erreur) ?></p><?php endif; ?>
          <p style="margin:0 0 1rem;font-size:.88rem;color:var(--gris-700)">
            8 caractères minimum, avec au moins une majuscule, une minuscule, un chiffre et un caractère spécial.
          </p>
          <form method="post" class="champs" novalidate>
            <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="champ">
              <label for="mdp">Nouveau mot de passe</label>
              <input type="password" id="mdp" name="mdp" autocomplete="new-password" required autofocus>
            </div>
            <div class="champ">
              <label for="mdp2">Confirmer le mot de passe</label>
              <input type="password" id="mdp2" name="mdp2" autocomplete="new-password" required>
            </div>
            <button class="bouton" type="submit">Enregistrer</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
