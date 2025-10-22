<?php
declare(strict_types=1);

// /api/ocultar-buscador/api.php
// Guarda/lee el estado "oculto" del buscador usando cookie 'filtersCollapsed'.
// GET  -> devuelve estado actual
// POST -> collapsed=1|0 para fijar, o toggle=1 para alternar

// Opcional: tu debug
// require_once __DIR__ . '/../../config/debug.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

const COOKIE_NAME = 'filtersCollapsed';
const COOKIE_LIFETIME = 60*60*24*180; // 180 días

function read_bool_param(array $src, string $key): ?bool {
  if (!isset($src[$key])) return null;
  $v = trim((string)$src[$key]);
  if ($v === '') return null;
  if (in_array($v, ['1','true','on','yes'], true))  return true;
  if (in_array($v, ['0','false','off','no'], true)) return false;
  return null;
}

function cookie_secure(): bool {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
  return $https;
}

function get_state_from_cookie(): bool {
  $c = $_COOKIE[COOKIE_NAME] ?? '';
  return in_array($c, ['1','true','on','yes'], true);
}

function set_state_cookie(bool $collapsed): void {
  setcookie(
    COOKIE_NAME,
    $collapsed ? '1' : '0',
    [
      'expires'  => time() + COOKIE_LIFETIME,
      'path'     => '/',
      'domain'   => '',         // actual host
      'secure'   => cookie_secure(),
      'httponly' => true,
      'samesite' => 'Lax',
    ]
  );
}

// Manejo de métodos
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
  if ($method === 'POST') {
    $collapsed = read_bool_param($_POST, 'collapsed');
    $toggle    = read_bool_param($_POST, 'toggle');

    if ($toggle === true && $collapsed === null) {
      // Alternar
      $current = get_state_from_cookie();
      $new = !$current;
      set_state_cookie($new);
      echo json_encode(['ok'=>true, 'collapsed'=>$new, 'action'=>'toggle']);
      exit;
    }

    if ($collapsed !== null) {
      set_state_cookie($collapsed);
      echo json_encode(['ok'=>true, 'collapsed'=>$collapsed, 'action'=>'set']);
      exit;
    }

    // Si no hay parámetros válidos
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'Parámetros inválidos. Usa collapsed=1|0 o toggle=1.']);
    exit;
  }

  // GET -> devolver estado
  $state = get_state_from_cookie();
  echo json_encode(['ok'=>true, 'collapsed'=>$state, 'action'=>'get']);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Exception', 'message'=>$e->getMessage()]);
  exit;
}
