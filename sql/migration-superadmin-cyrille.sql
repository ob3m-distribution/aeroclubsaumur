-- ==================================================================
--  Compte superadmin pour Cyrille Lepicier (demande du 23/09/2026).
--
--  Le mot de passe inseré ici est un hash bcrypt d'une valeur aleatoire
--  jamais communiquee (meme motif que la conversion demande -> membre
--  dans admin/adherent.php) : personne ne le connait, y compris Claude.
--  Cyrille doit ensuite passer par "Mot de passe oublie" sur le site
--  (/mot-de-passe-oublie) avec ob3m.distribution@gmail.com pour choisir
--  son propre mot de passe -- ca envoie un vrai email via le circuit de
--  production (email_lien_mot_de_passe), pas un mot de passe en clair
--  qui aurait transite par le chat ou un fichier versionne.
--
--  Idempotent (ON DUPLICATE KEY UPDATE sur l'email, colonne UNIQUE) :
--  si le compte existe deja, ce script se contente de le passer/laisser
--  en role='superadmin' actif, sans ecraser un mot de passe deja choisi.
--
--  A executer directement sur la base de PRODUCTION (celle que
--  config-local.php de la prod pointe -- voir deploy_ci.py) : ni ce
--  sandbox Claude Code ni le depot Git n'ont de connexion a cette base.
-- ==================================================================

INSERT INTO membres (prenom, nom, email, mot_de_passe_hash, role, actif)
VALUES (
  'Cyrille', 'Lepicier', 'ob3m.distribution@gmail.com',
  '$2y$12$xTGQYd66nTXnoB57kugj5eEiOyBB7ZVNp9cKViGWvTgnJmYwj/OAa',
  'superadmin', 1
)
ON DUPLICATE KEY UPDATE role = 'superadmin', actif = 1;
