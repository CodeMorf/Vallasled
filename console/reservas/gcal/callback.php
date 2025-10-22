<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../lib/gcal.php';
start_session_safe();

$expected = $_SESSION['gcal_state'] ?? '';
$state = $_GET['state'] ?? '';
$code = $_GET['code'] ?? '';
$uid = (int)($_SESSION['uid'] ?? 0);

if (!$uid || !$code || !$expected || !hash_equals($expected, $state)) {
  http_response_code(400); echo 'OAuth error'; exit;
}

$cfg = gcal_cfg($conn);
if ($cfg['client_id'] === '' || $cfg['client_secret'] === '') {
  http_response_code(500); echo 'Falta configurar Google OAuth'; exit;
}

$j = http_json('https://oauth2.googleapis.com/token',[
  'headers'=>['Content-Type: application/x-www-form-urlencoded'],
  'post'=>http_build_query([
    'client_id'    => $cfg['client_id'],
    'client_secret'=> $cfg['client_secret'],
    'code'         => $code,
    'redirect_uri' => $cfg['redirect_uri'],
    'grant_type'   => 'authorization_code',
  ])
]);

if (!isset($j['access_token'])) { http_response_code(400); echo 'No access_token'; exit; }

$save = [
  'access_token'  => $j['access_token'],
  'refresh_token' => $j['refresh_token'] ?? null,
  'token_type'    => $j['token_type'] ?? 'Bearer',
  'expires_at'    => time() + (int)($j['expires_in'] ?? 3500),
  'scope'         => $j['scope'] ?? $cfg['scope'],
];

/**
 * Si shared_mode=1 y el usuario es admin, puedes decidir guardar como token orgánico (user_id=0)
 * para que aplique a todo el staff automáticamente. Si prefieres por-usuario, comenta esta rama.
 */
$tipo = $_SESSION['tipo'] ?? '';
$targetUid = ((int)$cfg['shared_mode'] === 1 && in_array($tipo, ['admin'], true)) ? 0 : $uid;

gcal_tokens_save($conn, $targetUid, $save);
unset($_SESSION['gcal_state']);

header('Location: /console/reservas/?gcal=ok');
