<?php
// /console/gestion/vendors/ajax/_util.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

/* ===== JSON helpers ===== */
function json_ok(array $extra = []): void {
    json_exit(['ok' => true] + $extra, 200);
}
function json_fail(string $msg, array $fields = [], int $code = 200): void {
    json_exit(['ok' => false, 'msg' => $msg, 'fields' => $fields], $code);
}

/* ===== CSRF ===== */
function req_csrf(): void {
    if (function_exists('csrf_ok_from_header_or_post')) {
        if (!csrf_ok_from_header_or_post()) json_fail('CSRF inválido');
        return;
    }
    $sent  = $_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? '');
    $valid = function_exists('csrf_token') ? csrf_token() : ($_SESSION['csrf'] ?? '');
    if (!$sent || !$valid || !hash_equals((string)$valid, (string)$sent)) json_fail('CSRF inválido');
}

/* ===== Input helpers ===== */
function get_int(string $k, int $def = 0): int {
    return isset($_GET[$k]) && is_numeric($_GET[$k]) ? (int)$_GET[$k] : $def;
}
function post_int(string $k, int $def = 0): int {
    return isset($_POST[$k]) && is_numeric($_POST[$k]) ? (int)$_POST[$k] : $def;
}
function get_str(string $k, string $def = ''): string {
    return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $def;
}
function post_str(string $k, string $def = ''): string {
    return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $def;
}
function get_bool(string $k, ?bool $def = null): ?bool {
    if (!isset($_GET[$k])) return $def;
    $v = strtolower((string)$_GET[$k]);
    if (in_array($v, ['1','true','on','yes'], true))  return true;
    if (in_array($v, ['0','false','off','no'], true)) return false;
    return $def;
}

/* ===== SQL helpers ===== */
function safe_order(string $key, array $map, string $defaultKey): string {
    return $map[$key] ?? $map[$defaultKey];
}
function must_int(int $v, string $name = 'id'): int {
    if ($v <= 0) json_fail("Parámetro inválido: $name");
    return $v;
}
