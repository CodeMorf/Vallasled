<?php
/**
 * /tools/export_ddl.php
 * Lista todas tus bases de datos y arma un dump DDL con tablas, vistas, triggers, rutinas y events.
 * Usa tu /config/db.php. Copia/pega el resultado por esquema.
 *
 * Filtros opcionales:
 *   ?db=mi_db[,otra_db]    Limita a uno o varios esquemas.
 *   ?skip=info             Oculta cabeceras y metadatos.
 */
declare(strict_types=1);

ini_set('display_errors', '0');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// include seguro
$DOCROOT = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 1), '/');
require_once $DOCROOT . '/config/db.php';
if (function_exists('require_console_auth')) require_console_auth(['admin','staff']);

header('Content-Type: text/html; charset=utf-8');

$conn->set_charset('utf8mb4');
$conn->query("SET SESSION sql_quote_show_create=1");
try { $conn->query("SET SESSION information_schema_stats_expiry=0"); } catch (Throwable $e) {}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function qcol(mysqli $c, string $sql, array $p = []): array {
  $st = $c->prepare($sql);
  if ($p) $st->bind_param(str_repeat('s', count($p)), ...$p);
  $st->execute();
  $r = $st->get_result();
  $out = [];
  while ($row = $r->fetch_row()) $out[] = (string)$row[0];
  return $out;
}
function show_create(mysqli $c, string $sql): ?array {
  try {
    $r = $c->query($sql);
    return $r->fetch_assoc() ?: null;
  } catch (Throwable $e) {
    return null;
  }
}
function linesep(string $label): string {
  return "\n-- ------------------------------\n-- {$label}\n-- ------------------------------\n";
}

$filter = array_filter(array_map('trim', explode(',', (string)($_GET['db'] ?? ''))));
$skipInfo = isset($_GET['skip']);

$system = ['information_schema','mysql','performance_schema','sys'];
$schemas = $filter ?: qcol(
  $conn,
  "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA ORDER BY SCHEMA_NAME"
);
$schemas = array_values(array_diff($schemas, $system));

$now = date('Y-m-d H:i:s');

echo '<!doctype html><meta charset="utf-8"><title>DDL Export</title>';
echo '<style>body{font-family:ui-monospace,Consolas,monospace;padding:12px} .wrap{white-space:pre-wrap;border:1px solid #ddd;border-radius:8px;padding:12px;background:#fafafa} textarea{width:100%;height:420px} .card{margin:18px 0;padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff} .small{color:#6b7280;font-size:12px} .btn{padding:6px 10px;border:1px solid #111;border-radius:6px;background:#111;color:#fff;cursor:pointer} .row{display:flex;gap:10px;align-items:center;justify-content:space-between;}</style>';
echo '<h2>Export DDL</h2>';

