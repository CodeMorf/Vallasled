<?php
// /console/gestion/vendors/ajax/listar.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

try {
    // CSRF opcional si tu stack lo exige
    if (function_exists('csrf_token')) {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf !== '' && !hash_equals(csrf_token(), $csrf)) {
            json_exit(['ok'=>false,'error'=>'BAD_CSRF'], 403);
        }
    }

    $q        = trim((string)($_GET['q'] ?? ''));
    $estado   = $_GET['estado'] ?? '';
    $plan_id  = $_GET['plan_id'] ?? '';
    $feature  = trim((string)($_GET['feature'] ?? ''));
    $sort     = trim((string)($_GET['sort'] ?? 'recientes'));
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $perPage  = (int)($_GET['per_page'] ?? 20);
    if ($perPage < 5)   $perPage = 5;
    if ($perPage > 100) $perPage = 100;
    $offset   = ($page - 1) * $perPage;

    $featureMap = [
        'crm'         => 'vpf.access_crm',
        'facturacion' => 'vpf.access_facturacion',
        'mapa'        => 'vpf.access_mapa',
        'export'      => 'vpf.access_export',
        'ncf'         => 'vpf.soporte_ncf',
    ];

    $where=[]; $types=''; $vals=[];
    if ($q !== '') {
        $where[]='(p.nombre LIKE ? OR p.email LIKE ? OR p.contacto LIKE ?)';
        $like='%'.$q.'%'; $types.='sss'; array_push($vals,$like,$like,$like);
    }
    if ($estado === '0' || $estado === '1') { $where[]='p.estado = ?'; $types.='i'; $vals[]=(int)$estado; }
    if ($plan_id !== '' && ctype_digit((string)$plan_id)) { $where[]='vm.plan_id = ?'; $types.='i'; $vals[]=(int)$plan_id; }
    if ($feature !== '' && isset($featureMap[$feature])) { $where[]="COALESCE({$featureMap[$feature]},0)=1"; }
    $whereSql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

    switch ($sort) {
        case 'nombre':     $orderBy='p.nombre ASC'; break;
        case 'plan':       $orderBy='vp.nombre ASC, p.nombre ASC'; break;
        case 'vallas':     $orderBy='v_stats.vallas_total DESC, p.nombre ASC'; break;
        case 'pagado':     $orderBy='f_stats.total_pagado DESC, p.nombre ASC'; break;
        case 'pendiente':  $orderBy='f_stats.total_pendiente DESC, p.nombre ASC'; break;
        default:           $orderBy='p.creado DESC, p.id DESC';
    }

    $sqlCount="
      SELECT COUNT(*) c
      FROM proveedores p
      LEFT JOIN vendor_membresias vm ON vm.proveedor_id=p.id
      LEFT JOIN vendor_planes vp ON vp.id=vm.plan_id
      LEFT JOIN vendor_plan_features vpf ON vpf.plan_id=vm.plan_id
      $whereSql
    ";
    $stmt=$conn->prepare($sqlCount);
    if($types!==''){$stmt->bind_param($types,...$vals);}
    $stmt->execute(); $total=(int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close();

    $sql="
      SELECT
        p.id,p.nombre,p.contacto,p.telefono,p.email,p.estado,p.creado,
        vm.plan_id,vm.estado AS membresia_estado,vm.fecha_inicio,vm.fecha_fin,
        vp.nombre AS plan_nombre,
        COALESCE(v_stats.vallas_total,0) vallas_total,
        COALESCE(v_stats.vallas_activas,0) vallas_activas,
        COALESCE(v_stats.vallas_inactivas,0) vallas_inactivas,
        COALESCE(r_stats.reservas_activas,0) reservas_activas,
        COALESCE(f_stats.facturas_total,0) facturas_total,
        COALESCE(f_stats.total_pagado,0.00) total_pagado,
        COALESCE(f_stats.total_pendiente,0.00) total_pendiente,
        COALESCE(vpf.access_crm,0) access_crm,
        COALESCE(vpf.access_facturacion,0) access_facturacion,
        COALESCE(vpf.access_mapa,0) access_mapa,
        COALESCE(vpf.access_export,0) access_export,
        COALESCE(vpf.soporte_ncf,0) soporte_ncf
      FROM proveedores p
      LEFT JOIN vendor_membresias vm ON vm.proveedor_id=p.id
      LEFT JOIN vendor_planes vp ON vp.id=vm.plan_id
      LEFT JOIN vendor_plan_features vpf ON vpf.plan_id=vm.plan_id
      LEFT JOIN (
        SELECT v.proveedor_id, COUNT(*) vallas_total,
               SUM(v.estado_valla='activa') vallas_activas,
               SUM(v.estado_valla='inactiva') vallas_inactivas
        FROM vallas v GROUP BY v.proveedor_id
      ) v_stats ON v_stats.proveedor_id=p.id
      LEFT JOIN (
        SELECT v.proveedor_id,
               SUM(CURDATE() BETWEEN r.fecha_inicio AND r.fecha_fin
                   AND r.estado IN('confirmada','activa')) reservas_activas
        FROM reservas r JOIN vallas v ON v.id=r.valla_id GROUP BY v.proveedor_id
      ) r_stats ON r_stats.proveedor_id=p.id
      LEFT JOIN (
        SELECT v.proveedor_id, COUNT(*) facturas_total,
               SUM(CASE WHEN f.estado='pagado' THEN f.monto ELSE 0 END) total_pagado,
               SUM(CASE WHEN f.estado='pendiente' THEN f.monto ELSE 0 END) total_pendiente
        FROM facturas f JOIN vallas v ON v.id=f.valla_id GROUP BY v.proveedor_id
      ) f_stats ON f_stats.proveedor_id=p.id
      $whereSql
      ORDER BY $orderBy
      LIMIT ? OFFSET ?
    ";
    $stmt=$conn->prepare($sql);
    $types2=$types.'ii'; $vals2=$vals; $vals2[]=$perPage; $vals2[]=$offset;
    if($types2!==''){$stmt->bind_param($types2,...$vals2);}
    $stmt->execute(); $res=$stmt->get_result();
    $rows=[];
    while($r=$res->fetch_assoc()){
        foreach(['vallas_total','vallas_activas','vallas_inactivas','reservas_activas','facturas_total'] as $k){ $r[$k]=(int)($r[$k]??0); }
        foreach(['total_pagado','total_pendiente'] as $k){ $r[$k]=(float)($r[$k]??0); }
        foreach(['access_crm','access_facturacion','access_mapa','access_export','soporte_ncf','estado'] as $k){ if(isset($r[$k])) $r[$k]=(int)$r[$k]; }
        if(isset($r['plan_id'])) $r['plan_id']= $r['plan_id']===null ? null : (int)$r['plan_id'];
        $rows[]=$r;
    }
    $stmt->close();

    json_exit(['ok'=>true,'total'=>$total,'rows'=>$rows,'page'=>$page,'per_page'=>$perPage]);
} catch (Throwable $e) {
    json_exit(['ok'=>false,'error'=>'DB_ERROR','msg'=>$e->getMessage()], 500);
}
