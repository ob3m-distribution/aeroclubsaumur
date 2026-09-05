<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/auth.php';
exiger_connexion();

$titre = 'Guide du site';
$actif = 'support';
require __DIR__ . '/inc/entete.php';
?>

<style>
/* Guide — aligné sur la charte du back-office (Inter, marine #14294D, or #B08D2C) */
.guide{max-width:920px}
.guide .g-hero{background:var(--noir);color:#fff;border-radius:10px;padding:1.6rem 1.8rem;margin-bottom:1.25rem;position:relative;overflow:hidden}
.guide .g-hero::after{content:"";position:absolute;left:0;right:0;bottom:0;height:4px;background:var(--rouge)}
.guide .g-eyebrow{font-size:.7rem;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:var(--rouge-clair)}
.guide .g-hero h2{color:#fff;font-size:1.5rem;font-weight:600;margin:.35rem 0 .4rem}
.guide .g-hero p{color:#cdd6e6;margin:0;max-width:60ch;font-size:.95rem}

.guide .g-choix{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:.7rem;margin-bottom:1.5rem}
.guide .g-choix a{display:block;background:var(--blanc);border:1px solid var(--gris-200);border-radius:8px;padding:.9rem 1rem;text-decoration:none;color:var(--encre);transition:border-color var(--transition),box-shadow var(--transition)}
.guide .g-choix a:hover{border-color:var(--rouge);box-shadow:0 3px 12px rgba(20,41,77,.08)}
.guide .g-choix .g-em{font-size:1.35rem}
.guide .g-choix h3{margin:.35rem 0 .1rem;font-size:.95rem;font-weight:600}
.guide .g-choix p{margin:0;font-size:.8rem;color:var(--gris-500)}

.guide section{margin-bottom:2rem;scroll-margin-top:1rem}
.guide .g-kicker{display:inline-block;font-size:.68rem;font-weight:700;letter-spacing:.13em;text-transform:uppercase;color:var(--rouge-fonce);margin-bottom:.4rem}
.guide section>h2{font-size:1.3rem;font-weight:600;color:var(--noir);margin:.1rem 0 .4rem}
.guide .g-lead{font-size:.98rem;color:var(--gris-700);max-width:64ch;margin:0 0 1rem}
.guide h3.g-tache{font-size:1.05rem;font-weight:600;color:var(--noir);margin:1.5rem 0 .3rem;display:flex;align-items:center;gap:.5rem}
.guide h3.g-tache .g-em{font-size:1.15rem}

.guide .g-bloc{background:var(--gris-050);border:1px solid var(--gris-200);border-radius:10px;padding:1.1rem}
.guide .g-etapes{display:grid;gap:.6rem;counter-reset:et}
.guide .g-etape{display:flex;gap:.9rem;align-items:flex-start;background:var(--blanc);border:1px solid var(--gris-200);border-radius:8px;padding:.85rem 1rem}
.guide .g-etape::before{counter-increment:et;content:counter(et);flex:0 0 auto;width:30px;height:30px;border-radius:50%;background:var(--noir);color:#fff;font-weight:700;display:grid;place-items:center;font-size:.9rem}
.guide .g-etape h4{margin:0 0 .12rem;font-size:.95rem;font-weight:600}
.guide .g-etape p{margin:0;font-size:.88rem;color:var(--gris-700)}
.guide .g-btn{display:inline-block;font-size:.8rem;font-weight:600;background:var(--noir);color:#fff;border-radius:4px;padding:.08rem .45rem;margin:0 .1rem}
.guide .g-ch{display:inline-block;font-size:.8rem;background:var(--gris-100);border:1px solid var(--gris-200);border-radius:4px;padding:.03rem .4rem;color:var(--encre)}

.guide .g-astuce,.guide .g-attention{display:flex;gap:.7rem;align-items:flex-start;border-radius:8px;padding:.8rem 1rem;margin-top:.9rem;font-size:.88rem;color:var(--gris-700)}
.guide .g-astuce{background:var(--vert-fond);border:1px solid #bfe3cd}
.guide .g-attention{background:var(--ambre-fond);border:1px solid #f0dfa8}
.guide .g-astuce .g-em,.guide .g-attention .g-em{font-size:1.05rem;flex:0 0 auto}
.guide .g-astuce b,.guide .g-attention b{color:var(--encre)}

.guide .g-equipe{background:var(--noir);border-radius:12px;padding:1.5rem}
.guide .g-equipe h2,.guide .g-equipe h3.g-tache{color:#fff}
.guide .g-equipe .g-lead{color:#cdd6e6}
.guide .g-equipe .g-kicker{color:var(--rouge-clair)}
.guide .g-equipe .g-bloc{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.12)}
.guide .g-equipe .g-etape{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.12)}
.guide .g-equipe .g-etape h4{color:#fff}
.guide .g-equipe .g-etape p{color:#c9d3e4}
.guide .g-equipe .g-ch{background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.2);color:#eef3fb}
.guide .g-equipe .g-astuce{background:rgba(27,127,75,.18);border-color:transparent;color:#d6e8dc}
.guide .g-equipe .g-astuce b{color:#fff}

.guide .g-pied{margin-top:1.5rem;padding-top:1.2rem;border-top:1px solid var(--gris-200);display:flex;flex-wrap:wrap;gap:.8rem;align-items:center;justify-content:space-between}
.guide .g-pied span{color:var(--gris-500);font-size:.85rem}
@media print{.bo__menu,.bo__barre,.g-choix,.g-pied{display:none!important}.bo__contenu{padding:0!important}}
</style>

<div class="guide">

  <header class="g-hero">
    <div class="g-eyebrow">Saumur Air Club</div>
    <h2>Mode d’emploi du site, pas à pas</h2>
    <p>Chaque chose que vous pouvez faire, expliquée simplement, étape par étape. Aucune connaissance en informatique nécessaire.</p>
  </header>

  <div class="g-choix">
    <a href="#offrir"><div class="g-em">🎁</div><h3>Offrir un vol</h3><p>Acheter un bon cadeau</p></a>
    <a href="#connexion"><div class="g-em">🔑</div><h3>Je suis membre</h3><p>Me connecter, renouveler</p></a>
    <a href="#inscrire"><div class="g-em">✋</div><h3>Je veux m’inscrire</h3><p>Rejoindre le club</p></a>
    <a href="#bureau"><div class="g-em">🛠️</div><h3>Je suis du bureau</h3><p>Gérer le club</p></a>
  </div>

  <!-- OFFRIR UN VOL -->
  <section id="offrir">
    <div class="g-kicker">Pour tout le monde · aucun compte nécessaire</div>
    <h2>🎁 Offrir un vol (bon cadeau)</h2>
    <p class="g-lead">Vous voulez offrir un baptême de l’air ? Suivez ces étapes sur le site, du début à la fin. Comptez 3 minutes.</p>
    <div class="g-bloc">
      <div class="g-etapes">
        <div class="g-etape"><div><h4>Ouvrez la page des vols</h4><p>Sur le site, cliquez en haut sur le menu <span class="g-ch">Vols découvertes &amp; initiations</span>.</p></div></div>
        <div class="g-etape"><div><h4>Descendez jusqu’au formulaire</h4><p>Faites défiler la page jusqu’à l’encadré <b>« Offrir un bon cadeau »</b>.</p></div></div>
        <div class="g-etape"><div><h4>Choisissez le vol</h4><p>Cliquez sur <b>Vol découverte</b> ou <b>Vol d’initiation</b>. Pour un vol découverte, indiquez le nombre de personnes (1, 2 ou 3). <b>Le prix s’affiche tout seul.</b></p></div></div>
        <div class="g-etape"><div><h4>Dites à qui c’est offert (facultatif)</h4><p>Vous pouvez écrire le prénom et le nom de la personne qui recevra le vol.</p></div></div>
        <div class="g-etape"><div><h4>Remplissez vos coordonnées</h4><p>Votre <span class="g-ch">prénom</span>, <span class="g-ch">nom</span>, <span class="g-ch">e-mail</span> et <span class="g-ch">téléphone</span>. C’est à votre e-mail que le bon sera envoyé.</p></div></div>
        <div class="g-etape"><div><h4>Acceptez et continuez</h4><p>Cochez la petite case des conditions, puis cliquez sur <span class="g-btn">Procéder au paiement</span>.</p></div></div>
        <div class="g-etape"><div><h4>Payez par carte</h4><p>Entrez le <b>numéro de votre carte</b>, la <b>date d’expiration</b> et le <b>code à 3 chiffres</b> (au dos). Cliquez sur <span class="g-btn">Payer</span>. C’est sécurisé.</p></div></div>
        <div class="g-etape"><div><h4>C’est fait !</h4><p>Un e-mail arrive avec votre <b>bon cadeau en PDF</b> et son numéro (ex. <span class="g-ch">WEB-2026-08-27-001</span>). Vous pouvez l’imprimer ou le transférer.</p></div></div>
      </div>
      <div class="g-astuce"><span class="g-em">💡</span><div><b>Pour utiliser le bon :</b> la personne appelle le club avec le numéro du bon pour choisir une date de vol. Gardez bien l’e-mail !</div></div>
    </div>
  </section>

  <!-- ME CONNECTER -->
  <section id="connexion">
    <div class="g-kicker">Réservé aux membres</div>
    <h2>🔑 Me connecter à mon espace</h2>
    <p class="g-lead">Votre espace personnel donne accès aux documents du club et au renouvellement de votre adhésion.</p>

    <h3 class="g-tache"><span class="g-em">1️⃣</span> La toute première fois</h3>
    <div class="g-bloc"><div class="g-etapes">
      <div class="g-etape"><div><h4>Ouvrez l’e-mail d’invitation</h4><p>Le club vous a envoyé un e-mail intitulé <b>« Votre accès à l’espace adhérents »</b>. (Regardez aussi dans les courriers indésirables.)</p></div></div>
      <div class="g-etape"><div><h4>Cliquez sur le lien</h4><p>Dans l’e-mail, cliquez sur le lien pour choisir votre mot de passe.</p></div></div>
      <div class="g-etape"><div><h4>Créez votre mot de passe</h4><p>Au moins <b>8 caractères</b>, avec <b>une majuscule, une minuscule, un chiffre et un caractère spécial</b> (ex. <span class="g-ch">! ? @ #</span>). Écrivez-le deux fois.</p></div></div>
      <div class="g-etape"><div><h4>Enregistrez</h4><p>Cliquez sur <span class="g-btn">Enregistrer</span>. Votre compte est activé, vous pouvez vous connecter.</p></div></div>
    </div></div>

    <h3 class="g-tache"><span class="g-em">2️⃣</span> Me connecter (les fois suivantes)</h3>
    <div class="g-bloc"><div class="g-etapes">
      <div class="g-etape"><div><h4>Cliquez sur « Adhérents »</h4><p>Dans le menu du site, en haut.</p></div></div>
      <div class="g-etape"><div><h4>Entrez vos identifiants</h4><p>Votre <span class="g-ch">e-mail</span> et votre <span class="g-ch">mot de passe</span>.</p></div></div>
      <div class="g-etape"><div><h4>Connectez-vous</h4><p>Cliquez sur <span class="g-btn">Se connecter</span>. Vous voilà dans votre espace.</p></div></div>
    </div>
    <div class="g-astuce"><span class="g-em">🔓</span><div><b>Mot de passe oublié ?</b> Cliquez sur <b>« Mot de passe oublié ? »</b>, entrez votre e-mail : vous recevrez un lien pour en choisir un nouveau.</div></div>
    </div>
  </section>

  <!-- RENOUVELER -->
  <section id="renouveler">
    <div class="g-kicker">Membre · une fois par an</div>
    <h2>🔄 Renouveler mon adhésion</h2>
    <p class="g-lead">Fini le papier : la fiche se remplit en ligne. L’année suivante, tout est déjà pré-rempli — il n’y a qu’à vérifier.</p>
    <div class="g-bloc"><div class="g-etapes">
      <div class="g-etape"><div><h4>Ouvrez le formulaire</h4><p>Une fois connecté, cliquez sur le bouton <span class="g-btn">Réinscription</span> en haut de votre espace.</p></div></div>
      <div class="g-etape"><div><h4>Vérifiez vos informations</h4><p>Vos données de l’an dernier sont <b>déjà remplies</b>. Lisez, et corrigez seulement ce qui a changé.</p></div></div>
      <div class="g-etape"><div><h4>Choisissez votre cotisation</h4><p>Sélectionnez votre option. <b>Le total se calcule tout seul</b> en bas.</p></div></div>
      <div class="g-etape"><div><h4>Joignez vos 2 documents</h4><p>Votre <b>licence pilote</b> et votre <b>certificat médical</b>. Les deux sont <b>obligatoires</b> pour payer.</p></div></div>
      <div class="g-etape"><div><h4>Acceptez le règlement</h4><p>Cochez la case en bas.</p></div></div>
      <div class="g-etape"><div><h4>Validez et payez</h4><p>Cliquez sur <span class="g-btn">Valider et payer</span>, puis payez <b>par carte</b> ou choisissez le <b>virement</b>.</p></div></div>
      <div class="g-etape"><div><h4>C’est envoyé</h4><p>Votre dossier part au bureau, qui le validera. Vous n’avez plus rien à faire.</p></div></div>
    </div>
    <div class="g-astuce"><span class="g-em">💾</span><div><b>Pas le temps de tout finir ?</b> Cliquez sur <b>« Enregistrer le brouillon »</b> : vous reprendrez plus tard là où vous en étiez.</div></div>
    </div>
  </section>

  <!-- DOCUMENTS -->
  <section id="biblio">
    <div class="g-kicker">Membre</div>
    <h2>📚 Consulter les documents du club</h2>
    <p class="g-lead">Manuels, comptes-rendus, formation… tout est rangé par dossiers dans votre espace.</p>
    <div class="g-bloc"><div class="g-etapes">
      <div class="g-etape"><div><h4>Ouvrez votre espace</h4><p>Connectez-vous : vous voyez la <b>bibliothèque</b> avec des dossiers.</p></div></div>
      <div class="g-etape"><div><h4>Ouvrez un dossier</h4><p>Cliquez sur un dossier (ex. <span class="g-ch">Documentation avions</span>) pour voir ce qu’il contient.</p></div></div>
      <div class="g-etape"><div><h4>Ouvrez un document</h4><p>Cliquez sur son nom : il s’ouvre dans un nouvel onglet, prêt à lire ou télécharger.</p></div></div>
    </div>
    <div class="g-astuce"><span class="g-em">✉️</span><div><b>Une question ?</b> Depuis votre espace, vous pouvez aussi <b>envoyer un message au secrétariat</b> (avec une pièce jointe si besoin).</div></div>
    </div>
  </section>

  <!-- M'INSCRIRE -->
  <section id="inscrire">
    <div class="g-kicker">Futur membre · aucun compte</div>
    <h2>✋ Demander à rejoindre le club</h2>
    <p class="g-lead">Vous n’êtes pas encore membre ? Remplissez une demande de pré-inscription. Le bureau vous recontacte ensuite.</p>
    <div class="g-bloc"><div class="g-etapes">
      <div class="g-etape"><div><h4>Ouvrez la page de pré-inscription</h4><p>Le club vous envoie un lien, ou vous allez sur la page <b>« Demande d’inscription »</b>.</p></div></div>
      <div class="g-etape"><div><h4>Remplissez la fiche</h4><p>Votre état civil, vos titres, la cotisation souhaitée. <b>Le total s’affiche.</b></p></div></div>
      <div class="g-etape"><div><h4>Joignez vos documents</h4><p>Votre <b>licence pilote</b> et votre <b>certificat médical</b>.</p></div></div>
      <div class="g-etape"><div><h4>Envoyez la demande</h4><p>Cliquez sur <span class="g-btn">Envoyer ma demande</span>. Vous voyez les modalités de règlement.</p></div></div>
      <div class="g-etape"><div><h4>Le bureau vous contacte</h4><p>Un membre du bureau vous rencontre. Une fois tout validé, votre <b>compte membre est créé</b> et vous recevez votre invitation par e-mail.</p></div></div>
    </div></div>
  </section>

  <!-- LE BUREAU -->
  <section id="bureau">
    <div class="g-equipe">
      <div class="g-kicker">Réservé au bureau</div>
      <h2>🛠️ Gérer le club (back-office)</h2>
      <p class="g-lead">Tout se pilote depuis un navigateur, sans rien installer. Voici comment faire les tâches courantes.</p>

      <h3 class="g-tache"><span class="g-em">🔐</span> Se connecter au back-office</h3>
      <div class="g-bloc"><div class="g-etapes">
        <div class="g-etape"><div><h4>Allez sur la page d’administration</h4><p>Ajoutez <span class="g-ch">/admin</span> à l’adresse du site.</p></div></div>
        <div class="g-etape"><div><h4>Connectez-vous</h4><p>Votre e-mail + mot de passe. Le menu de gauche affiche ce que <b>votre rôle</b> vous autorise.</p></div></div>
      </div></div>

      <h3 class="g-tache"><span class="g-em">🎁</span> Suivre les bons cadeaux</h3>
      <div class="g-bloc"><div class="g-etapes">
        <div class="g-etape"><div><h4>Ouvrez « Bons cadeaux »</h4><p>Vous voyez la liste de tous les bons.</p></div></div>
        <div class="g-etape"><div><h4>Trier ou filtrer</h4><p>Cliquez sur le <b>titre d’une colonne</b> pour trier, ou sur la petite flèche <b>▾</b> pour filtrer.</p></div></div>
        <div class="g-etape"><div><h4>Noter un vol réalisé</h4><p>Sur la ligne du bon, choisissez la <b>date de réalisation</b> et le <b>pilote</b>, puis <span class="g-btn">Enregistrer</span>.</p></div></div>
        <div class="g-etape"><div><h4>Actions sur un bon</h4><p>Cliquez sur le bon pour l’ouvrir : <b>renvoyer par e-mail</b>, <b>prolonger</b> (1 à 6 mois) ou <b>rembourser</b> (total ou partiel, par Stripe).</p></div></div>
      </div></div>

      <h3 class="g-tache"><span class="g-em">👥</span> Suivre les adhérents</h3>
      <div class="g-bloc"><div class="g-etapes">
        <div class="g-etape"><div><h4>Ouvrez « Adhérents »</h4><p>Onglet <b>Réinscription</b> : les dossiers. Onglet <b>Nouvelle inscription</b> : les demandes reçues.</p></div></div>
        <div class="g-etape"><div><h4>Ouvrir un dossier</h4><p>Cliquez sur un nom pour la <b>fiche complète</b> : tout modifier, ajouter une <b>photo</b> et des <b>documents</b>.</p></div></div>
        <div class="g-etape"><div><h4>Valider un dossier</h4><p>Cochez <b>Cotisation reçue</b>, <b>Licence à jour</b>, <b>Médicale à jour</b> puis <span class="g-btn">Enregistrer</span>. Le site retient qui a coché et quand.</p></div></div>
        <div class="g-etape"><div><h4>Transformer une demande en membre</h4><p>Cochez les étapes puis <span class="g-btn">Valider et convertir en membre officiel</span> : le compte est créé automatiquement.</p></div></div>
      </div></div>

      <h3 class="g-tache"><span class="g-em">📁</span> Gérer les documents &amp; les accès</h3>
      <div class="g-bloc"><div class="g-etapes">
        <div class="g-etape"><div><h4>Ranger la bibliothèque</h4><p>Dans <b>« Bibliothèque adhérents »</b> : créez un dossier, déposez un fichier, et <b>glissez-déposez</b> pour changer l’ordre.</p></div></div>
        <div class="g-etape"><div><h4>Choisir qui voit quoi</h4><p>Dans <b>« Accès bibliothèque »</b>, cochez les dossiers autorisés — <b>par rôle</b>, puis <b>membre par membre</b> si besoin.</p></div></div>
      </div></div>

      <h3 class="g-tache"><span class="g-em">✉️</span> Envoyer une newsletter</h3>
      <div class="g-bloc"><div class="g-etapes">
        <div class="g-etape"><div><h4>Ouvrez « Newsletters »</h4></div></div>
        <div class="g-etape"><div><h4>Choisissez qui reçoit</h4><p>Filtrez par <b>rôle</b>, <b>adhésion à jour</b> ou <b>paiement</b>. Le <b>nombre de destinataires</b> s’affiche.</p></div></div>
        <div class="g-etape"><div><h4>Écrivez le message</h4><p>Un sujet, un texte. Astuce : écrivez <span class="g-ch">{prenom}</span> pour personnaliser.</p></div></div>
        <div class="g-etape"><div><h4>Envoyez</h4><p>Cliquez sur <span class="g-btn">Envoyer</span>. L’envoi est ajouté à l’historique.</p></div></div>
      </div></div>

      <div class="g-astuce"><span class="g-em">🆘</span><div><b>Un souci, une question ?</b> La rubrique <b>Support</b> du back-office ouvre un ticket : vous recevez une alerte e-mail et pouvez répondre directement au membre.</div></div>
    </div>
  </section>

  <div class="g-pied">
    <span>Mode d’emploi du site du <strong>Saumur Air Club</strong> · réalisé par OB3M Distribution</span>
    <button class="btn btn--contour" onclick="window.print()">Imprimer / enregistrer en PDF</button>
  </div>

</div>

<script>
document.querySelectorAll('.guide .g-choix a').forEach(function(a){
  a.addEventListener('click',function(e){
    var t=document.querySelector(this.getAttribute('href'));
    if(t){e.preventDefault();t.scrollIntoView({behavior:'smooth'});}
  });
});
</script>

<?php require __DIR__ . '/inc/pied.php'; ?>
