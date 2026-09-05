<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/auth.php';
session_demarrer();

// Déjà connecté : inutile de repasser par ici.
if (est_connecte()) {
    header('Location: /admin/', true, 302);
    exit;
}

$erreur = null;
$email  = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $mdp   = (string) ($_POST['mot_de_passe'] ?? '');

    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $erreur = 'Votre session a expiré. Merci de réessayer.';
    } elseif ($email === '' || $mdp === '') {
        $erreur = 'Merci de renseigner votre email et votre mot de passe.';
    } else {
        [$membre, $erreur] = tenter_connexion($email, $mdp);
        if ($membre) {
            $vers = $_SESSION['apres_connexion'] ?? '/admin/';
            unset($_SESSION['apres_connexion']);
            // On ne redirige que vers une URL interne : sinon c'est une
            // porte ouverte à la redirection malveillante.
            if (!preg_match('#^/admin/#', (string) $vers)) {
                $vers = '/admin/';
            }
            header('Location: ' . $vers, true, 302);
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion — Back-office Saumur Air Club</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<link rel="stylesheet" href="/admin/assets/admin.css?v=1">
</head>
<body>

<div class="connexion">
  <div class="connexion__boite">

    <div class="connexion__marque">
      <span class="marque"><span class="marque__saumur">Saumur</span> <span class="marque__club">Air Club</span></span>
      <p style="margin:.35rem 0 0;font-size:.75rem;letter-spacing:.1em;text-transform:uppercase;color:#83888A">
        Back-office
      </p>
    </div>

    <?php if ($erreur !== null): ?>
      <div class="message message--erreur" role="alert"><?= e($erreur) ?></div>
    <?php endif; ?>

    <form method="post" data-unique>
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

      <div class="champs">
        <div class="champ">
          <label for="email">Adresse email</label>
          <input type="email" id="email" name="email" value="<?= e($email) ?>"
                 autocomplete="username" required autofocus>
        </div>
        <div class="champ">
          <label for="mot_de_passe">Mot de passe</label>
          <input type="password" id="mot_de_passe" name="mot_de_passe"
                 autocomplete="current-password" required>
        </div>
      </div>

      <button type="submit" class="btn">Se connecter</button>
    </form>

    <p style="margin:1.25rem 0 0;font-size:.8125rem;text-align:center">
      <a href="/mot-de-passe-oublie">Mot de passe oublié ?</a>
    </p>

  </div>
</div>

<script src="/admin/assets/admin.js?v=1" defer></script>
</body>
</html>
