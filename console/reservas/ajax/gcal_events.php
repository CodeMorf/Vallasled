<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../lib/gcal.php';
start_session_safe();
header('Content-Type: application/json; charset=utf-8');

$uid = (int)($_SESSION['uid'] ?? 0);
if(!$uid){ echo json_encode(['ok'=>false,'items'=>[]]); exit; }

$timeMin = $_GET['time_min'] ?? null;
$timeMax = $_GET['time_max'] ?? null;
if(!$timeMin || !$timeMax){
  // rango por defecto: mes actual +/- 1 semana
  $timeMin = date('c', strtotime('first day of this month -7 days'));
  $timeMax = date('c', strtotime('last day of this month +7 days'));
}

$res = gcal_api($conn,$uid,'/calendars/primary/events',[
  'timeMin'=>$timeMin,
  'timeMax'=>$timeMax,
  'singleEvents'=>'true',
  'orderBy'=>'startTime',
  'maxResults'=>2500
]);

if(isset($res['items'])) echo json_encode(['ok'=>true,'items'=>$res['items']]);
else echo json_encode(['ok'=>false,'items'=>[], 'error'=>$res['error'] ?? 'api_error']);
