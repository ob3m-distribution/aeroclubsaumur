-- ==================================================================
-- Correctif : bons_cadeaux sur la prod n'a jamais recu les colonnes
-- ajoutees en cours de route sur le dev pour les vols decouverte/
-- initiation (type_vol, nb_passagers, duree_initiation, offert_a) et
-- le suivi cote B.O. (numero_bon, date_realisation, pilote,
-- date_fin_validite, relances) -- jamais captees dans un fichier de
-- migration verse, donc jamais appliquees en prod. Constate le
-- 24/09/2026 : le formulaire "vol decouverte" echouait en prod avec
-- "Unknown column 'type_vol'" (reproduit et confirme sur une copie
-- locale du schema verse avant d'ecrire ce correctif).
-- Idempotent (IF NOT EXISTS) : peut etre execute plusieurs fois sans
-- risque.
-- ==================================================================

ALTER TABLE bons_cadeaux
  ADD COLUMN IF NOT EXISTS type_vol VARCHAR(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS nb_passagers TINYINT UNSIGNED DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS duree_initiation VARCHAR(10) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS offert_a VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS numero_bon VARCHAR(30) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS date_realisation DATE DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS pilote VARCHAR(80) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS date_fin_validite DATE DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS relances VARCHAR(60) NOT NULL DEFAULT '';

-- 'rembourse' existe deja en prod sur `role`... non : sur le statut du
-- bon lui-meme, ajoute sur le dev pour les remboursements (voir
-- email_commande... / mail.php email_paiement_recu_club et le motif
-- "rembourse" gere par le B.O.) mais absent du schema verse.
ALTER TABLE bons_cadeaux
  MODIFY COLUMN statut ENUM('en_attente_paiement','paye','utilise','expire','annule','rembourse')
  NOT NULL DEFAULT 'en_attente_paiement';
