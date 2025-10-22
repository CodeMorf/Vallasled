<?php
declare(strict_types=1);

require __DIR__ . '/_common.php';
require_csrf();

$in = read_json();
$pid = intval_or_null($in['proveedor_id'] ?? null);
$texto = trim((string)($in['texto'] ?? ''));
if (!$pid) bad('Proveedor requerido');
if ($texto === '') bad('Sin datos');

$uid = (int)($_SESSION['uid'] ?? 0);
$ins = 0;

try {
  $conn->begin_transaction();
  $lines = preg_split('/\r\n|\n|\r/', $texto);
  $stmt = $conn->prepare("INSERT INTO crm_clientes (proveedor_id, nombre, email, telefono, empresa, usuario_id, creado)
                          VALUES (?, ?, ?, ?, ?, ?, NOW())");

  foreach ($lines as $ln) {
    $ln = trim($ln);
    if ($ln === '') continue;
    // formato simple: nombre;empresa;email;telefono
    $parts = array_map('trim', explode(';', $ln));
    $nombre = $parts[0] ?? '';
    if ($nombre === '') continue;
    $empresa = $parts[1] ?? '';
    $email = $parts[2] ?? '';
    $tel = $parts[3] ?? '';
    $stmt->bind_param('isssii', $pid, $nombre, $email, $tel, $empresa, $uid);
    $stmt->execute();
    $ins++;
  }
  $stmt->close();
  $conn->commit();

  ok(['insertados'=>$ins]);
} catch (Throwable $e) {
  $conn->rollback();
  bad('Error al importar');
}
