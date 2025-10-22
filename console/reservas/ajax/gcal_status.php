<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../lib/gcal.php';
start_session_safe();
header('Content-Type: application/json; charset=utf-8');

$uid = (int)($_SESSION['uid'] ?? 0);
if (!$uid) { echo json_encode(['connected'=>false]); exit; }

$tok = gcal_refresh_if_needed($conn, $uid);
echo json_encode(['connected'=> (bool)$tok]);
