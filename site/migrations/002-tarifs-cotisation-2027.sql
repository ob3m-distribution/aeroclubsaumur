-- Fiche d'inscription 2027 (version 08-10/2026) : les nouveaux tarifs de
-- cotisation sont les valeurs par défaut du code (inc/tarifs.php). On
-- retire d'éventuelles valeurs saisies au B.O. pour l'ancienne grille,
-- afin que les prix 2027 s'appliquent. Ils restent modifiables ensuite
-- dans « Tarifs et prix ».
DELETE FROM parametres WHERE cle LIKE 'tarif.cotis.%';
