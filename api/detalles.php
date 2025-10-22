<?php declare(strict_types=1);
/**
 * /api/detalles.php — API pública (PDO)
 * Respuestas:
 *   ?all=1 [+ filtros opcionales] → [ {valla...}, ... ]  (array PLANO)
 *   ?id={int}                      → { ok:true, valla:{...} }
 * Filtros opcionales (cuando all=1 o sin id):
 *   q, zona, tipo(led|impresa|movilled|vehiculo), disponible(0|1),
 *   provincia (id), provincia_nombre (LIKE)
 */

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
require $root . '/config/db.php';
require $root . '/config/debug.php';

try {
  $pdo = db();

  $id  = (int)($_GET['id']  ?? 0);
  $all = (int)($_GET['all'] ?? 0);

  // ===== Base media =====
  $cfg_media = (string) db_setting('media_base_url', '') ?: (string) db_setting('uploads_base', '');
  $MEDIA_BASE = defined('MEDIA_BASE_URL') && MEDIA_BASE_URL !== ''
    ? rtrim((string)MEDIA_BASE_URL, '/')
    : ($cfg_media !== '' ? rtrim($cfg_media, '/') : 'https://auth.vallasled.com/uploads');

  $join_media = function(string $base, string $p): string {
    $base = rtrim($base,'/'); $p = ltrim($p,'/');
    // evita duplicar uploads/
    $p = preg_replace('~^(?:uploads/)+(.*)$~i', 'uploads/$1', $p);
    if (preg_match('~/uploads$~i',$base) && preg_match('~^uploads/~i',$p)) {
      $p = preg_replace('~^uploads/~i','',$p);
    }
    $parts = explode('?', $p, 2);
    $pth   = implode('/', array_map('rawurlencode', array_filter(explode('/', $parts[0]), 'strlen')));
    return $base . '/' . $pth . (isset($parts[1]) && $parts[1]!=='' ? ('?' . $parts[1]) : '');
  };

  $norm_media = function(?string $raw) use ($MEDIA_BASE, $join_media): ?string {
    $raw = trim((string)$raw);
    if ($raw==='') return null;
    if (preg_match('~^https?://~i',$raw)) return $raw;
    if (strpos($raw,'//')===0) return 'https:' . $raw;
    if (preg_match('~/uploads/~i',$raw)) return $join_media($MEDIA_BASE,$raw);
    return $join_media($MEDIA_BASE,'uploads/'.$raw);
  };

  $is_img = static function(string $u): bool {
    $path = parse_url($u, PHP_URL_PATH) ?? '';
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg','jpeg','png','webp','gif','avif','bmp'], true);
  };

  $build_media = function(array $r) use ($norm_media, $is_img): array {
    $out = [];
    foreach (['imagen_previa','imagen','imagen1','imagen2','imagen_tercera','imagen_cuarta'] as $c) {
      $u = $norm_media((string)($r[$c] ?? ''));
      if (!$u) continue;
      if ($is_img($u)) { $out[] = ['tipo'=>'foto','url'=>$u]; break; }
    }
    if (!$out) $out[] = ['tipo'=>'foto','url'=>'https://placehold.co/800x450/e2e8f0/4b5563?text=Sin+Imagen'];
    return $out;
  };

  // ===== Helper para mapear fila → objeto valla
  $map_row = function(array $r) use ($build_media): array {
    return [
      'id'                     => (int)$r['id'],
      'nombre'                 => (string)$r['nombre'],
      'provincia_id'           => isset($r['provincia_id']) ? (int)$r['provincia_id'] : null,
      'provincia'              => (string)($r['provincia_nombre'] ?? ''),
      'zona'                   => (string)($r['zona'] ?? ''),
      'ubicacion'              => (string)($r['ubicacion'] ?? ''),
      'precio'                 => isset($r['precio']) ? (float)$r['precio'] : 0.0,
      'tipo'                   => (string)$r['tipo'],
      'lat'                    => isset($r['lat']) ? (float)$r['lat'] : null,
      'lng'                    => isset($r['lng']) ? (float)$r['lng'] : null,
      'disponible'             => (int)($r['disponible'] ?? 0),
      'mostrar_precios_market' => (int)($r['mostrar_precios_market'] ?? 0),
      'medida'                 => (string)($r['medida'] ?? ''),
      'descripcion'            => (string)($r['descripcion'] ?? ''),
      'media'                  => $build_media($r),
      'url_stream_pantalla'    => (string)($r['url_stream_pantalla'] ?? ''),
      'url_stream_trafico'     => (string)($r['url_stream_trafico'] ?? ''),
    ];
  };

  // ===== ?id = detalle =====
  if ($id > 0) {
    $stmt = $pdo->prepare("
      SELECT v.id, v.tipo, v.nombre, v.provincia_id, p.nombre AS provincia_nombre,
             v.zona, v.ubicacion, v.lat, v.lng,
             v.url_stream_pantalla, v.url_stream_trafico,
             v.precio, v.disponible, v.medida, v.descripcion,
             v.mostrar_precios_market,
             v.imagen, v.imagen1, v.imagen2, v.imagen_previa, v.imagen_tercera, v.imagen_cuarta
        FROM vallas v
        LEFT JOIN provincias p ON p.id = v.provincia_id
       WHERE v.visible_publico = 1 AND v.estado_valla = 'activa' AND v.id = ?
       LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$row) {
      http_response_code(404);
      echo json_encode(['ok'=>false,'error'=>'NOT_FOUND'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      exit;
    }
    echo json_encode(['ok'=>true,'valla'=>$map_row($row)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }

  // ===== Listado (?all=1) o búsqueda con filtros =====
  $filters_present = isset($_GET['q'],) || isset($_GET['zona'], $_GET['tipo'], $_GET['disponible'], $_GET['provincia'], $_GET['provincia_nombre']);
  if ($all === 1 || $filters_present) {
    $where = [
      "v.visible_publico = 1",
      "v.estado_valla = 'activa'",
    ];
    $args  = [];

    // Filtros opcionales (no rompen nada si no se envían)
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q !== '') {
      $where[] = "(v.nombre LIKE ? OR v.ubicacion LIKE ? OR v.zona LIKE ?)";
      $like = '%' . $q . '%';
      array_push($args, $like, $like, $like);
    }

    $zona = trim((string)($_GET['zona'] ?? ''));
    if ($zona !== '') { $where[] = "v.zona LIKE ?"; $args[] = '%' . $zona . '%'; }

    $tipo = strtolower(trim((string)($_GET['tipo'] ?? '')));
    if ($tipo !== '' && in_array($tipo, ['led','impresa','movilled','vehiculo'], true)) {
      $where[] = "LOWER(v.tipo) = ?";
      $args[]  = $tipo;
    }

    if (isset($_GET['disponible']) && $_GET['disponible'] !== '') {
      $where[] = "v.disponible = ?";
      $args[]  = (int)$_GET['disponible'] ? 1 : 0;
    }

    if (isset($_GET['provincia']) && ctype_digit((string)$_GET['provincia'])) {
      $where[] = "v.provincia_id = ?";
      $args[]  = (int)$_GET['provincia'];
    }

    $provNombre = trim((string)($_GET['provincia_nombre'] ?? ''));
    if ($provNombre !== '') {
      $where[] = "p.nombre LIKE ?";
      $args[]  = '%' . $provNombre . '%';
    }

    $sql = "
      SELECT v.id, v.tipo, v.nombre, v.provincia_id, p.nombre AS provincia_nombre,
             v.zona, v.ubicacion, v.lat, v.lng,
             v.url_stream_pantalla, v.url_stream_trafico,
             v.precio, v.disponible, v.medida, v.descripcion,
             v.mostrar_precios_market,
             v.imagen, v.imagen1, v.imagen2, v.imagen_previa, v.imagen_tercera, v.imagen_cuarta
        FROM vallas v
        LEFT JOIN provincias p ON p.id = v.provincia_id
       WHERE " . implode(' AND ', $where) . "
    ORDER BY COALESCE(v.destacado_orden, 999999), v.nombre ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);

    $items = [];
    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
      $items[] = $map_row($r);
    }
    echo json_encode($items, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }

  // ===== Si no hay id ni all =====
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'BAD_REQUEST','hint'=>'use ?all=1 o ?id={int}'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'INTERNAL','msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
