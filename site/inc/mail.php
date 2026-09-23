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

/**
 * URL absolue du logo blanc (fond sombre), pour l'en-tête des e-mails.
 * Construite depuis le domaine d'expédition (EMAIL_EXPEDITEUR) plutôt que
 * $_SERVER['HTTP_HOST'] : les e-mails partent aussi depuis les tâches cron
 * (site/taches), sans aucun contexte HTTP -- toujours le domaine de prod,
 * jamais dev (qui de toute façon exige un mot de passe HTTP, voir
 * .htaccess -- l'image n'y serait pas chargeable par un client mail).
 */
function email_logo_url(): string
{
    $domaine = strrchr(EMAIL_EXPEDITEUR, '@');
    return 'https://' . ($domaine !== false ? substr($domaine, 1) : 'aeroclub-saumur.fr') . '/assets/img/logo-blanc.png';
}

/** Gabarit HTML de marque (bandeau marine + logo + liseré or + carte blanche).
 *  Refonte du 23/09/2026 : logo au lieu d'un simple libellé texte, liseré en
 *  dégradé (au lieu d'un aplat), police alignée sur --police de style.css
 *  (system-ui : la police choisie en back-office est auto-hébergée, donc
 *  injoignable depuis un e-mail -- même repli que l'option "Inter" du BO). */
function email_gabarit(string $titre, string $corpsHtml): string
{
    $police = "system-ui,-apple-system,'Segoe UI',Roboto,sans-serif";
    return '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#EFF4F9;font-family:' . $police . ';color:#16263F;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EFF4F9;padding:32px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(20,41,77,.08),0 8px 24px -8px rgba(20,41,77,.16);">'
        . '<tr><td style="background:#14294D;padding:30px 32px;text-align:center;">'
        .   '<img src="' . e(email_logo_url()) . '" alt="' . e(CLUB['nom']) . '" width="180" height="44" style="display:block;margin:0 auto;height:44px;width:auto;border:0;">'
        . '</td></tr>'
        . '<tr><td style="height:4px;line-height:0;font-size:0;background:linear-gradient(90deg,#8C6E1F,#D8BC6A,#B08D2C);"></td></tr>'
        . '<tr><td style="padding:36px 32px;">'
        .   '<h1 style="margin:0 0 18px;font-size:21px;color:#14294D;">' . e($titre) . '</h1>'
        .   $corpsHtml
        . '</td></tr>'
        . '<tr><td style="background:#f8f9f9;padding:22px 32px;border-top:1px solid #e1e4e5;font-size:12px;color:#83888a;">'
        .   '<strong style="color:#14294D;">' . e(CLUB['nom']) . '</strong><br>'
        .   e(CLUB['adresse_1']) . ', ' . e(CLUB['adresse_2']) . '<br>'
        .   'Tél. ' . e(CLUB['tel_mobile']) . ' · ' . e(CLUB['email_vols'])
        . '</td></tr>'
        . '</table>'
        . '<p style="margin:14px 0 0;font-size:11px;color:#9AA4B2;">Cet e-mail vous est adressé par ' . e(CLUB['nom']) . ' suite à une démarche sur notre site.</p>'
        . '</td></tr></table></body></html>';
}

/** Bouton d'action des e-mails : même bleu marine que .bouton--sombre du
 *  site (style.css, --bleu-nuit "fonds sombres : hero, pied, panneaux,
 *  boutons"), même rayon (--rayon: 3px). */
