-- Bons cadeaux (réunion bureau, 28/09/2026)
--  1. Mode de paiement : stripe (en ligne), especes, cheque, cb_club
--     (TPE du club), virement. Le statut reste paye / en_attente_paiement :
--     le libellé affiché combine les deux (« Payé en espèces »,
--     « En attente virement »…).
--  2. Lien de paiement Stripe envoyé par e-mail (jeton dans le lien).
--  3. Validité : un an partout. Les bons déjà payés avec une fin de
--     validité à 6 mois passent à un an après le paiement (les bons
--     prolongés au-delà gardent leur date).

ALTER TABLE bons_cadeaux
  ADD COLUMN IF NOT EXISTS mode_paiement VARCHAR(20) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS lien_jeton VARCHAR(64) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS cree_par INT UNSIGNED NULL DEFAULT NULL;

UPDATE bons_cadeaux
   SET mode_paiement = 'stripe'
 WHERE mode_paiement IS NULL AND stripe_payment_intent_id IS NOT NULL;

UPDATE bons_cadeaux
   SET date_fin_validite = DATE_ADD(DATE(paye_le), INTERVAL 1 YEAR),
       relances = ''
 WHERE paye_le IS NOT NULL
   AND (date_fin_validite IS NULL OR date_fin_validite < DATE_ADD(DATE(paye_le), INTERVAL 1 YEAR));

UPDATE bons_cadeaux
   SET expire_le = DATE_ADD(DATE(paye_le), INTERVAL 1 YEAR)
 WHERE paye_le IS NOT NULL
   AND (expire_le IS NULL OR expire_le < DATE_ADD(DATE(paye_le), INTERVAL 1 YEAR));
