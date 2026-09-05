<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config-local.php';

/**
 * Envoi d'un email texte en UTF-8.
 * Sur mutualise IONOS on passe par mail() : pas de dependance a installer,
 * et aucun shell pour en installer une de toute facon.
 */
function envoyer_email(string $destinataire, string $sujet, string $corps, ?string $repondreA = null): bool
{
    if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $expediteur = sprintf('=?UTF-8?B?%s?= <%s>',
        base64_encode(EMAIL_EXPEDITEUR_NOM), EMAIL_EXPEDITEUR);

    $entetes = [
        'From: ' . $expediteur,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    // On ne met en Reply-To qu'une adresse validee : sinon c'est une porte
    // ouverte a l'injection d'en-tetes.
    if ($repondreA !== null && filter_var($repondreA, FILTER_VALIDATE_EMAIL)) {
        $entetes[] = 'Reply-To: ' . $repondreA;
    }

    $sujetEncode = '=?UTF-8?B?' . base64_encode($sujet) . '?=';

    // Retours a la ligne normalises, lignes repliees : certains serveurs
    // rejettent les lignes de plus de 998 caracteres.
    $corps = wordwrap(str_replace(["\r\n", "\r"], "\n", $corps), 78, "\n", false);

    return @mail($destinataire, $sujetEncode, $corps, implode("\r\n", $entetes),
        '-f' . EMAIL_EXPEDITEUR);
}

/**
 * Envoi d'un e-mail HTML (avec repli texte) et pièces jointes éventuelles.
 * $pieces : liste de ['nom','type','data'].
 */
function envoyer_email_html_pj(string $destinataire, string $sujet, string $html, string $texte,
                              array $pieces = [], ?string $repondreA = null): bool
{
    if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) return false;
    $expediteur = sprintf('=?UTF-8?B?%s?= <%s>', base64_encode(EMAIL_EXPEDITEUR_NOM), EMAIL_EXPEDITEUR);
    $mixed = '=_m' . bin2hex(random_bytes(12));
    $alt   = '=_a' . bin2hex(random_bytes(12));

    $entetes = [
        'From: ' . $expediteur,
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $mixed . '"',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];
    if ($repondreA !== null && filter_var($repondreA, FILTER_VALIDATE_EMAIL)) {
        $entetes[] = 'Reply-To: ' . $repondreA;
    }

    $m  = '--' . $mixed . "\r\n";
    $m .= 'Content-Type: multipart/alternative; boundary="' . $alt . "\"\r\n\r\n";
    $m .= '--' . $alt . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $m .= chunk_split(base64_encode($texte)) . "\r\n";
    $m .= '--' . $alt . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $m .= chunk_split(base64_encode($html)) . "\r\n";
    $m .= '--' . $alt . "--\r\n";

    foreach ($pieces as $p) {
        $nom = preg_replace('/["\r\n]/', '', (string) $p['nom']);
        $m .= '--' . $mixed . "\r\n";
        $m .= 'Content-Type: ' . $p['type'] . '; name="' . $nom . "\"\r\n";
        $m .= "Content-Transfer-Encoding: base64\r\n";
        $m .= 'Content-Disposition: attachment; filename="' . $nom . "\"\r\n\r\n";
        $m .= chunk_split(base64_encode((string) $p['data'])) . "\r\n";
    }
    $m .= '--' . $mixed . "--\r\n";

    $sujetEncode = '=?UTF-8?B?' . base64_encode($sujet) . '?=';
    return @mail($destinataire, $sujetEncode, $m, implode("\r\n", $entetes), '-f' . EMAIL_EXPEDITEUR);
}

