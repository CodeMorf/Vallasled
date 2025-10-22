<?php
// /console/gestion/proveedores/ajax/listar.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    // Parámetros opcionales ?limit=&offset=
    $limit  = isset($_GET['limit'])  ? max(1, min(1000, (int)$_GET['limit']))  : 100;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset'])            : 0;

    $sql = "
      SELECT
        p.id,
        p.nombre,
        p.contacto,
        p.telefono,
        p.email,
        p.direccion,
        p.estado,
        m.plan_id,
        pl.nombre  AS plan_nombre,
        m.estado   AS plan_estado
      FROM proveedores p
      LEFT JOIN vendor_membresias m ON m.proveedor_id = p.id
      LEFT JOIN vendor_planes     pl ON pl.id = m.plan_id
      ORDER BY p.nombre ASC
      LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $res = $stmt->get_result();

    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'id'          => (int)$row['id'],
            'nombre'      => (string)$row['nombre'],
            'contacto'    => $row['contacto']   !== null ? (string)$row['contacto']   : '',
            'telefono'    => $row['telefono']   !== null ? (string)$row['telefono']   : '',
            'email'       => $row['email']      !== null ? (string)$row['email']      : '',
            'direccion'   => $row['direccion']  !== null ? (string)$row['direccion']  : '',
            'estado'      => (int)$row['estado'],
            'plan_id'     => $row['plan_id']    !== null ? (int)$row['plan_id']       : null,
            'plan_nombre' => $row['plan_nombre']!== null ? (string)$row['plan_nombre'] : null,
            'plan_estado' => $row['plan_estado']!== null ? (string)$row['plan_estado'] : 'inactivo',
        ];
    }

    echo json_encode([
        'ok'   => true,
        'items'=> $items,
        'meta' => ['total'=>count($items), 'limit'=>$limit, 'offset'=>$offset]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'   => false,
        'msg'  => 'Error al listar',
        'error'=> $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
