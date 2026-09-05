<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/mail.php';
session_demarrer();

$page = 'mot-de-passe-oublie';
$titre = 'Mot de passe oublié';
$description = 'Réinitialisez votre mot de passe de l’espace adhérents du Saumur Air Club.';

$envoye = false;
$erreur = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $erreur = 'Votre session a expiré. Merci de réessayer.';
    } else {
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreur = 'Adresse e-mail invalide.';
        } else {
            // On cherche le compte, mais on répond toujours pareil (pas de fuite).
            $stmt = db()->prepare('SELECT id, prenom, email FROM membres WHERE email = ? AND actif = 1 LIMIT 1');
            $stmt->execute([$email]);
            $m = $stmt->fetch();
            if ($m) {
                $token = creer_token_reset((int) $m['id'], 24);
                $lien = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'dev.aeroclub-saumur.fr')
                      . '/reinitialiser-mot-de-passe?token=' . $token;
                @email_lien_mot_de_passe($m['email'], (string) $m['prenom'], $lien, false);
                journaliser('mot_de_passe.reset_demande', 'membre#' . $m['id'], $email);
            }
            $envoye = true;
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
        <h1 class="connexion__titre">Mot de passe oublié</h1>

        <?php if ($envoye): ?>
          <p class="bib-ok" role="status">Si un compte est associé à cette adresse, un e-mail
             contenant un lien de réinitialisation vient d’être envoyé. Pensez à vérifier vos
             courriers indésirables.</p>
          <p class="connexion__aide"><a href="<?= e(url('adherents')) ?>">Retour à la connexion</a></p>
        <?php else: ?>
          <?php if ($erreur !== null): ?><p class="connexion__erreur" role="alert"><?= e($erreur) ?></p><?php endif; ?>
          <p style="margin:0 0 1.25rem;font-size:.92rem;color:var(--gris-700)">
            Indiquez votre adresse e-mail : nous vous enverrons un lien pour choisir un nouveau mot de passe.
          </p>
          <form method="post" class="champs" novalidate>
            <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
            <div class="champ">
              <label for="email">Adresse e-mail</label>
              <input type="email" id="email" name="email" autocomplete="username" required autofocus>
            </div>
            <button class="bouton" type="submit">Envoyer le lien</button>
          </form>
          <p class="connexion__aide"><a href="<?= e(url('adherents')) ?>">Retour à la connexion</a></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
