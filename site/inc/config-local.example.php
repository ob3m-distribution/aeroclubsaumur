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

/** Clés Stripe. Utiliser les clés TEST en dev, les clés LIVE en production.
 *  Renseigner les vraies valeurs dans config-local.php (jamais ici). */
const STRIPE_CLE_PUBLIQUE   = 'VOTRE_CLE_PUBLIQUE_STRIPE';
const STRIPE_CLE_SECRETE    = 'VOTRE_CLE_SECRETE_STRIPE';
const STRIPE_WEBHOOK_SECRET = 'VOTRE_SECRET_WEBHOOK_STRIPE';
