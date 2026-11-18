<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
   MODÈLE de configuration locale.
   Copier ce fichier en « config-local.php » et renseigner les vraies
   valeurs. Le vrai config-local.php n'est JAMAIS versionné (.gitignore).
   ------------------------------------------------------------------ */

const BDD = [
    'hote'         => 'HOTE_MYSQL',
    'port'         => 3306,
    'base'         => 'NOM_DE_LA_BASE',
    'utilisateur'  => 'UTILISATEUR',
    'mot_de_passe' => 'MOT_DE_PASSE',
];

/** Destinataire des notifications de nouvelle demande. */
const EMAIL_CLUB = 'voler@saumurairclub.fr';

/**
 * Expéditeur des e-mails automatiques.
 * Doit appartenir au domaine hébergé, sinon SPF échoue et tout part en spam.
 */
const EMAIL_EXPEDITEUR = 'noreply@aeroclub-saumur.fr';
const EMAIL_EXPEDITEUR_NOM = 'Saumur Air Club';

/** Paiement en ligne HelloAsso (API v5). Clés d'API : espace HelloAsso de
 *  l'association > Mon compte > Intégrations et API. L'identifiant de
 *  l'association (« slug ») est la fin de l'adresse de sa page HelloAsso.
 *  Mettre HELLOASSO_SANDBOX à true avec des clés de l'environnement de test
 *  (helloasso-sandbox.com). Renseigner les vraies valeurs dans config-local.php. */
const HELLOASSO_CLIENT_ID     = 'VOTRE_CLIENT_ID_HELLOASSO';
const HELLOASSO_CLIENT_SECRET = 'VOTRE_CLIENT_SECRET_HELLOASSO';
const HELLOASSO_ORG_SLUG      = 'SLUG_DE_L_ASSOCIATION';
const HELLOASSO_SANDBOX       = false;
