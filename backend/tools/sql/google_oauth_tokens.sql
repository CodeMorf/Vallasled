CREATE TABLE IF NOT EXISTS `google_oauth_tokens` (
  `user_id` INT UNSIGNED NOT NULL,
  `access_token` TEXT NOT NULL,
  `refresh_token` TEXT,
  `token_type` VARCHAR(20) DEFAULT 'Bearer',
  `expires_at` INT UNSIGNED NOT NULL, -- epoch seconds
  `scope` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
