-- Integration avec kbeauty-customer-mdm (golden record client).
-- Rejouable sans erreur.

-- Identifiant User cote kbeauty-shop, tel que republie dans
-- customer.golden_record.updated. NULL pour les clients crees avant cette
-- integration (via un import de retour) et jamais revus depuis - ils sont
-- retrouves par email a la premiere mise a jour recue, qui renseigne alors
-- cette colonne.
ALTER TABLE clients ADD COLUMN IF NOT EXISTS source_user_id INT UNSIGNED NULL UNIQUE AFTER id;
