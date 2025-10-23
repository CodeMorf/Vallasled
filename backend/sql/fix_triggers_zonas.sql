-- fix_triggers_zonas.sql
USE vallasled;

-- 1:1 en pivote (si ya existe verás 1061, ignóralo)
ALTER TABLE vallas_zonas ADD UNIQUE KEY ux_vz_valla (valla_id);

-- Elimina triggers viejos y duplicados
DROP TRIGGER IF EXISTS trg_vz_ai_sync_vallas;
DROP TRIGGER IF EXISTS trg_vz_au_sync_vallas;
DROP TRIGGER IF EXISTS trg_vz_ad_sync_vallas;
DROP TRIGGER IF EXISTS trg_vallas_ai_sync_zona_pivot;
DROP TRIGGER IF EXISTS trg_vallas_au_sync_zona_pivot;

-- Limpia y recrea los “seguros”
DROP TRIGGER IF EXISTS vz_ai_sync;
DROP TRIGGER IF EXISTS vz_au_sync;
DROP TRIGGER IF EXISTS vz_ad_sync;
DROP TRIGGER IF EXISTS vallas_ai_sync_zp;
DROP TRIGGER IF EXISTS vallas_au_sync_zp;
DROP TRIGGER IF EXISTS trg_zonas_au_propagate_nombre;

DELIMITER $$

-- Pivote → refleja texto en vallas
CREATE TRIGGER vz_ai_sync
AFTER INSERT ON vallas_zonas
FOR EACH ROW trg: BEGIN
  IF COALESCE(@skip_vz_trg,0)=1 THEN LEAVE trg; END IF;
  UPDATE vallas v
  JOIN zonas z ON z.id = NEW.zona_id
  SET v.zona = z.nombre
  WHERE v.id = NEW.valla_id;
END$$

CREATE TRIGGER vz_au_sync
AFTER UPDATE ON vallas_zonas
FOR EACH ROW trg: BEGIN
  IF COALESCE(@skip_vz_trg,0)=1 THEN LEAVE trg; END IF;
  UPDATE vallas v
  JOIN zonas z ON z.id = NEW.zona_id
  SET v.zona = z.nombre
  WHERE v.id = NEW.valla_id;
END$$

CREATE TRIGGER vz_ad_sync
AFTER DELETE ON vallas_zonas
FOR EACH ROW trg: BEGIN
  IF COALESCE(@skip_vz_trg,0)=1 THEN LEAVE trg; END IF;
  UPDATE vallas SET zona = '' WHERE id = OLD.valla_id;
END$$

-- Editar vallas.zona → asegura pivote
CREATE TRIGGER vallas_ai_sync_zp
AFTER INSERT ON vallas
FOR EACH ROW trg: BEGIN
  IF COALESCE(@skip_vz_trg,0)=1 THEN LEAVE trg; END IF;
  IF NEW.zona IS NULL OR TRIM(NEW.zona)='' THEN LEAVE trg; END IF;
  SET @zn := TRIM(NEW.zona);
  SET @zona_id := (SELECT id FROM zonas WHERE nombre=@zn LIMIT 1);
  IF @zona_id IS NULL THEN
    INSERT INTO zonas(nombre,activo) VALUES (@zn,1);
    SET @zona_id := LAST_INSERT_ID();
  END IF;
  SET @skip_vz_trg := 1;
  INSERT INTO vallas_zonas(valla_id,zona_id)
  VALUES (NEW.id,@zona_id)
  ON DUPLICATE KEY UPDATE zona_id=VALUES(zona_id);
  SET @skip_vz_trg := 0;
END$$

CREATE TRIGGER vallas_au_sync_zp
AFTER UPDATE ON vallas
FOR EACH ROW trg: BEGIN
  IF COALESCE(@skip_vz_trg,0)=1 THEN LEAVE trg; END IF;
  IF (NEW.zona<=>OLD.zona) OR NEW.zona IS NULL OR TRIM(NEW.zona)='' THEN LEAVE trg; END IF;
  SET @zn := TRIM(NEW.zona);
  SET @zona_id := (SELECT id FROM zonas WHERE nombre=@zn LIMIT 1);
  IF @zona_id IS NULL THEN
    INSERT INTO zonas(nombre,activo) VALUES (@zn,1);
    SET @zona_id := LAST_INSERT_ID();
  END IF;
  SET @skip_vz_trg := 1;
  INSERT INTO vallas_zonas(valla_id,zona_id)
  VALUES (NEW.id,@zona_id)
  ON DUPLICATE KEY UPDATE zona_id=VALUES(zona_id);
  SET @skip_vz_trg := 0;
END$$

-- Renombrar zona → propaga a vallas.zona
CREATE TRIGGER trg_zonas_au_propagate_nombre
AFTER UPDATE ON zonas
FOR EACH ROW BEGIN
  IF (NEW.nombre <=> OLD.nombre) THEN LEAVE BEGIN; END IF;
  UPDATE vallas v
  JOIN vallas_zonas vz ON vz.valla_id = v.id
  SET v.zona = NEW.nombre
  WHERE vz.zona_id = NEW.id;
END$$

DELIMITER ;

-- Verificación opcional
-- SHOW TRIGGERS WHERE `Table` IN ('vallas_zonas','vallas','zonas');
