<?php
// /console/sistema/config/ajax/opciones.php
declare(strict_types=1);
@header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 4) . '/config/db.php';
start_session_safe();
require_console_auth(['admin','staff']);

try {
  if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF inválido']); exit;
  }

  // Tabla KV
  $conn->query("CREATE TABLE IF NOT EXISTS app_config (
    clave VARCHAR(64) PRIMARY KEY,
    valor TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $defaults = [
    'site_name' => 'Vallasled.com',
    'admin_email' => 'dev@demo.com',
    'site_desc' => 'Reserva vallas LED y estáticas en RD.',
    'company_phone' => '',
    'support_whatsapp' => '',
    'google_maps_api' => '',
    'openai_api' => '',
    'openai_model' => 'gpt-4.1-mini',
    'cron_key' => '',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_user' => '',
    'smtp_pass' => '',
    'smtp_from_email' => '',
    'smtp_from_name' => '',
    'stripe_pk' => '',
    'stripe_sk' => '',
    'stripe_currency' => 'usd',
    'vendor_comision' => '10.00',
    'logo_url' => '',
    'favicon_url' => '',
    'primary_color' => '#4f46e5',
    'secondary_color' => '#111827',
    'map_provider' => 'google',
    'map_style' => 'google.roadmap',
    'map_lat' => '18.4860580',
    'map_lng' => '-69.9312120',
    'map_zoom' => '12',
    'g_client_id' => '',
    'g_client_secret' => '',
    'g_redirect_uri' => ''
  ];

  $keys = array_keys($defaults);
  $in   = implode(',', array_fill(0, count($keys), '?'));
  $types= str_repeat('s', count($keys));

  $stmt = $conn->prepare("SELECT clave, valor FROM app_config WHERE clave IN ($in)");
  $stmt->bind_param($types, ...$keys);
  $stmt->execute();
  $res = $stmt->get_result();
  $pairs = $defaults;
  while ($r = $res->fetch_assoc()) { $pairs[$r['clave']] = (string)($r['valor'] ?? ''); }
  $stmt->close();

  $data = [
    'general' => [
      'site_name'=>$pairs['site_name'],'admin_email'=>$pairs['admin_email'],'site_desc'=>$pairs['site_desc'],
      'company_phone'=>$pairs['company_phone'],'support_whatsapp'=>$pairs['support_whatsapp']
    ],
    'apis' => [
      'google_maps_api'=>$pairs['google_maps_api'],'openai_api'=>$pairs['openai_api'],
      'openai_model'=>$pairs['openai_model'],'cron_key'=>$pairs['cron_key']
    ],
    'smtp' => [
      'smtp_host'=>$pairs['smtp_host'],'smtp_port'=>$pairs['smtp_port'],'smtp_user'=>$pairs['smtp_user'],
      'smtp_pass'=>$pairs['smtp_pass'],'smtp_from_email'=>$pairs['smtp_from_email'],'smtp_from_name'=>$pairs['smtp_from_name']
    ],
    'payments' => [
      'stripe_pk'=>$pairs['stripe_pk'],'stripe_sk'=>$pairs['stripe_sk'],
      'stripe_currency'=>$pairs['stripe_currency'],'vendor_comision'=>$pairs['vendor_comision']
    ],
    'appearance' => [
      'logo_url'=>$pairs['logo_url'],'favicon_url'=>$pairs['favicon_url'],
      'primary_color'=>$pairs['primary_color'],'secondary_color'=>$pairs['secondary_color']
    ],
    'maps' => [
      'map_provider'=>$pairs['map_provider'],'map_style'=>$pairs['map_style'],
      'map_lat'=>$pairs['map_lat'],'map_lng'=>$pairs['map_lng'],'map_zoom'=>$pairs['map_zoom']
    ],
    'integrations' => [
      'g_client_id'=>$pairs['g_client_id'],'g_client_secret'=>$pairs['g_client_secret'],'g_redirect_uri'=>$pairs['g_redirect_uri']
    ]
  ];

  echo json_encode(['ok'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>'Error interno']);
}
