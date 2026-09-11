CREATE TABLE IF NOT EXISTS satisfaction_survey_responses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  csat TINYINT UNSIGNED NOT NULL,
  nps TINYINT UNSIGNED NOT NULL,
  routine_score TINYINT UNSIGNED NOT NULL,
  most_used_feature VARCHAR(40) NOT NULL,
  feedback TEXT NULL,
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_satisfaction_survey_user (user_id),
  CONSTRAINT fk_satisfaction_survey_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