function email_bouton(string $href, string $libelle): string
{
    return '<a href="' . e($href) . '" style="display:inline-block;background:#14294D;border:1px solid #14294D;color:#ffffff;font-size:15px;font-weight:600;padding:13px 28px;border-radius:3px;text-decoration:none;">' . e($libelle) . '</a>';
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
    $bonjour = $prenom !== '' ? "Bonjour {$prenom}," : 'Bonjour,';
    if ($invitation) {
        $sujet = 'Votre accès à l’espace adhérents du Saumur Air Club';
        $texte = "{$bonjour}\n\n"
            . "Un compte vient d’être créé pour vous sur l’espace adhérents du Saumur Air Club.\n"
            . "Pour l’activer, cliquez sur le lien ci-dessous et choisissez votre mot de passe :\n\n"
            . $lien . "\n\n"
            . "Ce lien est valable 7 jours.\n\n"
            . "À bientôt,\nLe Saumur Air Club";
        $intro = 'Un compte vient d\'être créé pour vous sur l\'espace adhérents du club. Pour l\'activer, choisissez votre mot de passe en cliquant ci-dessous :';
        $texteBouton = 'Activer mon compte';
        $validite = 'Ce lien est valable 7 jours.';
    } else {
        $sujet = 'Réinitialisation de votre mot de passe — Saumur Air Club';
        $texte = "{$bonjour}\n\n"
            . "Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le lien "
            . "ci-dessous pour en choisir un nouveau :\n\n"
            . $lien . "\n\n"
            . "Ce lien est valable 24 heures. Si vous n’êtes pas à l’origine de cette demande, "
            . "ignorez simplement cet e-mail.\n\n"
            . "Le Saumur Air Club";
        $intro = 'Vous avez demandé à réinitialiser votre mot de passe. Choisissez-en un nouveau en cliquant ci-dessous :';
        $texteBouton = 'Choisir un nouveau mot de passe';
        $validite = 'Ce lien est valable 24 heures. Si vous n\'êtes pas à l\'origine de cette demande, ignorez simplement cet e-mail.';
    }
    $corpsHtml = '<p style="margin:0 0 22px;font-size:15px;line-height:1.6;">' . e($bonjour) . '<br><br>' . e($intro) . '</p>'
        . '<p style="margin:0 0 22px;">' . email_bouton($lien, $texteBouton) . '</p>'
        . '<p style="margin:0;font-size:13px;line-height:1.6;color:#78859A;">' . e($validite) . '</p>';
    $html = email_gabarit($invitation ? 'Bienvenue au club' : 'Réinitialisation de mot de passe', $corpsHtml);
    return envoyer_email_html_pj($destinataire, $sujet, $html, $texte);
}

/** Invite un membre à remplir/renouveler son adhésion en ligne. */
function email_lien_reinscription(string $destinataire, string $prenom, string $lien): bool
{
    $annee = COTISATION_ANNEE;
    $bonjour = $prenom !== '' ? "Bonjour {$prenom}," : 'Bonjour,';
    $sujet = 'Renouvelez votre adhésion ' . $annee . ' — Saumur Air Club';
    $texte = "{$bonjour}\n\n"
        . "La campagne d'adhésion {$annee} est ouverte. Vous pouvez renouveler votre "
        . "adhésion en ligne, en quelques minutes, à l'adresse suivante :\n\n"
        . $lien . "\n\n"
        . "Connectez-vous à votre espace adhérent : vos informations de l'an dernier sont "
        . "déjà pré-remplies, il ne vous reste qu'à les vérifier, joindre votre licence et "
        . "votre visite médicale, puis régler la cotisation.\n\n"
        . "À très bientôt,\nLe Saumur Air Club";
    $corpsHtml = '<p style="margin:0 0 18px;font-size:15px;line-height:1.6;">' . e($bonjour) . '<br><br>'
        . "La campagne d'adhésion <strong>{$annee}</strong> est ouverte. Vous pouvez renouveler votre adhésion en ligne, en quelques minutes.</p>"
        . '<p style="margin:0 0 22px;">' . email_bouton($lien, 'Renouveler mon adhésion') . '</p>'
        . '<p style="margin:0;font-size:14px;line-height:1.6;color:#4C596B;">Connectez-vous à votre espace adhérent : vos informations de l\'an dernier sont déjà pré-remplies, il ne vous reste qu\'à les vérifier, joindre votre licence et votre visite médicale, puis régler la cotisation.</p>';
    $html = email_gabarit('Renouvellement d\'adhésion ' . $annee, $corpsHtml);
    return envoyer_email_html_pj($destinataire, $sujet, $html, $texte);
}

