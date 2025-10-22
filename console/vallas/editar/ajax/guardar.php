<?php
// /console/vallas/editar/ajax/guardar.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../../config/db.php';
    require_once __DIR__ . '/../../../../config/mapas.php';

    start_session_safe();

    // CSRF
    $csrf = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$csrf)) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'CSRF']); exit;
    }

    // ID
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'ID_INVALID']); exit;
    }

    // Inputs + mapeos
    $nombre   = trim((string)($_POST['nombre'] ?? ''));
    $provRaw  = $_POST['provincia_id'] ?? $_POST['provincia'] ?? null;
    $provincia_id = is_numeric($provRaw) ? (int)$provRaw : 0;

    $proveedor_id = (int)($_POST['proveedor'] ?? 0);
    $zona      = trim((string)($_POST['zona'] ?? ''));
    $ubicacion = trim((string)($_POST['ubicacion'] ?? ''));
    $lat       = $_POST['lat'] ?? null;
    $lng       = $_POST['lng'] ?? ($_POST['ln'] ?? null);

    $tipo      = trim((string)($_POST['tipo'] ?? 'led'));
    $medida    = trim((string)($_POST['medida'] ?? ''));
    $precio    = (float)($_POST['precio'] ?? 0);
    $audiencia = isset($_POST['audiencia_mensual']) && $_POST['audiencia_mensual'] !== '' ? (int)$_POST['audiencia_mensual'] : 0;
    $spot_seg  = isset($_POST['spot_time_seg']) && $_POST['spot_time_seg'] !== '' ? (int)$_POST['spot_time_seg'] : 0;

    $url_pant  = trim((string)($_POST['url_stream_pantalla'] ?? ''));
    $url_traf  = trim((string)($_POST['url_stream_trafico'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $keywords    = trim((string)($_POST['keywords_seo'] ?? ''));
    $num_lic   = trim((string)($_POST['numero_licencia'] ?? ''));

    $fv_in     = trim((string)($_POST['fecha_vencimiento'] ?? '')); // Y-m-d
    $fecha_vencimiento = $fv_in !== '' ? ($fv_in . ' 00:00:00') : null;

    $estado_valla = in_array(($_POST['estado_valla'] ?? 'activa'), ['activa','inactiva'], true)
                    ? $_POST['estado_valla'] : 'activa';

    $visible_publico        = isset($_POST['visible_publico']) ? 1 : 0;
    $mostrar_precio_cliente = isset($_POST['mostrar_precio_cliente']) ? 1 : 0;

    $imagen = trim((string)($_POST['imagen_url'] ?? ''));

    // ADS (checkbox y campos opcionales)
    $ads       = isset($_POST['ads']);
    $ads_start = trim((string)($_POST['ads_start'] ?? ''));
    $ads_end   = trim((string)($_POST['ads_end'] ?? ''));
    $monto_pag = (string)($_POST['monto_pagado'] ?? '0');
    $orden_ads = (string)($_POST['orden'] ?? '1');

    // Validación
    $errors = [];
    if ($nombre === '')                $errors['nombre'] = 'requerido';
    if ($ubicacion === '')             $errors['ubicacion'] = 'requerido';
    if (!is_numeric($lat))             $errors['lat'] = 'num';
    if (!is_numeric($lng))             $errors['lng'] = 'num';
    if (!in_array($tipo, ['led','impresa','movilled','vehiculo'], true)) $errors['tipo'] = 'enum';
    if ($provincia_id < 0)             $errors['provincia_id'] = 'num';
    if ($proveedor_id < 0)             $errors['proveedor'] = 'num';

    if ($ads) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ads_start)) $errors['ads_start'] = 'fecha';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ads_end))   $errors['ads_end']   = 'fecha';
        if (!isset($errors['ads_start']) && !isset($errors['ads_end'])) {
            if (strtotime($ads_start) === false || strtotime($ads_end) === false || $ads_start > $ads_end) {
                $errors['ads_rango'] = 'invalido';
            }
        }
        if (!is_numeric($monto_pag) || (float)$monto_pag < 0) $errors['monto_pagado']='num';
        if (!ctype_digit($orden_ads) || (int)$orden_ads < 1)  $errors['orden']='num';
    }

    if ($errors) {
        echo json_encode(['ok'=>false, 'error'=>'VALIDATION', 'fields'=>$errors]); exit;
    }

    // Normaliza numéricos
    $lat = (float)$lat;
    $lng = (float)$lng;

    $conn->begin_transaction();

    // UPDATE
    $sql = "UPDATE vallas SET
        nombre=?,
        provincia_id=?,
        proveedor_id=?,
        zona=?,
        ubicacion=?,
        lat=?,
        lng=?,
        tipo=?,
        medida=?,
        precio=?,
        audiencia_mensual=?,
        spot_time_seg=?,
        url_stream_pantalla=?,
        url_stream_trafico=?,
        descripcion=?,
        keywords_seo=?,
        numero_licencia=?,
        fecha_vencimiento=?,
        estado_valla=?,
        visible_publico=?,
        mostrar_precio_cliente=?,
        imagen=?
      WHERE id=? LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['ok'=>false,'error'=>'SQL_PREPARE','detail'=>$conn->error]); exit;
    }

    // 23 tipos exactos
    $types = 'siissddssdiisssssssiisi';

    $stmt->bind_param(
        $types,
        $nombre,
        $provincia_id,
        $proveedor_id,
        $zona,
        $ubicacion,
        $lat,
        $lng,
        $tipo,
        $medida,
        $precio,
        $audiencia,
        $spot_seg,
        $url_pant,
        $url_traf,
        $descripcion,
        $keywords,
        $num_lic,
        $fecha_vencimiento, // puede ser null
        $estado_valla,
        $visible_publico,
        $mostrar_precio_cliente,
        $imagen,
        $id
    );

    if (!$stmt->execute()) {
        $conn->rollback();
        echo json_encode(['ok'=>false,'error'=>'SQL_EXEC','detail'=>$stmt->error]); exit;
    }

    // Inserta ADS si corresponde
    if ($ads) {
        $sqlAds = "INSERT INTO vallas_destacadas_pagos
          (valla_id, proveedor_id, cliente_id, fecha_inicio, fecha_fin, monto_pagado, observacion, `orden`)
          VALUES (?,?,?,?,?,?,?,?)";
        $stmtAds = $conn->prepare($sqlAds);
        if (!$stmtAds) {
            $conn->rollback();
            echo json_encode(['ok'=>false,'error'=>'SQL_PREPARE_ADS','detail'=>$conn->error]); exit;
        }

        // La tabla del dump no tiene AUTO_INCREMENT y permite NULL en proveedor/cliente. :contentReference[oaicite:0]{index=0}
        $prov_for_ads = $proveedor_id > 0 ? $proveedor_id : null;
        $cliente_id_i = null;
        $obs = 'ADS';
        $monto_f = (float)$monto_pag;
        $orden_i = (int)$orden_ads;

        $stmtAds->bind_param('iiissdsi', $id, $prov_for_ads, $cliente_id_i, $ads_start, $ads_end, $monto_f, $obs, $orden_i);
        if (!$stmtAds->execute()) {
            $conn->rollback();
            echo json_encode(['ok'=>false,'error'=>'SQL_EXEC_ADS','detail'=>$stmtAds->error]); exit;
        }
    }

    $conn->commit();
    echo json_encode(['ok'=>true,'id'=>$id]); exit;

} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['ok'=>false,'error'=>'SERVER','detail'=>$e->getMessage()]);
}
