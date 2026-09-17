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
