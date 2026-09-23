-- ==================================================================
--  Reinscriptions, bibliotheque adherents, tickets de support, mailings
--  Reconstruit a partir de l'usage reel dans le code (23/09/2026) :
--  ces tables n'avaient jamais ete versionnees dans un fichier .sql,
--  contrairement au reste du schema.
-- ==================================================================

CREATE TABLE IF NOT EXISTS inscriptions (
  id                        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  membre_id                 INT UNSIGNED NULL,
  annee                     SMALLINT UNSIGNED NOT NULL,
  type                      ENUM('renouvellement','demande') NOT NULL DEFAULT 'renouvellement',
  statut                    ENUM('brouillon','complet','paye','valide') NOT NULL DEFAULT 'brouillon',

  nom                       VARCHAR(80)  NOT NULL DEFAULT '',
  prenom                    VARCHAR(80)  NOT NULL DEFAULT '',
  nationalite               VARCHAR(80)  NOT NULL DEFAULT '',
  date_naissance            DATE         NULL,
  lieu_naissance            VARCHAR(120) NOT NULL DEFAULT '',
  profession                VARCHAR(120) NOT NULL DEFAULT '',
  adresse                   VARCHAR(255) NOT NULL DEFAULT '',
  tel_perso                 VARCHAR(30)  NOT NULL DEFAULT '',
  tel_pro                   VARCHAR(30)  NOT NULL DEFAULT '',
  tel_mobile                VARCHAR(30)  NOT NULL DEFAULT '',
  courriel                  VARCHAR(180) NOT NULL DEFAULT '',
  urgence                   VARCHAR(255) NOT NULL DEFAULT '',

  lapl_num                  VARCHAR(60)  NOT NULL DEFAULT '',
  lapl_date                 DATE         NULL,
  ppl_num                   VARCHAR(60)  NOT NULL DEFAULT '',
  ppl_date                  DATE         NULL,
  validite_licence          DATE         NULL,
  validite_sep              DATE         NULL,
  autres_qualifs            VARCHAR(255) NOT NULL DEFAULT '',
  validite_visite_medicale  DATE         NULL,
  num_ffa                   VARCHAR(30)  NOT NULL DEFAULT '',

  pere_nom                  VARCHAR(120) NOT NULL DEFAULT '',
  mere_nom                  VARCHAR(120) NOT NULL DEFAULT '',
  autorisation_parentale    TINYINT(1)   NOT NULL DEFAULT 0,

  option_cotisation         VARCHAR(20)  NOT NULL DEFAULT '',
  passeport_bloc            VARCHAR(20)  NOT NULL DEFAULT '',
  extras                    VARCHAR(255) NOT NULL DEFAULT '',
  convocation_ag            ENUM('courriel','courrier') NOT NULL DEFAULT 'courriel',
  rgpd_accepte              TINYINT(1)   NOT NULL DEFAULT 0,
  total_cents               INT UNSIGNED NOT NULL DEFAULT 0,

  licence_fichier           VARCHAR(255) NULL,
  visite_medicale_fichier   VARCHAR(255) NULL,
  photo                     VARCHAR(255) NULL,
  profil                    TEXT         NULL,

  stripe_payment_intent_id  VARCHAR(120) NULL,
  mode_paiement             VARCHAR(20)  NULL,
  paye_le                   DATETIME     NULL,

  rencontre_ok              TINYINT(1)   NOT NULL DEFAULT 0,
  rencontre_ok_le           DATETIME     NULL,
  rencontre_ok_par          INT UNSIGNED NULL,
  licence_ok                TINYINT(1)   NOT NULL DEFAULT 0,
  licence_ok_le             DATETIME     NULL,
  licence_ok_par            INT UNSIGNED NULL,
  medicale_ok               TINYINT(1)   NOT NULL DEFAULT 0,
  medicale_ok_le            DATETIME     NULL,
  medicale_ok_par           INT UNSIGNED NULL,
  cotisation_ok             TINYINT(1)   NOT NULL DEFAULT 0,
  cotisation_ok_le          DATETIME     NULL,
  cotisation_ok_par         INT UNSIGNED NULL,

  valide_le                 DATETIME     NULL,
  valide_par                INT UNSIGNED NULL,

  cree_le                   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modifie_le                DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uniq_membre_annee (membre_id, annee),
  UNIQUE KEY uniq_stripe_pi (stripe_payment_intent_id),
  KEY idx_statut (statut),
  KEY idx_annee (annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inscription_documents (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inscription_id INT UNSIGNED NOT NULL,
  nom            VARCHAR(150) NOT NULL,
  fichier        VARCHAR(255) NOT NULL,
  cree_le        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inscription (inscription_id),
  CONSTRAINT fk_inscription_doc FOREIGN KEY (inscription_id)
    REFERENCES inscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS biblio_sections (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  nom       VARCHAR(160) NOT NULL,
  position  INT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_parent (parent_id),
  CONSTRAINT fk_biblio_section_parent FOREIGN KEY (parent_id)
    REFERENCES biblio_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS biblio_documents (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_id INT UNSIGNED NOT NULL,
  nom        VARCHAR(160) NOT NULL,
  fichier    VARCHAR(255) NOT NULL,
  type       VARCHAR(20)  NOT NULL,
  taille     INT UNSIGNED NOT NULL DEFAULT 0,
  position   INT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_section (section_id),
  CONSTRAINT fk_biblio_doc_section FOREIGN KEY (section_id)
    REFERENCES biblio_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS membre_dossiers (
  membre_id INT UNSIGNED NOT NULL,
  dossier   VARCHAR(20) NOT NULL,
  PRIMARY KEY (membre_id, dossier),
  CONSTRAINT fk_membre_dossiers_membre FOREIGN KEY (membre_id)
    REFERENCES membres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_dossiers (
  role    VARCHAR(20) NOT NULL,
  dossier VARCHAR(20) NOT NULL,
  PRIMARY KEY (role, dossier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tickets (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  membre_id     INT UNSIGNED NOT NULL,
  membre_nom    VARCHAR(160) NOT NULL,
  membre_email  VARCHAR(180) NOT NULL,
  sujet         VARCHAR(180) NOT NULL,
  priorite      ENUM('basse','normale','haute') NOT NULL DEFAULT 'normale',
  statut        ENUM('ouvert','en_cours','resolu') NOT NULL DEFAULT 'ouvert',
  message       TEXT NOT NULL,
  nb_pieces     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  cree_le       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_membre (membre_id),
  KEY idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_reponses (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id  INT UNSIGNED NOT NULL,
  auteur_id  INT UNSIGNED NOT NULL,
  auteur_nom VARCHAR(160) NOT NULL,
  cote       ENUM('bureau','membre') NOT NULL,
  corps      TEXT NOT NULL,
  cree_le    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ticket (ticket_id),
  CONSTRAINT fk_ticket_reponse FOREIGN KEY (ticket_id)
    REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mailings (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sujet             VARCHAR(180) NOT NULL,
  corps             TEXT NOT NULL,
  filtre_role       VARCHAR(30) NOT NULL DEFAULT '',
  filtre_adhesion   VARCHAR(30) NOT NULL DEFAULT '',
  filtre_paiement   VARCHAR(30) NOT NULL DEFAULT '',
  nb_destinataires  INT UNSIGNED NOT NULL DEFAULT 0,
  nb_envoyes        INT UNSIGNED NOT NULL DEFAULT 0,
  envoye_par        INT UNSIGNED NULL,
  cree_le           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
