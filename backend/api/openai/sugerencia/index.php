<?php
declare(strict_types=1);

// DB + helpers únicos
require_once dirname(__DIR__, 3) . '/config/db.php';

// Helpers locales sin colisión
if (!function_exists('only_methods')) {
  function only_methods(array $allowed): void {
    $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($m, $allowed, true)) json_exit(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'], 405);
  }
}
if (!function_exists('need_csrf_for_write')) {
  function need_csrf_for_write(): void {
    if (strtolower($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'post') {
      start_session_safe();
      if (!csrf_ok_from_header_or_post()) json_exit(['ok'=>false,'error'=>'CSRF_FAILED'], 419);
    }
  }
}

only_methods(['GET','POST']);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { need_csrf_for_write(); }

/* === Vendor === */
$autoloads = [
  dirname(__DIR__, 3) . '/vendor/autoload.php',
  dirname(__DIR__, 2) . '/vendor/autoload.php',
  dirname(__DIR__, 1) . '/vendor/autoload.php',
];
$vendorOk = false;
foreach ($autoloads as $a) { if (is_file($a)) { require_once $a; $vendorOk = true; break; } }
if (!$vendorOk || !class_exists('\\OpenAI')) json_exit(['ok'=>false,'error'=>'OPENAI_VENDOR_MISSING'], 500);

/* === Config desde DB === */
$cfg    = cfg_get_map($conn, ['openai_api_key','openai_model']);
$apiKey = trim((string)($cfg['openai_api_key'] ?? ''));
$model  = trim((string)($cfg['openai_model'] ?? 'gpt-4.1-mini'));
if ($apiKey === '' || $model === '') json_exit(['ok'=>false,'error'=>'OPENAI_CONFIG_MISSING'], 500);

/* === Input === */
$ct = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
$input = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && strpos($ct,'application/json')!==false) {
  $raw = file_get_contents('php://input') ?: '';
  $j = json_decode($raw, true); if (is_array($j)) $input = $j;
}
$param = function(string $k, $def='') use ($input) {
  if (array_key_exists($k, $input)) return is_string($input[$k]) ? trim($input[$k]) : $input[$k];
  return isset($_REQUEST[$k]) ? (is_string($_REQUEST[$k]) ? trim($_REQUEST[$k]) : $_REQUEST[$k]) : $def;
};

$text        = (string)$param('text', '');
$target      = (string)$param('target', 'generico');
$max_tokens  = (int)($param('max_tokens', 120) ?: 120);
$temperature = (float)($param('temperature', 0.2) ?: 0.2);
if ($text === '') json_exit(['ok'=>false,'error'=>'MISSING:text'], 422);
if ($max_tokens < 16 || $max_tokens > 512) $max_tokens = 120;
if ($temperature < 0 || $temperature > 1) $temperature = 0.2;

/* === Prompt === */
$taskMap = [
  'zona'        => "Normaliza el nombre de una zona en RD y sugiere variantes útiles. Devuelve SOLO JSON {\"sugerencias\":[\"...\"]}.",
  'titulo'      => "Genera 3–6 títulos breves y claros para una ficha de valla. SOLO JSON {\"sugerencias\":[\"...\"]}.",
  'descripcion' => "Redacta 3–6 descripciones concisas para una valla publicitaria. SOLO JSON {\"sugerencias\":[\"...\"]}.",
  'resumen'     => "Resume en 1–2 líneas y sugiere 3 variantes. SOLO JSON {\"sugerencias\":[\"...\"]}.",
  'generico'    => "Propón 3–6 sugerencias útiles relacionadas. SOLO JSON {\"sugerencias\":[\"...\"]}.",
];
$task = $taskMap[$target] ?? $taskMap['generico'];

$system = "Eres un asistente estricto. Responde ÚNICAMENTE JSON UTF-8 válido con la forma {\"sugerencias\":[\"...\"]}. Sin texto adicional.";
$user   = "Instrucción: {$task}\nTexto:\n\"\"\"\n{$text}\n\"\"\"";

/* === Llamada OpenAI === */
try {
  /** @var \OpenAI\Client $client */
  $client = \OpenAI::client($apiKey);

  $resp = $client->chat()->create([
    'model' => $model,
    'messages' => [
      ['role'=>'system','content'=>$system],
      ['role'=>'user','content'=>$user],
    ],
    'temperature' => $temperature,
    'max_tokens'  => $max_tokens,
  ]);

  $content = (string)($resp->choices[0]->message->content ?? '');
  $items = [];
  $data = json_decode($content, true);
  if (is_array($data) && isset($data['sugerencias']) && is_array($data['sugerencias'])) {
    $items = array_values(array_filter(array_map('strval', $data['sugerencias'])));
  } else {
    $lines = preg_split('/\r?\n+/', trim($content));
    $lines = array_values(array_filter(array_map(fn($s)=>trim(ltrim($s, "-*•0123456789. ")), $lines)));
    if ($lines) $items = $lines;
  }

  json_exit(['ok'=>true,'via'=>'vendor','target'=>$target,'model'=>$model,'items'=>$items,'tokens_hint'=>$max_tokens]);
} catch (\Throwable $e) {
  json_exit(['ok'=>false,'error'=>'OPENAI_ERROR','detail'=>substr($e->getMessage(),0,300)], 502);
}
