-- Schema de la base sav_ticketing.
-- Redesign, en solo, du modele du projet de groupe "Filrouge2_sav" :
-- memes entites metier (clients, articles, commandes, tickets SAV, techniciens),
-- mais normalise proprement et sans redondance.

CREATE TABLE clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    telephone VARCHAR(20) NULL,
    adresse_rue VARCHAR(255) NULL,
    adresse_ville VARCHAR(100) NULL,
    adresse_code_postal VARCHAR(10) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(50) NOT NULL UNIQUE,
    designation VARCHAR(255) NOT NULL,
    prix DECIMAL(10, 2) NOT NULL,
    stock_quantite INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Unifie technicien/administrateur du projet d'origine (deux tables
-- quasi identiques dupliquees) en une seule table avec un role.
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'technicien') NOT NULL,
    poste ENUM('sav', 'hotline') NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE commandes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    date_commande DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_commandes_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table de jointure commande <-> article (equivalent de CONCERNE dans l'original).
CREATE TABLE commande_articles (
    commande_id INT UNSIGNED NOT NULL,
    article_id INT UNSIGNED NOT NULL,
    quantite INT NOT NULL,
    PRIMARY KEY (commande_id, article_id),
    CONSTRAINT fk_ca_commande FOREIGN KEY (commande_id) REFERENCES commandes (id) ON DELETE CASCADE,
    CONSTRAINT fk_ca_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    commande_id INT UNSIGNED NOT NULL,
    technicien_id INT UNSIGNED NULL,
    type ENUM('reparation', 'expedition', 'hotline') NOT NULL,
    statut ENUM('nouveau', 'en_cours', 'en_attente_piece', 'expedie', 'termine') NOT NULL DEFAULT 'nouveau',
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Liste des tickets (la plus consultee) : triee par date, filtree ou
    -- non par statut. Sans ces index, chaque page trie toute la table.
    INDEX idx_tickets_date (created_at),
    INDEX idx_tickets_statut_date (statut, created_at),
    CONSTRAINT fk_tickets_commande FOREIGN KEY (commande_id) REFERENCES commandes (id) ON DELETE CASCADE,
    CONSTRAINT fk_tickets_technicien FOREIGN KEY (technicien_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ajout par rapport a l'original : tracabilite des changements de statut,
-- utile pour un vrai suivi SAV (qui a fait quoi, quand).
CREATE TABLE ticket_historique (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    ancien_statut ENUM('nouveau', 'en_cours', 'en_attente_piece', 'expedie', 'termine') NULL,
    nouveau_statut ENUM('nouveau', 'en_cours', 'en_attente_piece', 'expedie', 'termine') NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hist_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notifications dans l'application (cloche de la barre laterale) : un
-- evenement sur un ticket qui concerne quelqu'un d'autre que son auteur
-- (assignation, changement de statut, nouveau ticket a assigner).
-- Message fige a la creation : il decrit l'evenement tel qu'il a eu lieu.
-- L'index sert le compteur de non lues, calcule sur chaque page.
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    ticket_id INT UNSIGNED NOT NULL,
    type ENUM('assignation', 'desassignation', 'statut', 'nouveau_ticket') NOT NULL,
    message VARCHAR(255) NOT NULL,
    lue_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user_lue (user_id, lue_at),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ajout par rapport a l'original (aucune recuperation de mot de passe) :
-- jetons de reinitialisation. Seul le hash SHA-256 du jeton est stocke,
-- jamais le jeton lui-meme : une fuite de la table ne permet pas de
-- reinitialiser un compte. Jeton a usage unique (used_at) et a duree
-- limitee (expires_at).
CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Limitation de debit generique (ex. demandes de reinitialisation de mot
-- de passe) : une ligne par tentative, comptee sur une fenetre glissante.
-- En base plutot que dans un cache (Redis...) faute d'en avoir un dans ce
-- projet ; la cle identifie l'action et le sujet (email hashe, IP).
CREATE TABLE tentatives (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cle VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tentatives_cle_date (cle, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
