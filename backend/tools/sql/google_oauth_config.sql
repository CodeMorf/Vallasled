-- Config global editable desde panel
CREATE TABLE IF NOT EXISTS google_oauth_config (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  client_id VARCHAR(255) NOT NULL DEFAULT '',
  client_secret VARCHAR(255) NOT NULL DEFAULT '',
  redirect_uri VARCHAR(255) NOT NULL DEFAULT '',
  scope TEXT NOT NULL,
  shared_mode TINYINT(1) NOT NULL DEFAULT 0, -- 0: cada usuario autoriza; 1: usar token orgánico si existe
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO google_oauth_config (id, client_id, client_secret, redirect_uri, scope, shared_mode)
VALUES (1,'','','','https://www.googleapis.com/auth/calendar.readonly',0)
ON DUPLICATE KEY UPDATE id=id;
