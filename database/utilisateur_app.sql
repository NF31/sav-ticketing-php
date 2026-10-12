-- Compte MariaDB utilise par l'application (DB_USER dans .env), a la place
-- de root : il lit et ecrit les donnees, mais ne peut ni modifier le schema
-- (CREATE, DROP, ALTER), ni lire ou ecrire des fichiers du serveur (FILE),
-- ni toucher aux autres bases. Si une injection SQL passait un jour, elle
-- serait limitee aux donnees de l'application.
--
-- root reste reserve a l'installation et aux migrations. Rejouable sans
-- erreur. Mot de passe de developpement : a remplacer en production
-- (ALTER USER 'sav_app'@'%' IDENTIFIED BY '...').
CREATE USER IF NOT EXISTS 'sav_app'@'%' IDENTIFIED BY 'sav_app';
GRANT SELECT, INSERT, UPDATE, DELETE ON sav_ticketing.* TO 'sav_app'@'%';
