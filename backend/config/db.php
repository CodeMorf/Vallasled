<?php
// /config/db.php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* === CONEXIÓN === */
$host = "localhost";
$usuario = "vallasled";
$clave = "xyhmZXYRrziHhR6R";
$base_de_datos = "vallasled";

$conn = new mysqli($host, $usuario, $clave, $base_de_datos);
$conn->set_charset("utf8mb4");

/* === BRANDING === */
function load_branding(mysqli $conn): array {
    $branding = ['logo_url'=>null,'logo_2x'=>null,'w'=>120,'h'=>40,'align'=>'center','title'=>'Panel'];
    $stmt = $conn->prepare("
        SELECT clave, valor
        FROM config_global
        WHERE clave IN ('logo_url','logo_2x_url','logo_width','logo_height','logo_align','site_title')
          AND activo=1
        ORDER BY id DESC
    ");
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        switch ($row['clave']) {
            case 'logo_url':    $branding['logo_url'] = $row['valor']; break;
            case 'logo_2x_url': $branding['logo_2x']  = $row['valor']; break;
            case 'logo_width':  $branding['w']        = ctype_digit($row['valor']) ? (int)$row['valor'] : $branding['w']; break;
            case 'logo_height': $branding['h']        = ctype_digit($row['valor']) ? (int)$row['valor'] : $branding['h']; break;
            case 'logo_align':  $branding['align']    = in_array($row['valor'], ['left','center','right'], true) ? $row['valor'] : $branding['align']; break;
            case 'site_title':  $branding['title']    = $row['valor']; break;
        }
    }
    return $branding;
}

/* === SESIÓN === */
function start_session_safe(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']);
        session_start();
    }
}

/* CSRF helpers */
function csrf_token(): string {
    start_session_safe();
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return (string)$_SESSION['csrf'];
}
function csrf_input_field(string $name='csrf', string $id='csrf'): string {
    $t = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    $n = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $i = htmlspecialchars($id,   ENT_QUOTES, 'UTF-8');
    return "<input type=\"hidden\" name=\"{$n}\" id=\"{$i}\" value=\"{$t}\">";
}

/* === JSON/AJAX === */
function wants_json(): bool {
    $xhr = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    $acc = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return $xhr === 'xmlhttprequest'
        || strpos($acc, 'application/json') !== false
        || (function_exists('str_starts_with') ? str_starts_with($uri, '/api/') : substr($uri,0,5)==='/api/');
}

/* === AUTH CONSOLA === */
function require_console_auth(array $tipos_permitidos = ['admin','staff']): void {
    start_session_safe();
    $ok = !empty($_SESSION['uid']) && !empty($_SESSION['tipo']) && in_array($_SESSION['tipo'], $tipos_permitidos, true);
    if ($ok) return;
    if (wants_json()) json_exit(['ok'=>false,'error'=>'UNAUTHORIZED'], 401);
    header("Location: /console/auth/login/"); exit;
}
function require_auth(array $tipos_permitidos = ['admin','staff']): void { require_console_auth($tipos_permitidos); }

/* === HELPERS === */
function cfg_get_map(mysqli $conn, array $keys): array {
    if (!$keys) return [];
    $place = implode(",", array_fill(0, count($keys), '?'));
    $stmt = $conn->prepare("SELECT clave, valor FROM config_global WHERE clave IN ($place) AND activo=1 ORDER BY id DESC");
    $types = str_repeat('s', count($keys));
    $stmt->bind_param($types, ...$keys);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[$r['clave']] = $r['valor'];
    return $out;
}

function csrf_ok_from_header_or_post(): bool {
    start_session_safe();
    $hdr = $_SERVER['HTTP_X_CSRF'] ?? '';
    $pst = $_POST['csrf'] ?? '';
    $tok = $hdr !== '' ? $hdr : $pst;
    return !empty($_SESSION['csrf']) && is_string($tok) && hash_equals((string)$_SESSION['csrf'], (string)$tok);
}

function json_exit(array $payload, int $code = 200): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        http_response_code($code);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
