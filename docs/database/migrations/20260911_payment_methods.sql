CREATE TABLE IF NOT EXISTS payment_methods (
 id INT NOT NULL AUTO_INCREMENT, home_id INT NOT NULL, type VARCHAR(30) NOT NULL, name VARCHAR(100) NOT NULL,
 last4 CHAR(4) NULL, issuer VARCHAR(100) NULL, is_default TINYINT(1) NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id), UNIQUE KEY uq_pm_home_name(home_id,name), KEY idx_pm_home_active(home_id,is_active,sort_order),
 CONSTRAINT fk_pm_home FOREIGN KEY(home_id) REFERENCES homes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE transactions ADD payment_method_id INT NULL AFTER user_id','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transactions' AND COLUMN_NAME='payment_method_id'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE transactions ADD KEY idx_tx_pm(payment_method_id)','SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transactions' AND INDEX_NAME='idx_tx_pm'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE transactions ADD CONSTRAINT fk_tx_pm FOREIGN KEY(payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL','SELECT 1') FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='transactions' AND CONSTRAINT_NAME='fk_tx_pm'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE recurring_transactions ADD payment_method_id INT NULL AFTER user_id','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='recurring_transactions' AND COLUMN_NAME='payment_method_id'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE recurring_transactions ADD KEY idx_rec_pm(payment_method_id)','SELECT 1') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='recurring_transactions' AND INDEX_NAME='idx_rec_pm'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=(SELECT IF(COUNT(*)=0,'ALTER TABLE recurring_transactions ADD CONSTRAINT fk_rec_pm FOREIGN KEY(payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL','SELECT 1') FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='recurring_transactions' AND CONSTRAINT_NAME='fk_rec_pm'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
INSERT INTO payment_methods(home_id,type,name,is_default,sort_order)
SELECT h.id,'bank_transfer','בנק',1,0 FROM homes h WHERE NOT EXISTS(SELECT 1 FROM payment_methods pm WHERE pm.home_id=h.id);
