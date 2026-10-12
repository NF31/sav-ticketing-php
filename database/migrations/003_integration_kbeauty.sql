-- Integration avec kbeauty-shop (demandes de retour recues via RabbitMQ).
-- Rejouable sans erreur.

-- Nouveau type de ticket, cree uniquement par l'import (pas depuis le
-- formulaire).
ALTER TABLE tickets MODIFY type ENUM('reparation', 'expedition', 'hotline', 'retour') NOT NULL;

-- Identifiant de la demande de retour cote boutique : UNIQUE, un meme
-- message recu deux fois (livraison "au moins une fois") ne cree qu'un ticket.
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS retour_kbeauty_id INT UNSIGNED NULL UNIQUE AFTER description;

-- Numero de commande cote boutique : retrouve la commande d'un second retour
-- sur la meme commande au lieu d'en creer une copie.
ALTER TABLE commandes ADD COLUMN IF NOT EXISTS reference_externe VARCHAR(50) NULL UNIQUE AFTER date_commande;
