-- RIB de l'association (relevé du 29/09/2026, Crédit Agricole de l'Anjou
-- et du Maine, agence Saumur Beaurepaire). Envoyé aux clients pour les
-- paiements par virement (bons cadeaux, cotisations). Modifiable ensuite
-- dans « Tarifs et prix » > Coordonnées bancaires ; INSERT IGNORE : ne
-- remplace pas une valeur déjà saisie au B.O.
INSERT IGNORE INTO parametres (cle, valeur) VALUES
  ('rib.titulaire', 'ASSOC. AERO CLUB DE SAUMUR'),
  ('rib.iban',      'FR76 1790 6000 3296 4233 6204 635'),
  ('rib.bic',       'AGRIFRPP879'),
  ('rib.banque',    'Crédit Agricole de l’Anjou et du Maine — Saumur Beaurepaire');
