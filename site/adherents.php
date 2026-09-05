<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/mail.php';
require_once __DIR__ . '/inc/biblio-adherents.php';
session_demarrer();

$page = 'adherents';
$description = 'Espace adhérents du Saumur Air Club — bibliothèque documentaire réservée aux membres.';

/* ---- Déconnexion --------------------------------------------------- */
if (($_GET['deconnexion'] ?? '') === '1') {
    deconnecter();
    header('Location: ' . url('adherents'), true, 303);
    exit;
}

$membre = membre_connecte();
$erreur = null;
$email  = '';
$partageOk = false;
$partageErreur = null;

/* ---- Connexion (avant toute sortie) -------------------------------- */
if (!$membre && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['form'] ?? '') === 'connexion') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $mdp   = (string) ($_POST['mot_de_passe'] ?? '');
    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $erreur = 'Votre session a expiré. Merci de réessayer.';
    } elseif ($email === '' || $mdp === '') {
        $erreur = 'Merci de renseigner votre e-mail et votre mot de passe.';
    } else {
        [$m, $erreur] = tenter_connexion($email, $mdp);
        if ($m) { header('Location: ' . url('adherents'), true, 303); exit; }
    }
}

/* ---- Formulaire de partage (membre connecté) ----------------------- */
if ($membre && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['form'] ?? '') === 'partage') {
    if (!jeton_csrf_valide($_POST['csrf'] ?? null)) {
        $partageErreur = 'Votre session a expiré. Merci de renvoyer votre message.';
    } else {
        $message = trim((string) ($_POST['message'] ?? ''));
        $pieces  = [];
        $tropGros = false;
        $mauvaisType = false;
        $totalOctets = 0;
        $okExt = ['jpg','jpeg','png','gif','webp','heic','pdf'];

        if (!empty($_FILES['pj']) && is_array($_FILES['pj']['name'])) {
            $nb = count($_FILES['pj']['name']);
            for ($i = 0; $i < $nb && $i < 6; $i++) {
                if (($_FILES['pj']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                $tmp  = $_FILES['pj']['tmp_name'][$i];
                $nom  = (string) $_FILES['pj']['name'][$i];
                $ext  = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
                if (!in_array($ext, $okExt, true)) { $mauvaisType = true; continue; }
                if (!is_uploaded_file($tmp)) continue;
                $taille = (int) filesize($tmp);
                $totalOctets += $taille;
                if ($totalOctets > 12 * 1024 * 1024) { $tropGros = true; break; }
                $pieces[] = [
                    'nom'  => $nom,
                    'type' => ($ext === 'pdf') ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext),
                    'data' => file_get_contents($tmp),
                ];
            }
        }

        if ($tropGros) {
            $partageErreur = 'L’ensemble des pièces jointes dépasse 12 Mo. Merci d’en envoyer moins à la fois.';
        } elseif ($mauvaisType) {
            $partageErreur = 'Formats acceptés : images (JPG, PNG…) et PDF uniquement.';
        } elseif ($message === '' && !$pieces) {
            $partageErreur = 'Écrivez un message ou joignez au moins un fichier.';
        } else {
            $auteur = trim($membre['prenom'] . ' ' . $membre['nom']);
            $sujet  = 'Espace adhérents — message de ' . $auteur;
            $corps  = "Message d'un adhérent depuis l'espace membres.\n\n"
                    . "De : {$auteur} <{$membre['email']}>\n"
                    . 'Le : ' . date('d/m/Y à H:i') . "\n"
                    . str_repeat('—', 32) . "\n\n"
                    . ($message !== '' ? $message : '(aucun message, voir pièces jointes)') . "\n";
            $envoye = $pieces
                ? envoyer_email_pj(EMAIL_CLUB, $sujet, $corps, $pieces, $membre['email'])
                : envoyer_email(EMAIL_CLUB, $sujet, $corps, $membre['email']);
            if ($envoye) {
                $partageOk = true;
            } else {
                $partageErreur = 'L’envoi a échoué. Réessayez, ou écrivez directement au secrétariat.';
            }
        }
    }
}

/* Droits B.O. + sections visibles selon les dossiers autorisés du membre. */
$aAccesBO = $membre !== null && membre_voit_tout($membre);
$sectionsVisibles = biblio_arbre();
if ($membre && !$aAccesBO) {
    $autorises = dossiers_effectifs($membre);
    if ($autorises !== null) {
        $sectionsVisibles = array_values(array_filter(
            $sectionsVisibles,
            fn($s) => in_array((string) $s['id'], $autorises, true)
        ));
    }
}
$totalDocs = 0;
foreach ($sectionsVisibles as $sec) $totalDocs += biblio_compter($sec);

/** Rendu des sections de premier niveau (accordéons). */
function rendu_biblio(array $noeuds): string {
    $html = '';
    foreach ($noeuds as $n) {
        $c = biblio_compter($n);
        $html .= '<section class="bib-sec"><button type="button" class="bib-sec__tete" aria-expanded="false">'
              . '<span class="bib-sec__t"><b>' . e($n['nom']) . '</b><span>' . $c . ' document' . ($c > 1 ? 's' : '') . '</span></span>'
              . '<span class="bib-badge">' . $c . '</span>'
              . '<svg class="bib-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>'
              . '</button><div class="bib-sec__corps" hidden>';
        $html .= rendu_biblio_contenu($n);
        if (!biblio_compter($n)) $html .= '<p class="bib-grp__t">Dossier vide pour le moment.</p>';
        $html .= '</div></section>';
    }
    return $html;
}
/** Contenu d'une section (docs + sous-sections en groupes). */
function rendu_biblio_contenu(array $n): string {
    $html = '';
    foreach ($n['docs'] as $d) {
        $badge = strtoupper((string) $d['type']);
        $html .= '<a class="bib-doc" data-n="' . e(mb_strtolower($d['nom'])) . '" target="_blank" rel="noopener" href="/doc-adherent.php?doc=' . (int) $d['id'] . '">'
              . '<span class="bib-doc__pdf">' . e($badge) . '</span>'
              . '<span class="bib-doc__n">' . e($d['nom']) . '</span>'
              . '<svg class="bib-doc__go" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>'
              . '</a>';
    }
    foreach ($n['sous'] as $s) {
        $classe = $s['nom'] === 'Documentation avions' ? ' bib-grp--av' : '';
        $html .= '<div class="bib-grp' . $classe . '"><p class="bib-grp__t">' . e($s['nom']) . '</p>'
              . rendu_biblio_contenu($s) . '</div>';
    }
    return $html;
}

require __DIR__ . '/inc/header.php';
?>

<?php if (!$membre): ?>
<!-- ============ CONNEXION ============ -->
<section class="section section--centree">
  <div class="conteneur">
    <div class="connexion">
      <img class="connexion__logo" src="/assets/img/logo.png?v=2" alt="<?= e(CLUB['nom']) ?>" width="752" height="184">
      <div class="formulaire connexion__carte">
        <p class="surtitre">Espace adhérents</p>
        <h1 class="connexion__titre">Connexion</h1>
        <?php if ($erreur !== null): ?><p class="connexion__erreur" role="alert"><?= e($erreur) ?></p><?php endif; ?>
        <form method="post" class="champs" novalidate>
          <input type="hidden" name="form" value="connexion">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <div class="champ">
            <label for="email">Adresse e-mail</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" autocapitalize="none" required autofocus>
          </div>
          <div class="champ">
            <label for="mot_de_passe">Mot de passe</label>
            <input type="password" id="mot_de_passe" name="mot_de_passe" autocomplete="current-password" required>
          </div>
          <button class="bouton" type="submit">Se connecter</button>
        </form>
        <p class="connexion__aide">
          <a href="/mot-de-passe-oublie">Mot de passe oublié ?</a><br>
          Pas encore de compte ? <a href="<?= e(url('contact')) ?>">Contactez le secrétariat</a>.
        </p>
      </div>
    </div>
  </div>
</section>

<?php else: ?>
<!-- ============ BIBLIOTHÈQUE (connecté) ============ -->
<section class="bib-hero">
  <div class="conteneur bib-hero__in">
    <div>
      <p class="surtitre">Espace adhérents · <?= e($membre['prenom']) ?></p>
      <h1>Bibliothèque documentaire</h1>
    </div>
    <div class="bib-hero__actions">
      <a class="bouton" href="/reinscription">Réinscription <?= COTISATION_ANNEE ?></a>
      <?php if ($aAccesBO): ?><a class="bouton bouton--fantome" href="/admin/">Back-office</a><?php endif; ?>
      <a class="bouton bouton--fantome" href="<?= e(url('adherents')) ?>?deconnexion=1">Se déconnecter</a>
    </div>
  </div>
</section>

<section class="section">
  <div class="conteneur bib-wrap">
    <p class="bib-intro">Manuels avions, formation, sécurité des vols, DTO, flash méca et inscriptions. Cliquez un document pour l’ouvrir dans un nouvel onglet.</p>

    <div class="bib-barre">
      <div class="bib-rech">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input id="bib-q" type="search" placeholder="Rechercher un document…" autocomplete="off" aria-label="Rechercher un document">
      </div>
      <div class="bib-compte"><b id="bib-cpt"><?= $totalDocs ?></b> <span id="bib-lbl">documents</span></div>
    </div>

    <?php if (!$sectionsVisibles): ?>
      <p class="bib-aucun">Aucun dossier ne vous est ouvert pour le moment. Contactez le secrétariat pour obtenir un accès.</p>
    <?php else: ?>
    <div class="bib-sections" id="bib-sections">
      <?= rendu_biblio($sectionsVisibles) ?>
    </div>
    <p class="bib-vide" id="bib-vide" hidden>Aucun document ne correspond à votre recherche.</p>
    <?php endif; ?>

    <!-- Formulaire de partage -->
    <form class="bib-partage" method="post" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="form" value="partage">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <h2>Partager avec le club</h2>
      <p class="bib-partage__sub">Un commentaire, une photo de vol, un document à faire remonter au bureau ? Déposez-le ici, le secrétariat le recevra.</p>

      <?php if ($partageOk): ?>
        <p class="bib-ok" role="status">Merci ! Votre message a bien été transmis au club.</p>
      <?php elseif ($partageErreur !== null): ?>
        <p class="connexion__erreur" role="alert"><?= e($partageErreur) ?></p>
      <?php endif; ?>

      <div class="champ">
        <label for="bib-msg">Message</label>
        <textarea id="bib-msg" name="message" placeholder="Votre commentaire, une info à partager…"></textarea>
      </div>
      <div class="champ">
        <label for="bib-pj">Pièces jointes (photos, PDF — 12 Mo max)</label>
        <label class="bib-depot" for="bib-pj">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="m6 10 6-6 6 6"/><path d="M4 20h16"/></svg>
          <b>Glissez vos fichiers ici</b>
          <small id="bib-pjlbl">ou cliquez pour parcourir</small>
        </label>
        <input id="bib-pj" type="file" name="pj[]" multiple accept="image/*,.pdf" class="visuellement-cache">
      </div>
      <div class="bib-envoi"><button class="bouton" type="submit">Envoyer au club</button></div>
    </form>
  </div>
</section>

<script>
(function(){
  var q=document.getElementById('bib-q'),cpt=document.getElementById('bib-cpt'),
      lbl=document.getElementById('bib-lbl'),vide=document.getElementById('bib-vide'),
      total=<?= $totalDocs ?>;
  document.querySelectorAll('.bib-sec__tete').forEach(function(t){
    t.addEventListener('click',function(){
      var body=t.nextElementSibling,open=t.getAttribute('aria-expanded')==='true';
      t.setAttribute('aria-expanded',open?'false':'true');body.hidden=open;
      t.closest('.bib-sec').classList.toggle('ouvert',!open);
    });
  });
  function esc(s){return s.replace(/[&<>]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;'}[c];});}
  q.addEventListener('input',function(){
    var term=q.value.trim().toLowerCase(),shown=0,any=false,rx=null;
    if(term){try{rx=new RegExp('('+term.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','ig');}catch(e){}}
    document.querySelectorAll('.bib-sec').forEach(function(sec){
      var has=false;
      sec.querySelectorAll('.bib-doc').forEach(function(a){
        var hit=!term||a.getAttribute('data-n').indexOf(term)>=0;
        a.style.display=hit?'flex':'none';
        var nm=a.querySelector('.bib-doc__n'),txt=nm.textContent;
        nm.innerHTML=rx&&hit?esc(txt).replace(rx,'<mark>$1</mark>'):esc(txt);
        if(hit){shown++;has=true;}
      });
      sec.querySelectorAll('.bib-grp').forEach(function(g){
        var vis=[].slice.call(g.querySelectorAll('.bib-doc')).some(function(d){return d.style.display!=='none';});
        g.style.display=vis?'block':'none';
      });
      var body=sec.querySelector('.bib-sec__corps'),tete=sec.querySelector('.bib-sec__tete');
      if(term){sec.style.display=has?'block':'none';body.hidden=!has;
        tete.setAttribute('aria-expanded',has?'true':'false');sec.classList.toggle('ouvert',has);}
      else{sec.style.display='block';body.hidden=true;
        tete.setAttribute('aria-expanded','false');sec.classList.remove('ouvert');}
      if(has)any=true;
    });
    cpt.textContent=term?shown:total;
    lbl.textContent=term?(shown>1?'résultats':'résultat'):'documents';
    vide.hidden=!(term&&!any);
  });
  var pj=document.getElementById('bib-pj'),pjl=document.getElementById('bib-pjlbl');
  pj.addEventListener('change',function(){var n=pj.files.length;
    pjl.textContent=n?n+' fichier'+(n>1?'s':'')+' sélectionné'+(n>1?'s':''):'ou cliquez pour parcourir';});
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
