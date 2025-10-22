<?php
// /console/gestion/contabilidad/ajax/listar.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

try {
    $q        = trim((string)($_GET['q'] ?? ''));
    $tipo     = $_GET['tipo'] ?? '';                 // ingreso|egreso|''
    $cat      = $_GET['categoria'] ?? '';            // venta_publicidad|comision_vendor|''
    $desde    = $_GET['desde'] ?? '';
    $hasta    = $_GET['hasta'] ?? '';
    $sort     = $_GET['sort'] ?? 'fecha_desc';       // fecha_desc|fecha_asc
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $perPage  = (int)($_GET['per_page'] ?? 20);
    if ($perPage < 5)   $perPage = 5;
    if ($perPage > 200) $perPage = 200;
    $offset   = ($page - 1) * $perPage;

    // filtros comunes
    $where = [];
    $types = '';
    $vals  = [];

    // Fechas: usamos fecha_pago porque listamos movimientos realizados
    if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
        $where[] = "fecha >= ?";
        $types  .= 's';
        $vals[]  = $desde . ' 00:00:00';
    }
    if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
        $where[] = "fecha <= ?";
        $types  .= 's';
        $vals[]  = $hasta . ' 23:59:59';
    }
    if ($tipo === 'ingreso' || $tipo === 'egreso') {
        $where[] = "tipo = ?";
        $types  .= 's';
        $vals[]  = $tipo;
    }
    if ($cat === 'venta_publicidad' || $cat === 'comision_vendor') {
        $where[] = "categoria = ?";
        $types  .= 's';
        $vals[]  = $cat;
    }
    if ($q !== '') {
        $where[] = "(descripcion LIKE ? OR COALESCE(cliente_nombre,'') LIKE ? OR COALESCE(proveedor_nombre,'') LIKE ?)";
        $like = '%' . $q . '%';
        $types .= 'sss';
        array_push($vals, $like, $like, $like);
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Subconsulta: ingresos + egresos por comisión desde facturas pagadas
    $base = "
      SELECT
        f.id                   AS ref_id,
        'factura'              AS ref_type,
        COALESCE(f.fecha_pago, f.fecha_generada) AS fecha,
        CONCAT('Pago Factura #', f.id) AS descripcion,
        'venta_publicidad'     AS categoria,
        f.total                AS monto,
        'ingreso'              AS tipo,
        COALESCE(f.cliente_nombre,'') AS cliente_nombre,
        p.nombre               AS proveedor_nombre
      FROM facturas f
      LEFT JOIN proveedores p ON p.id=f.proveedor_id
      WHERE f.estado='pagado' AND COALESCE(f.fecha_pago, f.fecha_generada) IS NOT NULL

      UNION ALL

      SELECT
        f.id                   AS ref_id,
        'factura'              AS ref_type,
        COALESCE(f.fecha_pago, f.fecha_generada) AS fecha,
        CONCAT('Comisión Vendor por Factura #', f.id) AS descripcion,
        'comision_vendor'      AS categoria,
        COALESCE(f.comision_monto,0.00) AS monto,
        'egreso'               AS tipo,
        COALESCE(f.cliente_nombre,'') AS cliente_nombre,
        p.nombre               AS proveedor_nombre
      FROM facturas f
      LEFT JOIN proveedores p ON p.id=f.proveedor_id
      WHERE f.estado='pagado' AND COALESCE(f.fecha_pago, f.fecha_generada) IS NOT NULL
        AND COALESCE(f.comision_monto,0.00) > 0
    ";

    // Conteo total
    $sqlCount = "SELECT COUNT(*) AS c FROM ( $base ) t $whereSql";
    $stmt = $conn->prepare($sqlCount);
    if ($types !== '') { $stmt->bind_param($types, ...$vals); }
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    // Datos paginados
    $orderBy = ($sort === 'fecha_asc') ? 'fecha ASC, ref_id ASC' : 'fecha DESC, ref_id DESC';
    $sql = "SELECT * FROM ( $base ) t $whereSql ORDER BY $orderBy LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $types2 = $types . 'ii';
    $vals2  = $vals;
    $vals2[] = $perPage;
    $vals2[] = $offset;
    if ($types2 !== '') { $stmt->bind_param($types2, ...$vals2); }
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        // casteos
        $r['ref_id'] = (int)$r['ref_id'];
        $r['monto']  = (float)$r['monto'];
        $rows[] = $r;
    }
    $stmt->close();

    json_exit(['ok'=>true,'total'=>$total,'rows'=>$rows,'page'=>$page,'per_page'=>$perPage]);
} catch (Throwable $e) {
    json_exit(['ok'=>false,'error'=>'DB_ERROR','msg'=>$e->getMessage()], 500);
}