/** Envoie au futur membre les modalités de règlement de sa cotisation. */
function email_lien_paiement(string $destinataire, string $prenom, string $mode, int $montantCents, string $reference): bool
{
    $montant = prix($montantCents);
    $bonjour = $prenom !== '' ? "Bonjour {$prenom}," : 'Bonjour,';
    // Encart "Montant / Référence [/ IBAN]" -- même gabarit visuel que le
    // récapitulatif du bon cadeau (email_bon_cadeau), pour rester cohérent
    // entre les deux seuls e-mails du site qui affichent un montant à régler.
    $ligneIban = $mode !== 'carte'
        ? '<tr><td style="padding:6px 0;color:#83888a;">IBAN</td><td style="padding:6px 0;text-align:right;font-weight:bold;color:#14294D;">FR76 —— à compléter par le club ——</td></tr>'
        : '';
    $encart = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
        . 'style="border:2px solid #B08D2C;border-radius:10px;padding:6px 20px;margin:0 0 22px;">'
        . '<tr><td style="padding:6px 0;color:#83888a;">Montant</td><td style="padding:6px 0;text-align:right;font-weight:bold;font-size:18px;color:#14294D;">' . e($montant) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#83888a;">Référence</td><td style="padding:6px 0;text-align:right;font-weight:bold;color:#14294D;">' . e($reference) . '</td></tr>'
        . $ligneIban
        . '</table>';

    if ($mode === 'carte') {
        $sujet = 'Réglez votre cotisation par carte — Saumur Air Club';
        $texte = "{$bonjour}\n\nVotre dossier d'inscription est complet. Il ne reste qu'à régler votre "
            . "cotisation de {$montant}.\n\nPaiement par carte bancaire : votre lien de paiement sécurisé "
            . "vous est adressé ci-dessous.\n\n"
            . "  Montant   : {$montant}\n"
            . "  Référence : {$reference}\n\n"
            . "(Le paiement par carte en ligne sera bientôt actif ; en attendant, le virement reste "
            . "possible — répondez à cet e-mail pour les coordonnées.)\n\n"
            . "À très bientôt,\nLe Saumur Air Club";
        $corpsHtml = '<p style="margin:0 0 20px;font-size:15px;line-height:1.6;">' . e($bonjour) . '<br><br>Votre dossier d\'inscription est complet. Il ne reste qu\'à régler votre cotisation.</p>'
            . $encart
            . '<p style="margin:0;font-size:13px;line-height:1.6;color:#78859A;">Le paiement par carte en ligne sera bientôt actif ; en attendant, le virement reste possible — répondez à cet e-mail pour les coordonnées.</p>';
    } else {
        $sujet = 'Réglez votre cotisation par virement — Saumur Air Club';
        $texte = "{$bonjour}\n\nVotre dossier d'inscription est complet. Il ne reste qu'à régler votre "
            . "cotisation de {$montant} par virement bancaire :\n\n"
            . "  Bénéficiaire : " . CLUB['nom'] . "\n"
            . "  IBAN         : FR76 —— à compléter par le club ——\n"
            . "  Référence    : {$reference}\n"
            . "  Montant      : {$montant}\n\n"
            . "Votre adhésion sera activée dès réception du virement.\n\n"
            . "À très bientôt,\nLe Saumur Air Club";
        $corpsHtml = '<p style="margin:0 0 20px;font-size:15px;line-height:1.6;">' . e($bonjour) . '<br><br>Votre dossier d\'inscription est complet. Il ne reste qu\'à régler votre cotisation par virement bancaire, au bénéficiaire <strong>' . e(CLUB['nom']) . '</strong> :</p>'
            . $encart
            . '<p style="margin:0;font-size:14px;line-height:1.6;color:#4C596B;">Votre adhésion sera activée dès réception du virement.</p>';
    }
    $html = email_gabarit($mode === 'carte' ? 'Paiement de votre cotisation' : 'Paiement par virement', $corpsHtml);
    return envoyer_email_html_pj($destinataire, $sujet, $html, $texte);
}

/** Invite une personne (sans compte) à remplir une demande de pré-inscription. */
function email_lien_preinscription(string $destinataire, string $lien): bool
{
    $sujet = 'Rejoignez le Saumur Air Club — demande d’inscription';
    $texte = "Bonjour,\n\n"
        . "Vous souhaitez rejoindre le Saumur Air Club ? Remplissez votre demande "
        . "d'inscription en ligne à l'adresse suivante :\n\n"
        . $lien . "\n\n"
        . "Nous reviendrons vers vous rapidement pour finaliser votre adhésion.\n\n"
        . "À très bientôt,\nLe Saumur Air Club";
    $corpsHtml = '<p style="margin:0 0 22px;font-size:15px;line-height:1.6;">Bonjour,<br><br>Vous souhaitez rejoindre le Saumur Air Club ? Remplissez votre demande d\'inscription en ligne en cliquant ci-dessous :</p>'
        . '<p style="margin:0 0 22px;">' . email_bouton($lien, 'Faire ma demande d\'inscription') . '</p>'
        . '<p style="margin:0;font-size:14px;line-height:1.6;color:#4C596B;">Nous reviendrons vers vous rapidement pour finaliser votre adhésion.</p>';
    $html = email_gabarit('Rejoindre le club', $corpsHtml);
    return envoyer_email_html_pj($destinataire, $sujet, $html, $texte);
}

