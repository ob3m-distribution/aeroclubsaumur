<?php
/* Contenu initial de la Documentation site (amorçage unique, puis éditable
   dans le B.O.). Mini-format : #/##/### titres, - listes, **gras**, `code`. */
declare(strict_types=1);

return [
[
 'cle' => 'presentation',
 'titre' => 'Présentation & pile technique',
 'contenu' =>
"# Documentation du site du Saumur Air Club\n" .
"Historique vivant de tout ce qui a été construit. Utile le jour où le site change de version ou de technologie.\n\n" .
"## Pile technique\n" .
"- **Langage** : PHP (sans framework), MySQL.\n" .
"- **Hébergement** : IONOS mutualisé (pas d'accès root/shell). Déploiement par **SFTP** (`deploy.py`).\n" .
"- **TLS** : géré par IONOS. En-têtes de sécurité posés côté PHP (voir section Sécurité).\n" .
"- **Réalisation** : OB3M Distribution.\n\n" .
"## Environnements\n" .
"- **Dev** : `dev.aeroclub-saumur.fr` (protégé par mot de passe HTTP le temps du chantier).\n" .
"- **Prod** : à activer (domaine public, retrait du mot de passe, Stripe en clés réelles).",
],
[
 'cle' => 'site-public',
 'titre' => 'Site public',
 'contenu' =>
"## Pages\n" .
"- Accueil, aérodrome (avec widget météo Holfuy), avions, vols découvertes & initiations, FAQ, contact.\n" .
"- Pages légales : mentions légales, CGV, confidentialité.\n" .
"- **Contenus éditables** depuis le back-office (mode édition en ligne).\n" .
"- URLs propres (sans `.php`), images en WebP automatique, compression et cache réglés.",
],
[
 'cle' => 'bo-adherents',
 'titre' => 'Espace adhérents & back-office',
 'contenu' =>
"## Espace adhérents\n" .
"- Connexion par e-mail + mot de passe, première connexion par lien d'invitation, mot de passe oublié.\n" .
"- Accès à la bibliothèque de documents selon les droits.\n\n" .
"## Back-office\n" .
"- Rôles et droits (super-admin, secrétariat, instructeur, lecture, adhérent).\n" .
"- Sections : Tableau de bord, Bons cadeaux, Contenus, Adhérents, Membres & accès, Accès bibliothèque, Bibliothèque, Newsletters, Support, Documentation.",
],
[
 'cle' => 'bons-cadeaux',
 'titre' => 'Bons cadeaux & paiement Stripe',
 'contenu' =>
"## Bons cadeaux\n" .
"- Achat en ligne d'un bon (vol découverte 1/2/3 pers., initiation), **paiement carte Stripe**.\n" .
"- Bon envoyé par e-mail en **PDF au visuel officiel**, numéro `WEB-AAAA-MM-JJ-NNN`.\n" .
"- B.O. : suivi, note du vol réalisé, **renvoi e-mail**, **prolongation** (1 à 6 mois), **remboursement** total/partiel (Stripe).\n\n" .
"## Stripe\n" .
"- Mode **TEST** en dev. Clés et secret webhook dans `config-local.php` (hors dépôt).\n" .
"- Webhook `webhook/stripe.php` (signature HMAC vérifiée), routé par metadata.\n" .
"- **À la prod** : basculer en clés **live** et recréer le webhook sur l'URL publique.",
],
[
 'cle' => 'adhesions',
 'titre' => 'Adhésions : pré-inscription & réinscription',
 'contenu' =>
"## Pré-inscription (futur membre)\n" .
"- Formulaire public, pièces jointes (licence, certificat médical), suivi B.O., conversion en membre.\n\n" .
"## Réinscription (membre)\n" .
"- Fiche pré-remplie d'une année sur l'autre, calcul auto de la cotisation, paiement carte ou virement.\n" .
"- Validation B.O. tracée (qui a coché quoi, quand).",
],
[
 'cle' => 'biblio-newsletters',
 'titre' => 'Bibliothèque & newsletters',
 'contenu' =>
"## Bibliothèque adhérents\n" .
"- Dossiers et documents, glisser-déposer pour l'ordre, **droits d'accès par rôle puis par membre**.\n\n" .
"## Newsletters\n" .
"- Ciblage (rôle, adhésion à jour, paiement), personnalisation `{prenom}`, historique des envois.",
],
[
 'cle' => 'support',
 'titre' => 'Support (tickets)',
 'contenu' =>
"## Système de tickets\n" .
"- Formulaire (sujet, priorité, message, **pièces jointes**), alerte e-mail à OB3M.\n" .
"- **Fil de discussion bidirectionnel** : réponse depuis le B.O. → e-mail au membre ; le membre répond → e-mail au bureau.\n" .
"- Statuts ouvert / en cours / résolu, historique par membre.\n" .
"- Remplace l'ancien contact (téléphone/e-mail retirés).",
],
[
 'cle' => 'guide',
 'titre' => 'Guide utilisateur',
 'contenu' =>
"## Guide pas-à-pas\n" .
"- Mode d'emploi complet (offrir un vol, se connecter, renouveler, documents, pré-inscription, back-office).\n" .
"- Accessible en ligne (`/admin/guide.php`, charte du B.O.) et en **PDF téléchargeable**.",
],
[
 'cle' => 'annuaire',
 'titre' => 'Annuaire des membres (import 05/09/2026)',
 'contenu' =>
"## Import de la liste officielle\n" .
"- **84 membres** importés depuis le fichier club : fiches (nom, naissance, tél, e-mail, n° licencié) + **comptes inactifs** (sans e-mail d'invitation).\n" .
"- Section **Profil** dans chaque fiche (qualité/fonction, ex. CSAETB, instructeur, membre du CA).\n" .
"- Superadmins existants rattachés sans doublon. Données de test supprimées.",
],
[
 'cle' => 'securite',
 'titre' => 'Sécurité',
 'contenu' =>
"## En-têtes HTTP (05/09/2026)\n" .
"- CSP (liste blanche Stripe + Holfuy), HSTS, Permissions-Policy, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, `X-Powered-By` masqué.\n" .
"- **Piège IONOS** : `.htaccess` ne pose pas les en-têtes sur les pages PHP → émis côté PHP (`inc/securite.php`).\n\n" .
"## Application\n" .
"- Session serveur (cookie HttpOnly + Secure + SameSite), mots de passe **bcrypt**.\n" .
"- **Anti-brute-force** (blocage temporaire après échecs), jetons CSRF, base hors dépôt Git.",
],
[
 'cle' => 'exploitation',
 'titre' => 'Sauvegardes, supervision & audit',
 'contenu' =>
"## Sur le VPS (dossier dédié)\n" .
"- **Sentinelle** toutes les 5 min (ping, 2 essais, alerte e-mail unique + retour en ligne).\n" .
"- **Sauvegarde** nocturne : base + secrets via endpoint d'export protégé, contrôle d'intégrité, gzip horodaté, **rotation 14 j**.\n" .
"- **Audit sécurité hebdo** (lundi) : en-têtes, cookies, chemins sensibles, protection admin, expiration TLS → rapport e-mail.\n" .
"- **E-mails via IONOS** (endpoint `notifier.php`), pas de service tiers.\n\n" .
"## Limite connue\n" .
"- Une panne **totale** ne peut pas s'auto-notifier (l'e-mail dépend d'IONOS). Filet externe (UptimeRobot) prévu pour la prod — voir `MONITORING-EXTERNE.md`.",
],
[
 'cle' => 'versionnage',
 'titre' => 'Suivi de version (Git/GitHub)',
 'contenu' =>
"## Dépôt\n" .
"- GitHub **privé** `ob3m-distribution/aeroclubsaumur`. Secrets et données personnelles exclus (`.gitignore`).\n" .
"- **Commit + push automatiques** à chaque fin de tâche (hook), avec liste d'exclusion de sécurité.\n" .
"- Le déploiement reste séparé (SFTP) : un push n'est pas une mise en ligne.",
],
[
 'cle' => 'incidents',
 'titre' => 'Incidents notables',
 'contenu' =>
"## Historique\n" .
"- **05/09/2026** : `membres.json` (données de 84 membres) poussé par erreur sur GitHub via le hook → retiré de l'historique (force-push) + `.gitignore` durci + hook renforcé.\n" .
"- **05/09/2026** : en-têtes de sécurité invisibles sur les pages PHP (mod_headers IONOS inactif sur PHP) → corrigé en émettant les en-têtes côté PHP.",
],
[
 'cle' => 'roadmap',
 'titre' => 'Chantiers en cours / à faire',
 'contenu' =>
"## À faire\n" .
"- **Bascule prod** : domaine public, retrait du mot de passe HTTP, Stripe clés live + webhook, purge données de démo, changement des secrets.\n" .
"- Activer le **filet de surveillance externe** (UptimeRobot) une fois la prod en ligne.\n" .
"- Moyens de paiement additionnels éventuels.\n\n" .
"## Idées\n" .
"- Renforcer la CSP par nonces (retirer `unsafe-inline`) si besoin.",
],
];