foreach ($schemas as $db) {
  if ($db === '') continue;

  // Validar que existe
  $exists = qcol($conn, "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME=? LIMIT 1", [$db]);
  if (!$exists) continue;

  // Tablas y vistas
  $tables = qcol($conn,
    "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME", [$db]
  );
  $views  = qcol($conn,
    "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='VIEW' ORDER BY TABLE_NAME", [$db]
  );
  $trigs  = qcol($conn,
    "SELECT TRIGGER_NAME FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA=? ORDER BY TRIGGER_NAME", [$db]
  );
  $rprocs = qcol($conn,
    "SELECT ROUTINE_NAME FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA=? AND ROUTINE_TYPE='PROCEDURE' ORDER BY ROUTINE_NAME", [$db]
  );
  $rfuncs = qcol($conn,
    "SELECT ROUTINE_NAME FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA=? AND ROUTINE_TYPE='FUNCTION' ORDER BY ROUTINE_NAME", [$db]
  );
  $events = qcol($conn,
    "SELECT EVENT_NAME FROM INFORMATION_SCHEMA.EVENTS WHERE EVENT_SCHEMA=? ORDER BY EVENT_NAME", [$db]
  );

  // Construir dump
  $buf = [];

  if (!$skipInfo) {
    $buf[] = "-- Dump DDL generado: {$now}";
    $buf[] = "-- Base de datos: `{$db}`";
  }

  // CREATE DATABASE
  $cdc = show_create($conn, "SHOW CREATE DATABASE `{$db}`");
  if ($cdc) {
    $createDb = $cdc['Create Database'] ?? $cdc['Create Database'] ?? null;
    if ($createDb) {
      $buf[] = linesep("CREATE DATABASE");
      $buf[] = $createDb . ';';
    }
  }
  $buf[] = "USE `{$db}`;";

  // Tablas
  if ($tables) {
    $buf[] = linesep("TABLES (" . count($tables) . ")");
    foreach ($tables as $t) {
      $row = show_create($conn, "SHOW CREATE TABLE `{$db}`.`{$t}`");
      $sql = $row['Create Table'] ?? null;
      if ($sql) {
        $buf[] = $sql . ';';
      } else {
        $buf[] = "-- ERROR al obtener CREATE TABLE `{$t}`";
      }
    }
  }

  // Vistas
  if ($views) {
    $buf[] = linesep("VIEWS (" . count($views) . ")");
    foreach ($views as $v) {
      $row = show_create($conn, "SHOW CREATE VIEW `{$db}`.`{$v}`");
      $sql = $row['Create View'] ?? null;
      if ($sql) {
        $buf[] = $sql . ';';
      } else {
        $buf[] = "-- ERROR al obtener CREATE VIEW `{$v}`";
      }
    }
  }

  // Triggers
  if ($trigs) {
    $buf[] = linesep("TRIGGERS (" . count($trigs) . ")");
    $buf[] = "DELIMITER ;;";
    foreach ($trigs as $tg) {
      $row = show_create($conn, "SHOW CREATE TRIGGER `{$db}`.`{$tg}`");
      $sql = $row['SQL Original Statement'] ?? $row['Create Trigger'] ?? null;
      if ($sql) {
        // Normalizar: asegurar DEFINER y terminador doble
        $buf[] = $sql . ";;";
      } else {
        $buf[] = "-- ERROR al obtener CREATE TRIGGER `{$tg}`";
      }
    }
    $buf[] = "DELIMITER ;";
  }

  // Rutinas: PROCEDURES
  if ($rprocs) {
    $buf[] = linesep("PROCEDURES (" . count($rprocs) . ")");
    $buf[] = "DELIMITER ;;";
    foreach ($rprocs as $rp) {
      $row = show_create($conn, "SHOW CREATE PROCEDURE `{$db}`.`{$rp}`");
      $sql = $row['Create Procedure'] ?? null;
      if ($sql) {
        $buf[] = $sql . ";;";
      } else {
        $buf[] = "-- ERROR al obtener CREATE PROCEDURE `{$rp}`";
      }
    }
    $buf[] = "DELIMITER ;";
  }

  // Rutinas: FUNCTIONS
  if ($rfuncs) {
    $buf[] = linesep("FUNCTIONS (" . count($rfuncs) . ")");
    $buf[] = "DELIMITER ;;";
    foreach ($rfuncs as $rf) {
      $row = show_create($conn, "SHOW CREATE FUNCTION `{$db}`.`{$rf}`");
      $sql = $row['Create Function'] ?? null;
      if ($sql) {
        $buf[] = $sql . ";;";
      } else {
        $buf[] = "-- ERROR al obtener CREATE FUNCTION `{$rf}`";
      }
    }
    $buf[] = "DELIMITER ;";
  }

  // Events
  if ($events) {
    $buf[] = linesep("EVENTS (" . count($events) . ")");
    $buf[] = "DELIMITER ;;";
    foreach ($events as $ev) {
      $row = show_create($conn, "SHOW CREATE EVENT `{$db}`.`{$ev}`");
      $sql = $row['Create Event'] ?? null;
      if ($sql) {
        $buf[] = $sql . ";;";
      } else {
        $buf[] = "-- ERROR al obtener CREATE EVENT `{$ev}`";
      }
    }
    $buf[] = "DELIMITER ;";
  }

  $ddl = implode("\n", $buf);

  echo '<div class="card">';
  echo '<div class="row"><h3>Esquema: <code>' . h($db) . '</code></h3>';
  echo '<button class="btn" data-copy="tx-' . h($db) . '">Copiar</button></div>';
  echo '<div class="small">Tablas: ' . count($tables) . ' · Vistas: ' . count($views) . ' · Triggers: ' . count($trigs) . ' · Procs: ' . count($rprocs) . ' · Funcs: ' . count($rfuncs) . ' · Events: ' . count($events) . '</div>';
  echo '<textarea id="tx-' . h($db) . '">' . h($ddl) . '</textarea>';
  echo '</div>';
}

?>
<script>
document.querySelectorAll('button[data-copy]').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const id = btn.getAttribute('data-copy');
    const ta = document.getElementById(id);
    ta.select(); ta.setSelectionRange(0, 999999);
    const ok = document.execCommand('copy');
    btn.textContent = ok ? 'Copiado' : 'No copiado';
    setTimeout(()=>btn.textContent='Copiar', 1200);
  });
});
</script>
