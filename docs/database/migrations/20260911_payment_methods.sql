CREATE TABLE payment_methods (
 id INT NOT NULL AUTO_INCREMENT, home_id INT NOT NULL, type VARCHAR(30) NOT NULL, name VARCHAR(100) NOT NULL,
 last4 CHAR(4) NULL, issuer VARCHAR(100) NULL, is_default TINYINT(1) NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), UNIQUE KEY uq_pm_home_name(home_id,name), KEY idx_pm_home_active(home_id,is_active,sort_order),
 CONSTRAINT fk_pm_home FOREIGN KEY(home_id) REFERENCES homes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE transactions ADD payment_method_id INT NULL AFTER user_id, ADD KEY idx_tx_pm(payment_method_id), ADD CONSTRAINT fk_tx_pm FOREIGN KEY(payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL;
ALTER TABLE recurring_transactions ADD payment_method_id INT NULL AFTER user_id, ADD KEY idx_rec_pm(payment_method_id), ADD CONSTRAINT fk_rec_pm FOREIGN KEY(payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL;
INSERT INTO payment_methods(home_id,type,name,is_default,sort_order)
SELECT id,'bank_transfer','בנק',1,0 FROM homes;
