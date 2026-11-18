-- Paiement en ligne via HelloAsso (remplace Stripe). Un enregistrement par
-- « intention de paiement » (checkout) créée : la vérité du paiement vient
-- toujours de l'API HelloAsso, jamais de l'adresse de retour ni du contenu
-- d'une notification. Les colonnes stripe_payment_intent_id des tables
-- inscriptions et bons_cadeaux sont conservées pour l'historique.
CREATE TABLE IF NOT EXISTS paiements_helloasso (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  checkout_id   BIGINT UNSIGNED NOT NULL,
  type          VARCHAR(20)     NOT NULL,
  cible_id      INT UNSIGNED    NOT NULL,
  montant_cents INT UNSIGNED    NOT NULL,
  statut        VARCHAR(20)     NOT NULL DEFAULT 'cree',
  order_id      BIGINT UNSIGNED NULL,
  cree_le       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paye_le       DATETIME        NULL,
  UNIQUE KEY uniq_checkout (checkout_id),
  KEY idx_cible (type, cible_id, statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
