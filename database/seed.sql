-- Donnees de demo. Les mots de passe en clair ci-dessous sont uniquement pour
-- le seed de dev ; ce sont bien des hash bcrypt qui sont stockes en base,
-- voir README pour la correspondance identifiant/mot de passe.

INSERT INTO users (nom, prenom, email, password_hash, role, poste) VALUES
('Admin', 'Sophie', 'admin@sav.local', '$2y$12$6CnqfspGSGyshmBkyhX9E.J4m/3PZLOQrPaeNMSY8/8USjnLy53Fy', 'admin', NULL),
('Diallo', 'Karim', 'karim.diallo@sav.local', '$2y$12$6CnqfspGSGyshmBkyhX9E.J4m/3PZLOQrPaeNMSY8/8USjnLy53Fy', 'technicien', 'sav'),
('Nguyen', 'Lea', 'lea.nguyen@sav.local', '$2y$12$6CnqfspGSGyshmBkyhX9E.J4m/3PZLOQrPaeNMSY8/8USjnLy53Fy', 'technicien', 'hotline');

INSERT INTO clients (nom, prenom, email, telephone, adresse_rue, adresse_ville, adresse_code_postal) VALUES
('Dupont', 'Jean', 'jean.dupont@example.com', '0601020304', 'Rue de Rivoli', 'Paris', '75001'),
('Dubois', 'Marie', 'marie.dubois@example.com', '0602030405', 'Rue de la Republique', 'Lyon', '69001'),
('Martin', 'Pierre', 'pierre.martin@example.com', '0603040506', 'Rue Sainte-Catherine', 'Bordeaux', '33000');

INSERT INTO articles (reference, designation, prix, stock_quantite) VALUES
('PGL-001', 'Pergola bioclimatique', 1200.00, 8),
('PRT-002', 'Portail de jardin aluminium', 500.00, 15),
('TNL-003', 'Tonnelle de jardin', 600.00, 10),
('CLT-004', 'Cloture composite', 400.00, 20),
('ABJ-005', 'Abri de jardin bois', 1000.00, 5);

INSERT INTO commandes (client_id, date_commande) VALUES
(1, '2026-08-14'),
(2, '2026-08-20'),
(3, '2026-09-01');

INSERT INTO commande_articles (commande_id, article_id, quantite) VALUES
(1, 1, 1),
(1, 4, 2),
(2, 2, 1),
(3, 5, 1),
(3, 3, 1);

INSERT INTO tickets (code, commande_id, technicien_id, type, statut, description) VALUES
('TCK-0001', 1, 2, 'reparation', 'en_cours', 'Moteur de la pergola bloque en position ouverte.'),
('TCK-0002', 2, NULL, 'expedition', 'nouveau', 'Portail livre avec une charniere manquante.'),
('TCK-0003', 3, 3, 'hotline', 'termine', 'Question sur le montage de la tonnelle, resolue par telephone.');
