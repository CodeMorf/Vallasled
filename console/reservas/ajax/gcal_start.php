<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../lib/gcal.php';
start_session_safe();
header('Content-Type: application/json; charset=utf-8');

$uid=(int)($_SESSION['uid']??0);
if(!$uid){ echo json_encode(['ok'=>false,'msg'=>'No auth']); exit; }

$cfg = gcal_cfg($conn);
if ($cfg['client_id']==='' || $cfg['client_secret']==='') {
  echo json_encode(['ok'=>false,'msg'=>'Configura CLIENT_ID y SECRET en el panel']); exit;
}

$_SESSION['gcal_state']=bin2hex(random_bytes(16));
$url=gcal_build_auth_url($conn,$uid,$_SESSION['gcal_state']);
echo json_encode(['ok'=>true,'url'=>$url]);
