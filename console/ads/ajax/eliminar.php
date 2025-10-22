<?php
// /console/ads/ajax/eliminar.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Allow: POST, OPTIONS');

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

function jexit(int $code, array $payload){ http_response_code($code); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if ($method !== 'POST') { jexit(405, ['error'=>'Método no permitido']); }

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  jexit(401, ['error'=>'No autorizado']);
}
if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
  jexit(403, ['error'=>'CSRF inválido']);
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) jexit(422, ['error'=>'ID inválido']);

mysqli_set_charset($conn, 'utf8mb4');

$stmt = $conn->prepare("DELETE FROM vallas_destacadas_pagos WHERE id=? LIMIT 1");
if (!$stmt) jexit(500, ['error'=>'prep']);
$stmt->bind_param('i', $id);
if (!$stmt->execute()) { $e = $stmt->error; $stmt->close(); jexit(500, ['error'=>"exec: $e"]); }

$aff = $stmt->affected_rows;
$stmt->close();

if ($aff < 1) jexit(404, ['error'=>'Registro no encontrado']);
jexit(200, ['ok'=>true, 'id'=>$id]);