/** Gabarit HTML de marque (bandeau marine + carte blanche). Renvoie le HTML complet. */
function email_gabarit(string $titre, string $corpsHtml): string
{
    $nom = e(CLUB['nom']);
    return '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#eef1f5;font-family:Arial,Helvetica,sans-serif;color:#0e1112;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f5;padding:24px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(20,41,77,.08);">'
        . '<tr><td style="background:#14294D;padding:26px 32px;">'
        .   '<div style="color:#D8BC6A;font-size:13px;letter-spacing:3px;text-transform:uppercase;">Saumur</div>'
        .   '<div style="color:#ffffff;font-size:24px;font-weight:bold;letter-spacing:.5px;">' . $nom . '</div>'
        . '</td></tr>'
        . '<tr><td style="height:4px;background:#B08D2C;"></td></tr>'
        . '<tr><td style="padding:32px;">'
        .   '<h1 style="margin:0 0 16px;font-size:20px;color:#14294D;">' . e($titre) . '</h1>'
        .   $corpsHtml
        . '</td></tr>'
        . '<tr><td style="background:#f8f9f9;padding:20px 32px;border-top:1px solid #e1e4e5;font-size:12px;color:#83888a;">'
        .   e(CLUB['nom']) . ' — Aérodrome de Saumur Terrefort, Route de Marson, 49400 Saumur<br>'
        .   'Tél. ' . e(CLUB['tel_mobile']) . ' · ' . e(CLUB['email_vols'])
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/**
 * Envoi avec pièces jointes (multipart/mixed).
 * $pieces : liste de ['nom' => string, 'type' => mime, 'data' => contenu binaire].
 */
function envoyer_email_pj(string $destinataire, string $sujet, string $corps, array $pieces, ?string $repondreA = null): bool
{
    if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $expediteur = sprintf('=?UTF-8?B?%s?= <%s>',
        base64_encode(EMAIL_EXPEDITEUR_NOM), EMAIL_EXPEDITEUR);
    $bound = '=_' . bin2hex(random_bytes(16));

    $entetes = [
        'From: ' . $expediteur,
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $bound . '"',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];
    if ($repondreA !== null && filter_var($repondreA, FILTER_VALIDATE_EMAIL)) {
        $entetes[] = 'Reply-To: ' . $repondreA;
    }

    $texte = wordwrap(str_replace(["\r\n", "\r"], "\n", $corps), 78, "\r\n", false);
    $m  = '--' . $bound . "\r\n";
    $m .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $m .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $m .= chunk_split(base64_encode($texte)) . "\r\n";

    foreach ($pieces as $p) {
        $nom = preg_replace('/["\r\n]/', '', (string) $p['nom']);
        $m .= '--' . $bound . "\r\n";
        $m .= 'Content-Type: ' . $p['type'] . '; name="' . $nom . "\"\r\n";
        $m .= "Content-Transfer-Encoding: base64\r\n";
        $m .= 'Content-Disposition: attachment; filename="' . $nom . "\"\r\n\r\n";
        $m .= chunk_split(base64_encode((string) $p['data'])) . "\r\n";
    }
    $m .= '--' . $bound . "--\r\n";

    $sujetEncode = '=?UTF-8?B?' . base64_encode($sujet) . '?=';
    return @mail($destinataire, $sujetEncode, $m, implode("\r\n", $entetes),
        '-f' . EMAIL_EXPEDITEUR);
}

/**
 * E-mail contenant un lien pour définir / réinitialiser son mot de passe.
 * $invitation = true pour un nouveau membre (première connexion), sinon
 * c'est un « mot de passe oublié ».
 */
function email_lien_mot_de_passe(string $destinataire, string $prenom, string $lien, bool $invitation = false): bool
{
    if ($invitation) {
        $sujet = 'Votre accès à l’espace adhérents du Saumur Air Club';
        $corps = "Bonjour {$prenom},\n\n"
            . "Un compte vient d’être créé pour vous sur l’espace adhérents du Saumur Air Club.\n"
            . "Pour l’activer, cliquez sur le lien ci-dessous et choisissez votre mot de passe :\n\n"
            . $lien . "\n\n"
            . "Ce lien est valable 7 jours.\n\n"
            . "À bientôt,\nLe Saumur Air Club";
    } else {
        $sujet = 'Réinitialisation de votre mot de passe — Saumur Air Club';
        $corps = "Bonjour {$prenom},\n\n"
            . "Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le lien "
            . "ci-dessous pour en choisir un nouveau :\n\n"
            . $lien . "\n\n"
            . "Ce lien est valable 24 heures. Si vous n’êtes pas à l’origine de cette demande, "
            . "ignorez simplement cet e-mail.\n\n"
            . "Le Saumur Air Club";
    }
    return envoyer_email($destinataire, $sujet, $corps);
}

/** Invite un membre à remplir/renouveler son adhésion en ligne. */
function email_lien_reinscription(string $destinataire, string $prenom, string $lien): bool
{
    $annee = COTISATION_ANNEE;
    $bonjour = $prenom !== '' ? "Bonjour {$prenom}," : 'Bonjour,';
    $sujet = 'Renouvelez votre adhésion ' . $annee . ' — Saumur Air Club';
    $corps = "{$bonjour}\n\n"
        . "La campagne d'adhésion {$annee} est ouverte. Vous pouvez renouveler votre "
        . "adhésion en ligne, en quelques minutes, à l'adresse suivante :\n\n"
        . $lien . "\n\n"
        . "Connectez-vous à votre espace adhérent : vos informations de l'an dernier sont "
        . "déjà pré-remplies, il ne vous reste qu'à les vérifier, joindre votre licence et "
        . "votre visite médicale, puis régler la cotisation.\n\n"
        . "À très bientôt,\nLe Saumur Air Club";
    return envoyer_email($destinataire, $sujet, $corps);
}

/** Envoie au futur membre les modalités de règlement de sa cotisation. */
function email_lien_paiement(string $destinataire, string $prenom, string $mode, int $montantCents, string $reference): bool
{
    $montant = prix($montantCents);
    $bonjour = $prenom !== '' ? "Bonjour {$prenom}," : 'Bonjour,';
    if ($mode === 'carte') {
        $sujet = 'Réglez votre cotisation par carte — Saumur Air Club';
        $corps = "{$bonjour}\n\nVotre dossier d'inscription est complet. Il ne reste qu'à régler votre "
            . "cotisation de {$montant}.\n\nPaiement par carte bancaire : votre lien de paiement sécurisé "
            . "vous est adressé ci-dessous.\n\n"
            . "  Montant   : {$montant}\n"
            . "  Référence : {$reference}\n\n"
            . "(Le paiement par carte en ligne sera bientôt actif ; en attendant, le virement reste "
            . "possible — répondez à cet e-mail pour les coordonnées.)\n\n"
            . "À très bientôt,\nLe Saumur Air Club";
    } else {
        $sujet = 'Réglez votre cotisation par virement — Saumur Air Club';
        $corps = "{$bonjour}\n\nVotre dossier d'inscription est complet. Il ne reste qu'à régler votre "
            . "cotisation de {$montant} par virement bancaire :\n\n"
            . "  Bénéficiaire : " . CLUB['nom'] . "\n"
            . "  IBAN         : FR76 —— à compléter par le club ——\n"
            . "  Référence    : {$reference}\n"
            . "  Montant      : {$montant}\n\n"
            . "Votre adhésion sera activée dès réception du virement.\n\n"
            . "À très bientôt,\nLe Saumur Air Club";
    }
    return envoyer_email($destinataire, $sujet, $corps);
}

/** Invite une personne (sans compte) à remplir une demande de pré-inscription. */
function email_lien_preinscription(string $destinataire, string $lien): bool
{
    $sujet = 'Rejoignez le Saumur Air Club — demande d’inscription';
    $corps = "Bonjour,\n\n"
        . "Vous souhaitez rejoindre le Saumur Air Club ? Remplissez votre demande "
        . "d'inscription en ligne à l'adresse suivante :\n\n"
        . $lien . "\n\n"
        . "Nous reviendrons vers vous rapidement pour finaliser votre adhésion.\n\n"
        . "À très bientôt,\nLe Saumur Air Club";
    return envoyer_email($destinataire, $sujet, $corps);
}

/** Notification au club : une demande vient d'arriver. */
function email_notification_club(array $bon): bool
{
    $sujet = sprintf('Nouvelle demande de bon cadeau — %s', $bon['reference']);

    $corps = <<<TXT
Une nouvelle demande de bon cadeau vient d'être enregistrée sur le site.

RÉFÉRENCE : {$bon['reference']}
MONTANT    : {$bon['montant_affiche']}
STATUT     : en attente de paiement

ACHETEUR
  {$bon['prenom']} {$bon['nom']}
  Email     : {$bon['email']}
  Téléphone : {$bon['telephone']}

TXT;

    if (!empty($bon['message'])) {
        $corps .= "MESSAGE\n  {$bon['message']}\n\n";
    }

    $corps .= <<<TXT
--
Le paiement en ligne n'est pas encore actif : merci de recontacter
l'acheteur pour convenir du règlement.

Message automatique du site du Saumur Air Club.
TXT;

    return envoyer_email(EMAIL_CLUB, $sujet, $corps, $bon['email']);
}

/** Le bon cadeau lui-même, envoyé à l'acheteur une fois le paiement confirmé. */
function email_bon_cadeau(array $bon): bool
{
    require_once __DIR__ . '/pdf-bon.php';

    $numero  = (string) ($bon['numero_bon'] ?: $bon['reference']);
    $sujet   = 'Votre bon cadeau — ' . $numero;

    $fin     = date('d/m/Y', strtotime((string) ($bon['date_fin_validite'] ?: $bon['expire_le'])));
    $montant = prix((int) $bon['montant_cents']);
    $tel     = CLUB['tel_mobile'];
    $email   = CLUB['email_vols'];
    $prenom  = $bon['acheteur_prenom'];

    $vol = libelle_vol_bon($bon);
    $offert = trim((string) ($bon['offert_a'] ?? ''));

    // Version texte (repli).
    $texte = "Bonjour {$prenom},\n\nVotre paiement a bien été reçu — merci !\n\n"
        . "Voici votre bon cadeau ({$vol}). Vous le trouverez aussi en pièce jointe (PDF).\n\n"
        . "  N° DU BON : {$numero}\n  Valable jusqu'au {$fin}\n  Montant réglé : {$montant}\n\n"
        . "COMMENT L'UTILISER\nLe bénéficiaire contacte le club, muni de ce bon, pour convenir\n"
        . "d'une date de vol.\n\n  Téléphone : {$tel}\n  Email : {$email}\n\n"
        . "À très bientôt dans le ciel saumurois,\nLe Saumur Air Club";

    // Version HTML à la charte.
    $offertHtml = $offert !== '' ? '<tr><td style="padding:6px 0;color:#83888a;">Offert à</td><td style="padding:6px 0;text-align:right;font-weight:bold;color:#14294D;">' . e($offert) . '</td></tr>' : '';
    $corpsHtml =
        '<p style="margin:0 0 18px;font-size:15px;line-height:1.6;">Bonjour <strong>' . e($prenom) . '</strong>,<br>'
        . 'votre paiement a bien été reçu — merci ! Voici votre bon cadeau, également joint en PDF prêt à offrir.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
        .   'style="border:2px solid #B08D2C;border-radius:10px;padding:6px 20px;margin:0 0 20px;">'
        .   '<tr><td colspan="2" style="padding:14px 0 6px;text-align:center;color:#14294D;font-size:13px;letter-spacing:2px;text-transform:uppercase;">Bon cadeau · ' . e($vol) . '</td></tr>'
        .   '<tr><td style="padding:6px 0;color:#83888a;">N° du bon</td><td style="padding:6px 0;text-align:right;font-weight:bold;font-size:18px;color:#B08D2C;">' . e($numero) . '</td></tr>'
        .   '<tr><td style="padding:6px 0;color:#83888a;">Montant réglé</td><td style="padding:6px 0;text-align:right;font-weight:bold;color:#14294D;">' . e($montant) . '</td></tr>'
        .   '<tr><td style="padding:6px 0 14px;color:#83888a;">Valable jusqu\'au</td><td style="padding:6px 0 14px;text-align:right;font-weight:bold;color:#14294D;">' . e($fin) . '</td></tr>'
        .   $offertHtml
        . '</table>'
        . '<p style="margin:0 0 6px;font-size:15px;font-weight:bold;color:#14294D;">Comment l\'utiliser</p>'
        . '<p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#55595b;">Le bénéficiaire contacte le club, '
        .   'muni de ce bon, pour convenir d\'une date de vol selon la météo et les disponibilités.<br>'
        .   'Téléphone : <strong>' . e($tel) . '</strong> · Email : <strong>' . e($email) . '</strong></p>'
        . '<p style="margin:0;font-size:14px;color:#55595b;">Belle surprise à faire, et à très bientôt dans le ciel saumurois.</p>';

    $html = email_gabarit('Votre bon cadeau est prêt', $corpsHtml);

    try {
        $pdf = pdf_bon_cadeau($bon);
        return envoyer_email_html_pj($bon['acheteur_email'], $sujet, $html, $texte, [[
            'nom' => 'bon-cadeau-' . $numero . '.pdf', 'type' => 'application/pdf', 'data' => $pdf,
        ]]);
    } catch (Throwable $e) {
        return envoyer_email_html_pj($bon['acheteur_email'], $sujet, $html, $texte);
    }
}

/**
 * Relance avant expiration d'un bon cadeau non encore utilisé.
 * $joursRestants : nombre de jours avant la date de fin de validité.
 */
function email_relance_bon(array $bon, int $joursRestants): bool
{
    $fin    = date('d/m/Y', strtotime((string) $bon['date_fin_validite']));
    $tel    = CLUB['tel_mobile'];
    $email  = CLUB['email_vols'];
    $prenom = $bon['acheteur_prenom'];
    $num    = $bon['numero_bon'] ?: $bon['reference'];

    $delai = $joursRestants <= 1 ? 'demain'
           : ($joursRestants <= 3 ? 'dans ' . $joursRestants . ' jours'
           : ($joursRestants <= 10 ? 'dans ' . $joursRestants . ' jours'
           : ($joursRestants <= 31 ? 'dans un mois environ' : 'dans trois mois environ')));

    $sujet = 'Votre bon cadeau expire bientôt — ' . $num;

    $corps = <<<TXT
Bonjour {$prenom},

Votre bon cadeau pour un vol au-dessus du Val de Loire arrive à
échéance {$delai}.

  ┌────────────────────────────────────────┐
     BON N° : {$num}
     À utiliser avant le {$fin}
  └────────────────────────────────────────┘

Il vous suffit de contacter le club pour convenir d'une date de vol,
selon la météo et les disponibilités. Ne laissez pas passer ce beau
moment !

  Téléphone : {$tel}
  Email     : {$email}

À très bientôt dans le ciel saumurois,
Le Saumur Air Club

--
Aérodrome de Saumur Terrefort
Route de Marson, 49400 SAUMUR
TXT;

    return envoyer_email($bon['acheteur_email'], $sujet, $corps);
}

/** Notification au club : un paiement vient d'aboutir. */
function email_paiement_recu_club(array $bon): bool
{
    $sujet = 'Bon cadeau PAYÉ — ' . $bon['code'];
    $montant = prix((int) $bon['montant_cents']);
    $expire  = date('d/m/Y', strtotime((string) $bon['expire_le']));

    $corps = <<<TXT
Un bon cadeau vient d'être payé en ligne.

CODE       : {$bon['code']}
RÉFÉRENCE  : {$bon['reference']}
MONTANT    : {$montant}
VALABLE JUSQU'AU : {$expire}

ACHETEUR
  {$bon['acheteur_prenom']} {$bon['acheteur_nom']}
  Email     : {$bon['acheteur_email']}
  Téléphone : {$bon['acheteur_telephone']}

Le bon a été envoyé automatiquement à l'acheteur.
Le bénéficiaire vous contactera pour convenir d'une date de vol.

--
Message automatique du site du Saumur Air Club.
TXT;

    return envoyer_email(EMAIL_CLUB, $sujet, $corps, $bon['acheteur_email']);
}

/** Accusé de réception à l'acheteur. */
function email_accuse_acheteur(array $bon): bool
{
    $sujet = sprintf('Votre demande de bon cadeau — %s', $bon['reference']);

    $tel   = CLUB['tel_mobile'];
    $email = CLUB['email_vols'];

    $corps = <<<TXT
Bonjour {$bon['prenom']},

Nous avons bien reçu votre demande de bon cadeau pour un vol découverte
au-dessus du Val de Loire. Merci !

VOTRE RÉFÉRENCE : {$bon['reference']}
MONTANT          : {$bon['montant_affiche']}

Le club va vous recontacter très rapidement pour finaliser le règlement
et vous transmettre votre bon cadeau.

Une question d'ici là ?
  Téléphone : {$tel}
  Email     : {$email}

À très bientôt dans le ciel saumurois,
Le Saumur Air Club

--
Aérodrome de Saumur Terrefort
Route de Marson, 49400 SAUMUR
TXT;

    return envoyer_email($bon['email'], $sujet, $corps);
}
