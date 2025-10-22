<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';

function gcal_cfg(mysqli $conn): array {
  static $cache = null;
  if ($cache !== null) return $cache;

  $cfg = [
    'client_id'     => '',
    'client_secret' => '',
    'redirect_uri'  => '',
    'scope'         => 'https://www.googleapis.com/auth/calendar.readonly',
    'shared_mode'   => 0,
  ];

  try {
    if ($conn->query("SHOW TABLES LIKE 'google_oauth_config'")->num_rows) {
      $rs = $conn->query("SELECT client_id, client_secret, redirect_uri, scope, shared_mode FROM google_oauth_config WHERE id=1 LIMIT 1");
      if ($rs && $rs->num_rows) {
        $row = $rs->fetch_assoc();
        $cfg['client_id']     = (string)$row['client_id'];
        $cfg['client_secret'] = (string)$row['client_secret'];
        $cfg['redirect_uri']  = (string)$row['redirect_uri'];
        $cfg['scope']         = (string)$row['scope'];
        $cfg['shared_mode']   = (int)$row['shared_mode'];
      }
    }
  } catch (\Throwable $e) {}

  // Fallback a entorno si falta algo
  if ($cfg['client_id'] === '')     $cfg['client_id']     = getenv('GOOGLE_CLIENT_ID')     ?: '';
  if ($cfg['client_secret'] === '') $cfg['client_secret'] = getenv('GOOGLE_CLIENT_SECRET') ?: '';
  if ($cfg['redirect_uri'] === '') {
    $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ($_SERVER['REQUEST_SCHEME'] ?? 'https');
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $cfg['redirect_uri'] = $scheme . '://' . $host . '/console/reservas/gcal/callback.php';
  }

  return $cache = $cfg;
}

function gcal_tokens_get(mysqli $conn, int $uid): ?array {
  $rs = $conn->query("SELECT * FROM google_oauth_tokens WHERE user_id={$uid} LIMIT 1");
  return ($rs && $rs->num_rows) ? $rs->fetch_assoc() : null;
}
function gcal_tokens_save(mysqli $conn, int $uid, array $t): bool {
  $access = $conn->real_escape_string($t['access_token']);
  $refresh = isset($t['refresh_token']) ? $conn->real_escape_string((string)$t['refresh_token']) : null;
  $type = $conn->real_escape_string($t['token_type'] ?? 'Bearer');
  $scope = $conn->real_escape_string($t['scope'] ?? '');
  $exp = (int)($t['expires_at'] ?? (time()+($t['expires_in']??3500)));
  $sql = "INSERT INTO google_oauth_tokens (user_id, access_token, refresh_token, token_type, expires_at, scope)
          VALUES ($uid,'$access',".($refresh?"'$refresh'":"NULL").",'$type',$exp,'$scope')
          ON DUPLICATE KEY UPDATE access_token=VALUES(access_token), refresh_token=COALESCE(VALUES(refresh_token),refresh_token), token_type=VALUES(token_type), expires_at=VALUES(expires_at), scope=VALUES(scope)";
  return (bool)$conn->query($sql);
}
function gcal_tokens_delete(mysqli $conn, int $uid): bool {
  return (bool)$conn->query("DELETE FROM google_oauth_tokens WHERE user_id=$uid");
}

function http_json(string $url, array $opts=[]): array {
  $ch = curl_init($url);
  $headers = $opts['headers'] ?? [];
  $post = $opts['post'] ?? null;
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>20,
    CURLOPT_SSL_VERIFYHOST=>2,
    CURLOPT_SSL_VERIFYPEER=>true,
    CURLOPT_HTTPHEADER=>$headers
  ]);
  if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
  $res = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  if ($res === false) { $err = curl_error($ch); curl_close($ch); return ['ok'=>false,'code'=>$code,'error'=>$err]; }
  curl_close($ch);
  $j = json_decode($res, true);
  return is_array($j) ? $j : ['ok'=>false,'code'=>$code,'error'=>'invalid_json','raw'=>$res];
}

/**
 * Devuelve token válido del usuario. Si no hay y shared_mode=1, intenta token orgánico (user_id=0) para admins/staff.
 */
function gcal_token_user_or_org(mysqli $conn, int $uid): ?array {
  $tok = gcal_tokens_get($conn, $uid);
  if ($tok) return $tok;

  $cfg = gcal_cfg($conn);
  $tipo = $_SESSION['tipo'] ?? '';
  if ((int)$cfg['shared_mode'] === 1 && in_array($tipo, ['admin','staff'], true)) {
    $orgTok = gcal_tokens_get($conn, 0); // token orgánico
    if ($orgTok) return $orgTok;
  }
  return null;
}

function gcal_refresh_if_needed(mysqli $conn, int $uid): ?array {
  $tok = gcal_token_user_or_org($conn, $uid);
  if (!$tok) return null;
  if ((int)$tok['expires_at'] > time() + 60) return $tok;

  if (empty($tok['refresh_token'])) {
    // Si era orgánico sin refresh, no hay forma de renovar
    return null;
  }

  $cfg = gcal_cfg($conn);
  $j = http_json('https://oauth2.googleapis.com/token',[
    'headers'=>['Content-Type: application/x-www-form-urlencoded'],
    'post'=>http_build_query([
      'client_id'     => $cfg['client_id'],
      'client_secret' => $cfg['client_secret'],
      'grant_type'    => 'refresh_token',
      'refresh_token' => $tok['refresh_token'],
    ])
  ]);
  if (!isset($j['access_token'])) return null;

  $save = [
    'access_token'  => $j['access_token'],
    'refresh_token' => $tok['refresh_token'],
    'token_type'    => $j['token_type'] ?? 'Bearer',
    'expires_at'    => time() + (int)($j['expires_in'] ?? 3500),
    'scope'         => $tok['scope'] ?? ($j['scope'] ?? ''),
  ];

  // Si el token original era orgánico (user_id=0), renueva ahí. Si no, en el usuario.
  $targetUid = ((int)$tok['user_id'] === 0) ? 0 : $uid;
  gcal_tokens_save($conn, $targetUid, $save);
  return gcal_tokens_get($conn, $targetUid);
}

function gcal_build_auth_url(mysqli $conn, int $uid, string $state): string {
  $cfg = gcal_cfg($conn);
  $q = [
    'client_id' => $cfg['client_id'],
    'redirect_uri' => $cfg['redirect_uri'],
    'response_type' => 'code',
    'scope' => $cfg['scope'],
    'access_type' => 'offline',
    'include_granted_scopes' => 'true',
    'prompt' => 'consent',
    'state' => $state
  ];
  return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($q);
}

function gcal_api(mysqli $conn, int $uid, string $path, array $query=[]): array {
  $tok = gcal_refresh_if_needed($conn, $uid);
  if (!$tok) return ['ok'=>false,'error'=>'not_connected'];
  $u = 'https://www.googleapis.com/calendar/v3' . $path . '?' . http_build_query($query);
  return http_json($u, ['headers'=>['Authorization: Bearer '.$tok['access_token']]]);
}
