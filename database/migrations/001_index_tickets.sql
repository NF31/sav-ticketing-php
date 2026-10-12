-- Pour une base creee avant l'ajout des index de la liste des tickets
-- (une base neuve les a deja via schema.sql). Rejouable sans erreur.
ALTER TABLE tickets
    ADD INDEX IF NOT EXISTS idx_tickets_date (created_at),
    ADD INDEX IF NOT EXISTS idx_tickets_statut_date (statut, created_at);
