<?php
// /console/gestion/vendors/ajax/eliminar.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

try {
    if (function_exists('csrf_token')) {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals(csrf_token(), $csrf)) {
            json_exit(['ok'=>false,'error'=>'BAD_CSRF'], 403);
        }
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id<=0) { json_exit(['ok'=>false,'error'=>'VALIDATION','msg'=>'ID inválido'], 422); }

    // Soft delete: desactivar proveedor y membresía
    $conn->begin_transaction();

    $stmt=$conn->prepare("UPDATE proveedores SET estado=0 WHERE id=?");
    $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close();

    $stmt=$conn->prepare("UPDATE vendor_membresias SET estado='inactiva', fecha_fin=IFNULL(fecha_fin, CURDATE()) WHERE proveedor_id=?");
    $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close();

    $conn->commit();
    json_exit(['ok'=>true]);
} catch (Throwable $e) {
    if ($conn && $conn->errno===0) { $conn->rollback(); }
    json_exit(['ok'=>false,'error'=>'DB_ERROR','msg'=>$e->getMessage()], 500);
}
