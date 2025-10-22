<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../config/mapas.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function json_ok(array $data = []){ echo json_encode(['ok'=>true]+$data, JSON_UNESCAPED_UNICODE); exit; }
function json_err(string $msg, int $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE); exit; }

try {
    if ($action === 'zones') {
        $sql = "SELECT DISTINCT TRIM(zona) AS z FROM vallas WHERE zona IS NOT NULL AND TRIM(zona)<>'' ORDER BY z ASC";
        $res = mysqli_query($conn, $sql);
        $zones = [];
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $z = (string)($r['z'] ?? '');
                if ($z !== '') $zones[] = $z;
            }
        }
        json_ok(['zones'=>$zones]);
    }

    if ($action === 'provincias') {
        // Intenta distintas tablas. Fallback: desde vallas.provincia texto.
        $try = [
            "SELECT id, nombre FROM provincias ORDER BY nombre ASC",
            "SELECT id, nombre FROM provincia ORDER BY nombre ASC",
        ];
        $rows = [];
        foreach ($try as $q) {
            $res = mysqli_query($conn, $q);
            if ($res && mysqli_num_rows($res) > 0) {
                while ($r = mysqli_fetch_assoc($res)) {
                    $rows[] = ['id'=>(string)$r['id'], 'nombre'=>(string)$r['nombre']];
                }
                break;
            }
        }
        if (!$rows) {
            $res = mysqli_query($conn, "SELECT DISTINCT TRIM(provincia) AS nombre FROM vallas WHERE provincia IS NOT NULL AND TRIM(provincia)<>'' ORDER BY nombre ASC");
            if ($res) {
                $i=1;
                while ($r = mysqli_fetch_assoc($res)) {
                    $rows[] = ['id'=>(string)$i++, 'nombre'=>(string)$r['nombre']];
                }
            }
        }
        json_ok(['provincias'=>$rows]);
    }

    if ($action === 'key') {
        json_ok(['key'=> $GOOGLE_MAPS_API_KEY !== '' ? 'present' : 'missing' ]);
    }

    json_err('bad_action');
} catch (Throwable $e) {
    json_err('server_error', 500);
}
