-- Optional schema for per-valla SEO keywords and limit enforcement
CREATE TABLE IF NOT EXISTS valla_keywords (
  id INT AUTO_INCREMENT PRIMARY KEY,
  valla_id INT NOT NULL,
  keyword VARCHAR(64) NOT NULL,
  weight TINYINT NOT NULL DEFAULT 5,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vk_valla_keyword (valla_id, keyword),
  KEY idx_vk_valla (valla_id),
  CONSTRAINT fk_vk_valla FOREIGN KEY (valla_id) REFERENCES vallas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DELIMITER $$
CREATE TRIGGER trg_vk_limit_ins BEFORE INSERT ON valla_keywords FOR EACH ROW
BEGIN
  DECLARE cnt INT;
  SELECT COUNT(*) INTO cnt FROM valla_keywords WHERE valla_id = NEW.valla_id;
  IF cnt >= 10 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Máximo 10 keywords por valla';
  END IF;
END$$
DELIMITER ;

-- Suggested config keys:
-- INSERT INTO config_global (clave, valor, activo) VALUES
--  ('site_name','Vallasled.com',1),
--  ('site_title','Vallasled.com - Publicidad Exterior Inteligente',1),
--  ('site_description','Vallas digitales y medios exteriores con disponibilidad en RD.',1),
--  ('theme_primary_color','#0ea5e9',1),
--  ('theme_secondary_color','#111827',1),
--  ('theme_hero_bg','',1),
--  ('media_base_url','https://cdn.vallasled.com',1);
