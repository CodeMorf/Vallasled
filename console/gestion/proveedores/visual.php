<?php
declare(strict_types=1);

/**
 * visual.php - Test de guardado de proveedor (modo AJAX y directo MySQL)
 * - Si la tabla/proceso falla, muestra el error crudo de MySQL/PHP.
 * - Si AJAX falla, muestra respuesta y headers de la API.
 * - Sin dependencias JS ni CSS extra.
 */

$DOCROOT = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 3), '/');
require_once $DOCROOT . '/config/db.php';
require_console_auth(['admin','staff']);

$csrf = csrf_token();
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$base   = $scheme . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$guardar_url = $base . '/console/gestion/proveedores/ajax/guardar.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$mode   = $_POST['mode']   ?? 'remoto'; // remoto | directo
$id     = ($_POST['id'] ?? '') !== '' ? (int)$_POST['id'] : null;
$nombre = trim($_POST['nombre'] ?? '');
$contacto  = trim($_POST['contacto'] ?? '');
$email     = trim($_POST['email'] ?? '');
$telefono  = trim($_POST['telefono'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$plan_id   = ($_POST['plan_id'] ?? '') !== '' ? (int)$_POST['plan_id'] : null;

$did_post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$result = null; $error = null;

if ($did_post) {
  if ($mode === 'remoto') {
    if (!function_exists('curl_init')) {
      $error = 'cURL no disponible en PHP.';
    } else {
      $payload = [
        'id'        => $id,
        'nombre'    => $nombre ?: null,
        'contacto'  => $contacto ?: null,
        'email'     => $email ?: null,
        'telefono'  => $telefono ?: null,
        'direccion' => $direccion ?: null,
        'plan_id'   => $plan_id,
      ];
      foreach ($payload as $k=>$v) if ($v === null) unset($payload[$k]);
      $cookieHeader = $_SERVER['HTTP_COOKIE'] ?? '';
      if ($cookieHeader === '') {
        $cookieHeader = session_name() . '=' . session_id();
      }
      $ch = curl_init($guardar_url);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
          'Content-Type: application/json',
          'Accept: application/json',
          'X-Requested-With: XMLHttpRequest',
          'X-CSRF: ' . $csrf,
          'Referer: ' . $base . '/console/gestion/proveedores/visual.php',
        ],
        CURLOPT_COOKIE         => $cookieHeader,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HEADER         => true,
      ]);
      $raw = curl_exec($ch);
      if ($raw === false) {
        $error = 'cURL error: ' . curl_error($ch);
      } else {
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hslen  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers_txt = substr($raw, 0, $hslen);
        $body_txt    = substr($raw, $hslen);
        $json = json_decode($body_txt, true);
        $result = [
          'request' => [
            'url'     => $guardar_url,
            'mode'    => 'remoto',
            'headers' => ['Content-Type'=>'application/json','X-Requested-With'=>'XMLHttpRequest','X-CSRF'=>'<token>'],
            'cookies' => $cookieHeader,
            'payload' => $payload,
          ],
          'response'=> [
            'status'  => $status,
            'headers' => $headers_txt,
            'body_raw'=> $body_txt,
            'json'    => $json,
          ]
        ];
      }
      curl_close($ch);
    }
  } else {
    // ---- MODO DIRECTO: transacción MySQL ----
    mysqli_begin_transaction($conn);
    try {
      if ($id) {
        $stmt = $conn->prepare("UPDATE proveedores
          SET nombre=?, contacto=?, telefono=?, email=?, direccion=?
          WHERE id=?");
        $stmt->bind_param('sssssi', $nombre, $contacto, $telefono, $email, $direccion, $id);
        $stmt->execute();
        if ($plan_id !== null) {
          $stmt2 = $conn->prepare("
            INSERT INTO vendor_membresias (proveedor_id, plan_id, fecha_inicio, estado, pago_metodo)
            VALUES (?, ?, CURDATE(), 'activa', 'gratis')
            ON DUPLICATE KEY UPDATE plan_id=VALUES(plan_id), estado='activa'
          ");
          $stmt2->bind_param('ii', $id, $plan_id);
          $stmt2->execute();
        }
        $out = ['ok'=>true,'msg'=>'UPDATE directo OK','id'=>$id];
      } else {
        $stmt = $conn->prepare("INSERT INTO proveedores
          (nombre, contacto, telefono, email, direccion, estado)
          VALUES (?,?,?,?,?,1)");
        $stmt->bind_param('sssss', $nombre, $contacto, $telefono, $email, $direccion);
        $stmt->execute();
        $newId = $conn->insert_id;
        if ($plan_id !== null) {
          $stmt2 = $conn->prepare("
            INSERT INTO vendor_membresias (proveedor_id, plan_id, fecha_inicio, estado, pago_metodo)
            VALUES (?, ?, CURDATE(), 'activa', 'gratis')
            ON DUPLICATE KEY UPDATE plan_id=VALUES(plan_id), estado='activa'
          ");
          $stmt2->bind_param('ii', $newId, $plan_id);
          $stmt2->execute();
        }
        $out = ['ok'=>true,'msg'=>'INSERT directo OK','id'=>$newId];
      }
      mysqli_commit($conn);
      $result = ['request'=>['mode'=>'directo'],'response'=>$out];
    } catch (Throwable $e) {
      mysqli_rollback($conn);
      $result = [
        'request'=>['mode'=>'directo'],
        'error'=>[
          'message'=>$e->getMessage(),'code'=>$e->getCode(),
          'mysqli_errno'=>$conn->errno,'mysqli_error'=>$conn->error,
        ]
      ];
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="csrf" content="<?=h($csrf)?>">
  <title>Prueba guardar proveedor (visual.php)</title>
  <style>
    body { font-family: 'Segoe UI', Arial, sans-serif; margin: 2em; }
    input, select { padding: .25em; width: 250px; }
    pre { background: #f8f8f8; border: 1px solid #ccc; padding: 1em; overflow-x: auto; }
    h1, h2 { margin-bottom: 0.5em; }
    label { display: block; margin-top: 1em; }
    button { margin-top: 2em; padding: .5em 1.5em; }
    .error { color: #c00; font-weight: bold; }
  </style>
</head>
<body>
<h1>Test guardar proveedor <small>(visual.php)</small></h1>
<form method="post" autocomplete="off">
  <label>Modo:
    <select name="mode">
      <option value="remoto" <?= $mode==='remoto'?'selected':''?>>Remoto a ajax/guardar.php</option>
      <option value="directo" <?= $mode==='directo'?'selected':''?>>Directo MySQL</option>
    </select>
  </label>
  <label>ID (editar): <input name="id" value="<?=h($_POST['id'] ?? '')?>"></label>
  <label>Nombre *: <input name="nombre" value="<?=h($_POST['nombre'] ?? '')?>" required></label>
  <label>Contacto: <input name="contacto" value="<?=h($_POST['contacto'] ?? '')?>"></label>
  <label>Email: <input name="email" value="<?=h($_POST['email'] ?? '')?>"></label>
  <label>Teléfono: <input name="telefono" value="<?=h($_POST['telefono'] ?? '')?>"></label>
  <label>Dirección: <input name="direccion" value="<?=h($_POST['direccion'] ?? '')?>"></label>
  <label>Plan ID: <input name="plan_id" value="<?=h($_POST['plan_id'] ?? '')?>"></label>
  <button type="submit">Guardar prueba</button>
</form>

<?php if ($did_post): ?>
  <hr><h2>Resultado</h2>
  <pre><?php
    echo h(json_encode($error ? ['error'=>$error] : $result, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  ?></pre>
  <?php if (isset($result['error'])): ?>
    <div class="error">Error detectado, revisa arriba el detalle MySQL/PHP.</div>
  <?php endif; ?>
<?php endif; ?>

<hr>
<p>Endpoint remoto: <code><?=h($guardar_url)?></code></p>
<p>CSRF actual: <code><?=h($csrf)?></code></p>
</body>
</html>
