<?php
// /console/gestion/vendors/ajax/guardar.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

try {
    // CSRF
    if (function_exists('csrf_token')) {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals(csrf_token(), $csrf)) {
            json_exit(['ok'=>false,'error'=>'BAD_CSRF'], 403);
        }
    }

    // Datos
    $id          = isset($_POST['id']) && $_POST['id']!=='' ? (int)$_POST['id'] : null;
    $nombre      = trim((string)($_POST['nombre'] ?? ''));
    $contacto    = trim((string)($_POST['contacto'] ?? ''));
    $email       = trim((string)($_POST['email'] ?? ''));
    $telefono    = trim((string)($_POST['telefono'] ?? ''));
    $direccion   = trim((string)($_POST['direccion'] ?? ''));
    $plan_id_raw = $_POST['plan_id'] ?? '';
    $plan_id     = ($plan_id_raw!=='' && ctype_digit((string)$plan_id_raw)) ? (int)$plan_id_raw : null;
    $inicio      = trim((string)($_POST['fecha_inicio'] ?? ''));
    $fin         = trim((string)($_POST['fecha_fin'] ?? ''));
    $estado      = isset($_POST['estado']) ? (($_POST['estado']=='1'||$_POST['estado']=='on')?1:0) : 0;

    // Validaciones
    if ($nombre==='')      { json_exit(['ok'=>false,'error'=>'VALIDATION','msg'=>'Nombre requerido'], 422); }
    if ($email==='' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_exit(['ok'=>false,'error'=>'VALIDATION','msg'=>'Email inválido'], 422);
    }
    if ($inicio!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$inicio)) { json_exit(['ok'=>false,'error'=>'VALIDATION','msg'=>'Fecha inicio inválida'], 422); }
    if ($fin!==''    && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$fin))     { json_exit(['ok'=>false,'error'=>'VALIDATION','msg'=>'Fecha fin inválida'], 422); }

    $conn->begin_transaction();

    if ($id===null) {
        // INSERT proveedor
        $sql="INSERT INTO proveedores (nombre,contacto,telefono,email,direccion,estado,creado)
              VALUES (?,?,?,?,?,?,NOW())";
        $stmt=$conn->prepare($sql);
        $stmt->bind_param('sssssi',$nombre,$contacto,$telefono,$email,$direccion,$estado);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
    } else {
        // UPDATE proveedor
        $sql="UPDATE proveedores SET nombre=?, contacto=?, telefono=?, email=?, direccion=?, estado=? WHERE id=?";
        $stmt=$conn->prepare($sql);
        $stmt->bind_param('sssssii',$nombre,$contacto,$telefono,$email,$direccion,$estado,$id);
        $stmt->execute();
        $stmt->close();
    }

    // Membresía
    if ($plan_id===null) {
        // Si existe, marcar inactiva
        $stmt=$conn->prepare("UPDATE vendor_membresias SET estado='inactiva', plan_id=NULL, fecha_fin=IFNULL(fecha_fin, CURDATE()) WHERE proveedor_id=?");
        $stmt->bind_param('i',$id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Upsert simple por existencia
        $check=$conn->prepare("SELECT id FROM vendor_membresias WHERE proveedor_id=? LIMIT 1");
        $check->bind_param('i',$id);
        $check->execute();
        $rid = $check->get_result()->fetch_assoc()['id'] ?? null;
        $check->close();

        if ($rid) {
            $sql="UPDATE vendor_membresias
                  SET plan_id=?, estado='activa',
                      fecha_inicio=IFNULL(?, fecha_inicio),
                      fecha_fin=NULLIF(?, '')
                  WHERE proveedor_id=?";
            $stmt=$conn->prepare($sql);
            $stmt->bind_param('issi',$plan_id,$inicio,$fin,$id);
            $stmt->execute(); $stmt->close();
        } else {
            $sql="INSERT INTO vendor_membresias (proveedor_id,plan_id,estado,fecha_inicio,fecha_fin)
                  VALUES (?,?,?,?,NULLIF(?,''))";
            $stmt=$conn->prepare($sql);
            $estado_vm='activa';
            $stmt->bind_param('iisss',$id,$plan_id,$estado_vm,$inicio,$fin);
            $stmt->execute(); $stmt->close();
        }
    }

    $conn->commit();
    json_exit(['ok'=>true,'id'=>$id]);
} catch (Throwable $e) {
    if ($conn && $conn->errno===0) { $conn->rollback(); }
    json_exit(['ok'=>false,'error'=>'DB_ERROR','msg'=>$e->getMessage()], 500);
}
