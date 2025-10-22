<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../../../config/db.php';
start_session_safe();
if (empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) { http_response_code(401); echo json_encode(['rows'=>[]]); exit; }

$sql = "SELECT r.id,
               COALESCE(v.nombre, CONCAT('Valla #',r.valla_id)) valla,
               COALESCE(c.nombre, r.cliente_nombre, '-') cliente,
               CONCAT(DATE_FORMAT(r.fecha_inicio,'%d %b'),' - ',DATE_FORMAT(r.fecha_fin,'%d %b')) fechas,
               CONCAT(FORMAT(COALESCE(r.monto,0),2),' US$') monto_fmt,
               CASE r.estado
                 WHEN 'confirmada' THEN '<span class=\"px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800\">Confirmada</span>'
                 WHEN 'pendiente'  THEN '<span class=\"px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800\">Pendiente</span>'
                 WHEN 'cancelada'  THEN '<span class=\"px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800\">Cancelada</span>'
                 ELSE '<span class=\"px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800\">'+COALESCE(r.estado,'-')+'</span>'
               END estado_html
        FROM reservas r
        LEFT JOIN vallas v    ON v.id = r.valla_id
        LEFT JOIN clientes c  ON c.id = r.cliente_id
        ORDER BY COALESCE(r.fecha_inicio,r.creado_en) DESC
        LIMIT 10";
$rows=[]; if($q=$conn->query($sql)){ while($x=$q->fetch_assoc()) $rows[]=$x; }
echo json_encode(['rows'=>$rows], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