/** Notification au club : une demande vient d'arriver. */
function email_notification_club(array $bon): bool
{
    $sujet = sprintf('Nouvelle demande de bon cadeau — %s', $bon['reference']);

    $texte = <<<TXT
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
        $texte .= "MESSAGE\n  {$bon['message']}\n\n";
    }

    $texte .= <<<TXT
--
Le paiement en ligne n'est pas encore actif : merci de recontacter
l'acheteur pour convenir du règlement.

Message automatique du site du Saumur Air Club.
TXT;

    $messageHtml = !empty($bon['message'])
        ? '<p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#4C596B;"><strong style="color:#14294D;">Message</strong><br>' . nl2br(e((string) $bon['message'])) . '</p>'
        : '';
    $corpsHtml =
        '<p style="margin:0 0 18px;font-size:15px;line-height:1.6;">Une nouvelle demande de bon cadeau vient d\'être enregistrée sur le site.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
        .   'style="border:2px solid #B08D2C;border-radius:10px;padding:6px 20px;margin:0 0 20px;">'
        .   '<tr><td style="padding:6px 0;color:#83888a;">Référence</td><td style="padding:6px 0;text-align:right;font-weight:bold;color:#14294D;">' . e($bon['reference']) . '</td></tr>'
        .   '<tr><td style="padding:6px 0;color:#83888a;">Montant</td><td style="padding:6px 0;text-align:right;font-weight:bold;font-size:18px;color:#14294D;">' . e($bon['montant_affiche']) . '</td></tr>'
        .   '<tr><td style="padding:6px 0;color:#83888a;">Statut</td><td style="padding:6px 0;text-align:right;font-weight:bold;color:#B08D2C;">En attente de paiement</td></tr>'
        . '</table>'
        . '<p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#4C596B;"><strong style="color:#14294D;">Acheteur</strong><br>'
        .   e($bon['prenom']) . ' ' . e($bon['nom']) . '<br>Email : ' . e($bon['email']) . '<br>Téléphone : ' . e($bon['telephone']) . '</p>'
        . $messageHtml
        . '<p style="margin:0;font-size:13px;line-height:1.6;color:#78859A;">Le paiement en ligne n\'est pas encore actif : merci de recontacter l\'acheteur pour convenir du règlement.</p>';
    $html = email_gabarit('Nouvelle demande de bon cadeau', $corpsHtml);

    return envoyer_email_html_pj(EMAIL_CLUB, $sujet, $html, $texte, [], $bon['email']);
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

    $texte = <<<TXT
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

    // Même encart que email_bon_cadeau (boîte à liseré or) : c'est le même
    // bon qu'on rappelle, il doit rester immédiatement reconnaissable.
    $corpsHtml =
        '<p style="margin:0 0 18px;font-size:15px;line-height:1.6;">Bonjour <strong>' . e($prenom) . '</strong>,<br>'
        . 'votre bon cadeau pour un vol au-dessus du Val de Loire arrive à échéance <strong>' . e($delai) . '</strong>. Ne laissez pas passer ce beau moment !</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
        .   'style="border:2px solid #B08D2C;border-radius:10px;padding:6px 20px;margin:0 0 22px;">'
        .   '<tr><td style="padding:6px 0;color:#83888a;">N° du bon</td><td style="padding:6px 0;text-align:right;font-weight:bold;font-size:18px;color:#B08D2C;">' . e((string) $num) . '</td></tr>'
        .   '<tr><td style="padding:6px 0;color:#83888a;">À utiliser avant le</td><td style="padding:6px 0;text-align:right;font-weight:bold;color:#14294D;">' . e($fin) . '</td></tr>'
        . '</table>'
        . '<p style="margin:0;font-size:14px;line-height:1.6;color:#4C596B;">Il vous suffit de contacter le club pour convenir d\'une date de vol, selon la météo et les disponibilités.<br>'
        .   'Téléphone : <strong>' . e($tel) . '</strong> · Email : <strong>' . e($email) . '</strong></p>';
    $html = email_gabarit('Votre bon cadeau expire bientôt', $corpsHtml);

    return envoyer_email_html_pj($bon['acheteur_email'], $sujet, $html, $texte);
}

