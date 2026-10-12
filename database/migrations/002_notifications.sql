-- Pour une base creee avant l'ajout des notifications (une base neuve a
-- deja la table via schema.sql). Rejouable sans erreur.
CREATE TABLE IF NOT EXISTS notifications (
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
