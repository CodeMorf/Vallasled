<?php
declare(strict_types=1);
require_once __DIR__ . '/_webauthn_bootstrap.php';
json_guard();

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!$payload) { http_response_code(400); echo json_encode(['ok'=>0,'error'=>'JSON inválido']); exit; }
if (empty($_SESSION['webauthn_login_challenge'])) { http_response_code(400); echo json_encode(['ok'=>0,'error'=>'Sesión expirada']); exit; }

$challenge = $_SESSION['webauthn_login_challenge'];
mark_challenge_used($conn, $challenge);
unset($_SESSION['webauthn_login_challenge']);

/* Modo “probe” para el asistente: no inicia sesión real */
if (!empty($_GET['probe'])) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['ok'=>1,'probe'=>1]);
    exit;
}

/* Inicio de sesión real por passkey: pendiente de validador WebAuthn */
if (ob_get_length()) ob_clean();
echo json_encode(['ok'=>0,'error'=>'Validación WebAuthn pendiente']);
