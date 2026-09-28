-- ==================================================================
--  Rôles cumulables + affectation des membres (demande du 28/09/2026).
--
--  5 rôles, un membre peut en avoir plusieurs :
--    Adhérents       : bibliothèque en lecture, sauf DTO et
--                      Conseils d’administration
--    Administrateurs : bibliothèque en lecture, sauf DTO
--    Instructeurs    : toute la bibliothèque en lecture
--    Bureau          : toute la bibliothèque, lecture et modification
--    Bons cadeaux    : /admin/bons.php et sa gestion
--  Les super administrateurs gardent tous leurs accès (colonne
--  membres.role, non modifiée ici).
--
--  A executer dans phpMyAdmin sur la base de PRODUCTION (puis, si
--  souhaite, sur celle de developpement). Selectionner la base dans la
--  colonne de gauche AVANT d'ouvrir l'onglet SQL.
--
--  Rejouable sans risque : les affectations sont ajoutees, jamais
--  dupliquees. Les deux tableaux affiches a la fin permettent de
--  verifier le resultat.
-- ==================================================================

SET NAMES utf8mb4;

-- 1. Table des rôles cumulables --------------------------------------
CREATE TABLE IF NOT EXISTS membre_roles (
  membre_id INT UNSIGNED NOT NULL,
  role      VARCHAR(20)  NOT NULL,
  PRIMARY KEY (membre_id, role),
  KEY idx_role (role),
  CONSTRAINT fk_membre_roles_membre FOREIGN KEY (membre_id)
    REFERENCES membres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Affectations nominatives ----------------------------------------
--    (comparaison insensible aux majuscules et aux accents)
DROP TEMPORARY TABLE IF EXISTS tmp_affectations;
CREATE TEMPORARY TABLE tmp_affectations (
  role   VARCHAR(20) NOT NULL,
  prenom VARCHAR(80) NOT NULL,
  nom    VARCHAR(80) NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_affectations (role, prenom, nom) VALUES
  ('administrateur', 'Sébastien',       'Lacourt'),
  ('administrateur', 'Jocelyn',         'Foucher'),
  ('administrateur', 'Jean-Christophe', 'Lafilay'),
  ('administrateur', 'Christian',       'Lepicier'),
  ('administrateur', 'Jonathan',        'Robert'),
  ('administrateur', 'Clotilde',        'Nardin'),
  ('administrateur', 'Romain',          'Perru'),
  ('administrateur', 'Pascal',          'Martin'),
  ('administrateur', 'Maxime',          'Malagu'),

  ('instructeur',    'Jean-Christophe', 'Lafilay'),
  ('instructeur',    'Yves',            'Couffon'),
  ('instructeur',    'Benjamin',        'Tortorici'),
  ('instructeur',    'Maxime',          'Malagu'),
  ('instructeur',    'Jonathan',        'Robert'),
  ('instructeur',    'Patrick',         'Lebian'),

  ('bons_cadeaux',   'Christian',       'Lepicier'),
  ('bons_cadeaux',   'Jean-Christophe', 'Lafilay'),
  ('bons_cadeaux',   'Yves',            'Couffon'),
  ('bons_cadeaux',   'Benjamin',        'Tortorici'),
  ('bons_cadeaux',   'Maxime',          'Malagu'),
  ('bons_cadeaux',   'Jonathan',        'Robert'),
  ('bons_cadeaux',   'Valentin',        'Vasseur'),
  ('bons_cadeaux',   'Philippe',        'Berthon'),

  ('bureau',         'Jonathan',        'Robert'),
  ('bureau',         'Christian',       'Lepicier'),
  ('bureau',         'Jean-Christophe', 'Lafilay');

INSERT IGNORE INTO membre_roles (membre_id, role)
SELECT m.id, a.role
  FROM tmp_affectations a
  JOIN membres m ON m.prenom = a.prenom AND m.nom = a.nom;

-- 3. Adhérents : tous les autres membres -----------------------------
INSERT IGNORE INTO membre_roles (membre_id, role)
SELECT m.id, 'adherent'
  FROM membres m
 WHERE NOT EXISTS (SELECT 1 FROM membre_roles r WHERE r.membre_id = m.id);

-- 4. Dossier « Conseils d’administration » ----------------------------
--    Il n'existe pas encore dans la bibliothèque : on le crée (vide) en
--    dernière position, pour que les accès ci-dessous le couvrent. Le
--    bureau pourra ensuite y déposer les documents.
INSERT INTO biblio_sections (parent_id, nom, position)
SELECT NULL, 'Conseils d’administration', x.suivante
  FROM (SELECT COALESCE(MAX(position), -1) + 1 AS suivante
          FROM biblio_sections WHERE parent_id IS NULL) x
 WHERE NOT EXISTS (SELECT 1 FROM biblio_sections c
                    WHERE c.parent_id IS NULL AND c.nom LIKE 'Conseil%administration%');

-- 5. Dossiers visibles par rôle ---------------------------------------
--    (Instructeurs et Bureau voient tout : réglé dans le code.)
DELETE FROM role_dossiers WHERE role IN ('adherent', 'administrateur');

INSERT INTO role_dossiers (role, dossier)
SELECT 'adherent', CAST(s.id AS CHAR)
  FROM biblio_sections s
 WHERE s.parent_id IS NULL
   AND s.nom <> 'DTO'
   AND s.nom NOT LIKE 'Conseil%administration%';

INSERT INTO role_dossiers (role, dossier)
SELECT 'administrateur', CAST(s.id AS CHAR)
  FROM biblio_sections s
 WHERE s.parent_id IS NULL
   AND s.nom <> 'DTO';

-- 6. Réglages individuels ---------------------------------------------
--    Les éventuels réglages « par membre » remplaçaient ceux du rôle : on
--    les efface pour que les nouvelles règles s'appliquent à tout le monde.
DELETE FROM membre_dossiers;

-- 7. Vérification ------------------------------------------------------
--    Un tableau : une ligne par rôle, et une ligne « INTROUVABLES » si un
--    nom de la liste ne correspond à aucun membre (à corriger à la main).
SET SESSION group_concat_max_len = 20000;

SELECT r.role, COUNT(*) AS nb,
       GROUP_CONCAT(CONCAT(m.prenom, ' ', m.nom) ORDER BY m.nom SEPARATOR ', ') AS membres
  FROM membre_roles r JOIN membres m ON m.id = r.membre_id
 GROUP BY r.role
UNION ALL
SELECT 'INTROUVABLES', COUNT(*),
       GROUP_CONCAT(CONCAT(a.prenom, ' ', a.nom, ' (', a.role, ')') SEPARATOR ', ')
  FROM tmp_affectations a
  LEFT JOIN membres m ON m.prenom = a.prenom AND m.nom = a.nom
 WHERE m.id IS NULL
HAVING COUNT(*) > 0;
