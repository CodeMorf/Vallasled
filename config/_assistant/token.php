<?php declare(strict_types=1);

/**
 * Devuelve un token efímero para Realtime de OpenAI.
 * Lee la clave desde DB (config_global.openai_api_key) o env OPENAI_API_KEY.
 * CORS limitado al dominio del sitio.
 */

header('Content-Type: application/json; charset=utf-8');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host   = $_SERVER['HTTP_HOST'] ?? '';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$allow  = $scheme.'://'.$host;
if ($origin && stripos($origin, $host)!==false) {
  header('Access-Control-Allow-Origin: '.$origin);
  header('Vary: Origin');
} else {
  header('Access-Control-Allow-Origin: '.$allow);
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

$root = dirname(__DIR__, 1).'/config/db.php';
if (!is_file($root)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'MISSING_DB']); exit; }
require_once $root;

try {
  $serverKey = db_setting('openai_api_key', getenv('OPENAI_API_KEY') ?: '');
  if (!$serverKey) { throw new RuntimeException('OPENAI_API_KEY not set'); }

  $body = [
    'model'       => 'gpt-4o-realtime-preview-2024-12-17',
    'voice'       => 'alloy',
    'modalities'  => ['text','audio'],
    // Opcional: restringe origen en el lado de OpenAI si está disponible en tu cuenta
    //'client'    => ['origin' => $allow]
  ];

  $ch = curl_init('https://api.openai.com/v1/realtime/sessions');
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
      'Authorization: Bearer '.$serverKey,
      'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
  ]);
  $res  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($res === false || $code >= 400) {
    throw new RuntimeException('OpenAI error '.$code.': '.$err.' '.$res);
  }

  // Respuesta incluye client_secret.value
  http_response_code(200);
  echo $res;
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'TOKEN_ERROR','message'=>$e->getMessage()]);
}
