<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

$out = ['ok' => false, 'error' => 'Error desconocido'];

try {
    // CSRF
    $csrf = $_POST['csrf'] ?? '';
    if (!$csrf || $csrf !== ($_SESSION['csrf'] ?? '')) {
        throw new Exception('CSRF inválido.');
    }

    // Datos
    $email    = trim($_POST['email'] ?? '');
    $pass1    = $_POST['password'] ?? '';
    $pass2    = $_POST['password2'] ?? '';

    // Validaciones
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email inválido.');
    if (strlen($pass1) < 8 || strlen($pass1) > 128) throw new Exception('Contraseña inválida.');
    if ($pass1 !== $pass2) throw new Exception('Las contraseñas no coinciden.');

    // (Opcional) Verifica si el email ya existe como admin
    $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE email=? AND tipo='admin'");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($count); $stmt->fetch(); $stmt->close();
    if ($count > 0) throw new Exception('Ya existe un admin con ese email.');

    // Hash de contraseña
    $hash = password_hash($pass1, PASSWORD_DEFAULT);

    // Solo las columnas que existen en tu tabla
    $q = $conn->prepare("INSERT INTO usuarios (email, clave, tipo, activo) VALUES (?, ?, 'admin', 1)");
    $q->bind_param('ss', $email, $hash);
    $q->execute();
    $out['ok'] = $q->insert_id > 0;
    $out['redirect'] = '/console/portal/index.php';

    if (!$out['ok']) throw new Exception('No se pudo guardar.');
    $out['error'] = null;
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
