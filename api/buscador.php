<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/media.php';

function i($v, int $def=0): int { $n = filter_var($v, FILTER_VALIDATE_INT); return $n===false? $def : $v+0; }
function s($v): string { return trim((string)$v); }

try {
  $pdo = db();

  // Filtros
  $id           = i($_GET['id'] ?? 0);
  $q            = s($_GET['q'] ?? '');
  $tipo         = s($_GET['tipo'] ?? '');
  $zona         = s($_GET['zona'] ?? '');
  $provinciaId  = i($_GET['provincia'] ?? 0);
  $provNom      = s($_GET['provincia_nombre'] ?? '');
  $disponibleQS = $_GET['disponible'] ?? null;         // 1|0 opcional
  $estadoQS     = strtolower(s($_GET['estado'] ?? 'activa')); // 'activa' | 'inactiva' | 'all'

  $where  = ["v.visible_publico=1"];
  $params = [];

  // Estado por defecto: solo activas
  if ($estadoQS !== 'all' && $estadoQS !== '') {
    $where[] = "v.estado_valla = :estado";
    $params[':estado'] = ($estadoQS === 'inactiva') ? 'inactiva' : 'activa';
  }

  if ($id > 0)                 { $where[] = "v.id=:id"; $params[':id'] = $id; }
  if ($q !== '')               { $where[] = "(v.nombre LIKE :q OR v.ubicacion LIKE :q)"; $params[':q'] = "%$q%"; }
  if ($tipo !== '')            { $where[] = "v.tipo=:tipo"; $params[':tipo'] = $tipo; }
  if ($zona !== '')            { $where[] = "v.zona=:zona"; $params[':zona'] = $zona; }
  if ($provinciaId > 0)        { $where[] = "v.provincia_id=:provincia"; $params[':provincia'] = $provinciaId; }
  if ($provNom !== '')         { $where[] = "p.nombre LIKE :provnom"; $params[':provnom'] = "%$provNom%"; }
  if ($disponibleQS !== null && $disponibleQS !== '') {
    $where[] = "v.disponible=" . (intval($disponibleQS) ? "1" : "0");
  }

  $sql = "
    SELECT
      v.id, v.nombre, v.tipo, v.ubicacion, v.zona, v.provincia_id, v.precio, v.disponible,
      v.medida, v.lat, v.lng, v.estado_valla,
      v.url_stream_pantalla, v.url_stream_trafico,
      v.imagen, v.imagen1, v.imagen2, v.imagen_previa, v.imagen_tercera, v.imagen_cuarta,
      p.nombre AS provincia
    FROM vallas v
    LEFT JOIN provincias p ON p.id = v.provincia_id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY v.id DESC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $items = [];
  foreach ($rows as $r) {
    // Galería: prioriza primera foto disponible
    $media = [];
    foreach (['imagen_previa','imagen','imagen1','imagen2','imagen_tercera','imagen_cuarta'] as $c) {
      $u = media_norm((string)($r[$c] ?? ''));
      if ($u === '') continue;
      $media[] = ['tipo' => (is_vid($u) ? 'video' : 'foto'), 'url' => $u];
      if ($media[0]['tipo'] === 'foto') break;
    }
    if (!$media) $media[] = ['tipo'=>'foto','url'=>'https://placehold.co/600x340/e2e8f0/475569?text=Sin+imagen'];

    // Estados
    $estadoRaw = strtolower((string)($r['estado_valla'] ?? ''));
    $activo    = ($estadoRaw === 'activa') ? 1 : 0;
    $dispInt   = (int)($r['disponible'] ?? 0);
    $estadoUi  = $activo ? 'activo' : 'inactivo';
    $dispUi    = $dispInt === 1 ? 'disponible' : 'no_disponible';

    $items[] = [
      'id'         => (int)$r['id'],
      'nombre'     => (string)$r['nombre'],
      'tipo'       => (string)$r['tipo'],
      'ubicacion'  => (string)($r['ubicacion'] ?? ''),
      'zona'       => (string)($r['zona'] ?? ''),
      'provincia_id' => isset($r['provincia_id']) ? (int)$r['provincia_id'] : null,
      'provincia'  => (string)($r['provincia'] ?? ''),
      'precio'     => isset($r['precio']) ? (float)$r['precio'] : 0.0,

      // === NUEVO: estados listos para UI ===
      'estado_valla'   => $estadoRaw,     // 'activa' | 'inactiva'
      'estado'         => $estadoUi,      // 'activo' | 'inactivo'
      'activo'         => $activo,        // 1|0
      'disponible'     => $dispInt,       // 1|0 (compat)
      'disponible_txt' => $dispUi,        // 'disponible' | 'no_disponible'

      'medida'     => (string)($r['medida'] ?? ''),
      'lat'        => isset($r['lat']) ? (float)$r['lat'] : null,
      'lng'        => isset($r['lng']) ? (float)$r['lng'] : null,
      'url_stream_pantalla' => (string)($r['url_stream_pantalla'] ?? ''),
      'url_stream_trafico'  => (string)($r['url_stream_trafico'] ?? ''),
      'media'      => $media,
    ];
  }

  echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'INTERNAL']);
}
