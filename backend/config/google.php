<?php
// /config/google.php
declare(strict_types=1);

// Usa variables de entorno si existen; si no, define aquí.
$GOOGLE_OAUTH = [
  'client_id'     => getenv('GOOGLE_CLIENT_ID')     ?: 'REEMPLAZA_CLIENT_ID',
  'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: 'REEMPLAZA_CLIENT_SECRET',
  'redirect_uri'  => getenv('GOOGLE_REDIRECT_URI')  ?: (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ($_SERVER['REQUEST_SCHEME'] ?? 'https')) . '://' . $_SERVER['HTTP_HOST'] . '/console/reservas/gcal/callback.php'),
  'scope'         => 'https://www.googleapis.com/auth/calendar.readonly',
];
