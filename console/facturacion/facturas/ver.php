<?php
declare(strict_types=1);
/** /console/facturacion/facturas/ver.php?id=123 */
require_once __DIR__ . '/../../../config/db.php';
start_session_safe();
require_auth(['admin','staff']);

function has_table(mysqli $c,string $t):bool{ $t=$c->real_escape_string($t); $rs=$c->query("SHOW TABLES LIKE '{$t}'"); return (bool)($rs&&$rs->num_rows); }
function columns(mysqli $c,string $t):array{ $m=[]; $t=$c->real_escape_string($t); if($rs=$c->query("SHOW COLUMNS FROM `{$t}`")) while($r=$rs->fetch_assoc()) $m[$r['Field']]=1; return $m; }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id<=0) { header('Location: /console/facturacion/facturas/'); exit; }

if (!has_table($conn,'facturas')) { header('HTTP/1.1 404 Not Found'); echo "No existe tabla facturas"; exit; }
$cols = columns($conn,'facturas');

$colCliente = isset($cols['usuario_id'])?'usuario_id':(isset($cols['cliente_id'])?'cliente_id':(isset($cols['user_id'])?'user_id':null));
$colFecha   = isset($cols['fecha_generada'])?'fecha_generada':(isset($cols['fecha'])?'fecha':(isset($cols['created_at'])?'created_at':null));
$colTotal   = isset($cols['monto'])?'monto':(isset($cols['total'])?'total':(isset($cols['amount'])?'amount':null));
$colValla   = isset($cols['valla_id'])?'valla_id':null;

$select = "id".($colCliente? ", {$colCliente}":"").($colFecha? ", {$colFecha}":"").($colTotal? ", {$colTotal}":"")
        .(isset($cols['descuento'])?", descuento":"").(isset($cols['precio_personalizado'])?", precio_personalizado":"")
        .(isset($cols['estado'])?", estado":"").(isset($cols['metodo_pago'])?", metodo_pago":"").($colValla? ", {$colValla}":"")
        .(isset($cols['notas'])?", notas":"");
$stmt=$conn->prepare("SELECT {$select} FROM facturas WHERE id=? LIMIT 1");
$stmt->bind_param('i',$id); $stmt->execute(); $fact=$stmt->get_result()->fetch_assoc(); $stmt->close();
if(!$fact){ header('HTTP/1.1 404 Not Found'); echo "Factura no encontrada"; exit; }

$clienteNombre='Cliente'; $clienteRNC='';
if ($colCliente && $fact[$colCliente]) {
  $cid=(int)$fact[$colCliente];
  if (has_table($conn,'clientes')) {
    $cc=columns($conn,'clientes'); $cNom=isset($cc['nombre'])?'nombre':null; $cMail=isset($cc['correo'])?'correo':null; $cR=isset($cc['rnc'])?'rnc':null;
    $sel="id".($cNom?", {$cNom}":"").($cMail?", {$cMail}":"").($cR?", {$cR}":"");
    $s=$conn->prepare("SELECT {$sel} FROM clientes WHERE id=? LIMIT 1"); $s->bind_param('i',$cid); $s->execute(); $r=$s->get_result()->fetch_assoc(); $s->close();
    if($r){ $clienteNombre=$r[$cNom]??($r[$cMail]??"Cliente #{$cid}"); $clienteRNC=$r[$cR]??''; }
  } elseif (has_table($conn,'usuarios')) {
    $uu=columns($conn,'usuarios'); $uNom=isset($uu['nombre'])?'nombre':null; $uMail=isset($uu['email'])?'email':null;
    $sel="id".($uNom?", {$uNom}":"").($uMail?", {$uMail}":"");
    $s=$conn->prepare("SELECT {$sel} FROM usuarios WHERE id=? LIMIT 1"); $s->bind_param('i',$cid); $s->execute(); $r=$s->get_result()->fetch_assoc(); $s->close();
    if($r){ $clienteNombre=$r[$uNom]??($r[$uMail]??"Cliente #{$cid}"); }
  }
}

$branding = load_branding($conn);
$empresa = $branding['title'] ?: 'VallasLed';
$logo = $branding['logo_url'] ?: 'https://placehold.co/150x50/111827/FFFFFF?text=Vallas+Admin';

$fecha = $colFecha? $fact[$colFecha] : date('Y-m-d');
$total = $colTotal? (float)$fact[$colTotal] : 0.0;
$desc  = isset($fact['descuento'])? (float)$fact['descuento'] : 0.0;

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Factura #<?=h($id)?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
<link rel="stylesheet" href="/console/asset/css/facturacion/crear.css">
</head>
<body class="bg-[var(--main-bg)] text-[var(--text-primary)]" style="font-family:'Inter',sans-serif">
<div class="max-w-5xl mx-auto p-6">
  <div class="flex items-start justify-between border-b border-[var(--border-color)] pb-4">
    <div>
      <p class="font-bold text-2xl"><?=h($empresa)?></p>
      <p class="text-sm text-[var(--text-secondary)]">Factura #<?=h($id)?></p>
      <p class="text-sm text-[var(--text-secondary)]">Fecha: <?=h($fecha)?></p>
    </div>
    <img src="<?=h($logo)?>" alt="Logo" class="h-10">
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
    <div>
      <p class="text-xs text-[var(--text-secondary)]">FACTURAR A:</p>
      <p class="font-semibold"><?=h($clienteNombre)?></p>
      <p class="text-sm text-[var(--text-secondary)]"><?= $clienteRNC? 'RNC: '.h($clienteRNC):'' ?></p>
    </div>
    <div class="text-right">
      <p class="text-sm text-[var(--text-secondary)]">Estado: <span class="font-medium"><?=h($fact['estado'] ?? ($fact['status'] ?? 'pendiente'))?></span></p>
      <p class="text-sm text-[var(--text-secondary)]">Método: <span class="font-medium"><?=h($fact['metodo_pago'] ?? 'transferencia')?></span></p>
    </div>
  </div>

  <div class="mt-6 border border-[var(--border-color)] rounded-lg overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-[var(--main-bg)]">
        <tr><th class="p-3 text-left font-semibold">Descripción</th><th class="p-3 text-right font-semibold">Subtotal</th></tr>
      </thead>
      <tbody>
        <tr class="border-b border-[var(--border-color)]">
          <td class="p-3">Servicio de Publicidad<?= isset($fact['notas']) && $fact['notas']? ' - '.h($fact['notas']) : '' ?></td>
          <td class="p-3 text-right">
            <?php $fmt=function($n){ return number_format((float)$n,2,',','.'); };
              echo 'DOP '.$fmt($total + max(0,$desc));
            ?>
          </td>
        </tr>
      </tbody>
      <tfoot>
        <tr><td class="p-3 text-right font-medium">Descuento:</td><td class="p-3 text-right">DOP <?=$fmt($desc)?></td></tr>
        <tr class="text-lg font-bold text-[var(--text-primary)]">
          <td class="p-3 text-right">Total:</td><td class="p-3 text-right">DOP <?=$fmt($total)?></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="mt-6 flex flex-wrap gap-3">
    <a href="/console/facturacion/facturas/" class="px-4 py-2 rounded-lg bg-gray-200 text-gray-800 hover:bg-gray-300">Volver</a>
    <button onclick="window.print()" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Imprimir</button>
    <button id="copy-link" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-800 hover:bg-gray-200">Copiar enlace</button>
  </div>
</div>

<script>
document.getElementById('copy-link')?.addEventListener('click', ()=>{
  const ta=document.createElement('textarea');
  ta.value=location.href; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
  alert('Enlace copiado');
});
</script>
</body>
</html>