/** Notification au club : un paiement vient d'aboutir. */
function email_paiement_recu_club(array $bon): bool
{
    $sujet = 'Bon cadeau PAYÉ — ' . $bon['code'];
    $montant = prix((int) $bon['montant_cents']);
    $expire  = date('d/m/Y', strtotime((string) $bon['expire_le']));

    $texte = <<<TXT
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

    $corpsHtml =
        '<p style="margin:0 0 18px;font-size:15px;line-height:1.6;">Un bon cadeau vient d\'être payé en ligne.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
        .   'style="border:2px solid #B08D2C;border-radius:10px;padding:6px 20px;margin:0 0 20px;">'
        .   '<tr><td style="padding:6px 0;color:#83888a;">Code</td><td style="padding:6px 0;text-align:right;font-weight:bold;color:#14294D;">' . e($bon['code']) . '</td></tr>'
        .   '<tr><td style="padding:6px 0;color:#83888a;">Référence</td><td style="padding:6px 0;text-align:right;font-weight:bold;color:#14294D;">' . e($bon['reference']) . '</td></tr>'
        .   '<tr><td style="padding:6px 0;color:#83888a;">Montant</td><td style="padding:6px 0;text-align:right;font-weight:bold;font-size:18px;color:#14294D;">' . e($montant) . '</td></tr>'
        .   '<tr><td style="padding:6px 0;color:#83888a;">Valable jusqu\'au</td><td style="padding:6px 0;text-align:right;font-weight:bold;color:#14294D;">' . e($expire) . '</td></tr>'
        . '</table>'
        . '<p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#4C596B;"><strong style="color:#14294D;">Acheteur</strong><br>'
        .   e($bon['acheteur_prenom']) . ' ' . e($bon['acheteur_nom']) . '<br>Email : ' . e($bon['acheteur_email']) . '<br>Téléphone : ' . e($bon['acheteur_telephone']) . '</p>'
        . '<p style="margin:0;font-size:13px;line-height:1.6;color:#78859A;">Le bon a été envoyé automatiquement à l\'acheteur. Le bénéficiaire vous contactera pour convenir d\'une date de vol.</p>';
    $html = email_gabarit('Bon cadeau payé', $corpsHtml);

    return envoyer_email_html_pj(EMAIL_CLUB, $sujet, $html, $texte, [], $bon['acheteur_email']);
}

/** Accusé de réception à l'acheteur. */
function email_accuse_acheteur(array $bon): bool
{
    $sujet = sprintf('Votre demande de bon cadeau — %s', $bon['reference']);

    $tel   = CLUB['tel_mobile'];
    $email = CLUB['email_vols'];

    $texte = <<<TXT
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

    $corpsHtml =
        '<p style="margin:0 0 18px;font-size:15px;line-height:1.6;">Bonjour <strong>' . e($bon['prenom']) . '</strong>,<br>'
        . 'nous avons bien reçu votre demande de bon cadeau pour un vol découverte au-dessus du Val de Loire. Merci !</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
        .   'style="border:2px solid #B08D2C;border-radius:10px;padding:6px 20px;margin:0 0 22px;">'
        .   '<tr><td style="padding:6px 0;color:#83888a;">Votre référence</td><td style="padding:6px 0;text-align:right;font-weight:bold;color:#14294D;">' . e($bon['reference']) . '</td></tr>'
        .   '<tr><td style="padding:6px 0;color:#83888a;">Montant</td><td style="padding:6px 0;text-align:right;font-weight:bold;font-size:18px;color:#14294D;">' . e($bon['montant_affiche']) . '</td></tr>'
        . '</table>'
        . '<p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#4C596B;">Le club va vous recontacter très rapidement pour finaliser le règlement et vous transmettre votre bon cadeau.</p>'
        . '<p style="margin:0;font-size:14px;line-height:1.6;color:#4C596B;">Une question d\'ici là ?<br>Téléphone : <strong>' . e($tel) . '</strong> · Email : <strong>' . e($email) . '</strong></p>';
    $html = email_gabarit('Demande bien reçue', $corpsHtml);

    return envoyer_email_html_pj($bon['email'], $sujet, $html, $texte);
}
