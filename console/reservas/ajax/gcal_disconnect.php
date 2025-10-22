<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../lib/gcal.php';
start_session_safe();
header('Content-Type: application/json; charset=utf-8');

$uid=(int)($_SESSION['uid']??0);
if(!$uid){ echo json_encode(['ok'=>false]); exit; }

echo json_encode(['ok'=>gcal_tokens_delete($conn,$uid)]);
