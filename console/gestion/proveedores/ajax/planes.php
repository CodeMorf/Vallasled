<?php
// /console/gestion/proveedores/ajax/planes.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    // Devuelve planes activos
    $sql = "SELECT id, nombre FROM vendor_planes WHERE estado=1 ORDER BY id ASC";
    $res = $conn->query($sql);

    $items = [];
    while ($r = $res->fetch_assoc()) {
        $items[] = ['id' => (int)$r['id'], 'nombre' => (string)$r['nombre']];
    }

    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'   => false,
        'msg'  => 'Error al cargar planes',
        'error'=> $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
