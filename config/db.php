<?php
declare(strict_types=1);
/**
 * /config/db.php
 * MySQL 8.0+
 * - PDO (db())
 * - MySQLi legacy (dbi(), $conn)
 * - Helpers y branding
 * - loader.php, error.php, _assistant/boot.php
 */

/** Loader cache opcional (OFF) */
(function () {
    $loader = __DIR__ . '/load.php';
    if (is_file($loader)) {
        if (!defined('LOAD_ENABLED')) define('LOAD_ENABLED', false);
        require_once $loader;
        if (function_exists('load_boot') && LOAD_ENABLED) {
            load_boot();
            if (function_exists('load_commit')) register_shutdown_function('load_commit');
        }
    }
})();

/** Config DB */
$DB_CONFIG = [
    'host'    => getenv('DB_HOST') ?: '',
    'port'    => getenv('DB_PORT') ?: '',
    'name'    => getenv('DB_NAME') ?: '',
    'user'    => getenv('DB_USER') ?: '',
    'pass'    => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
];

/** PDO */
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    global $DB_CONFIG;
    $dsn = 'mysql:host='.$DB_CONFIG['host'].';port='.$DB_CONFIG['port'].';dbname='.$DB_CONFIG['name'].';charset='.$DB_CONFIG['charset'];
    $pdo = new PDO($dsn, $DB_CONFIG['user'], $DB_CONFIG['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$DB_CONFIG['charset']}",
    ]);
    return $pdo;
}

/** MySQLi legacy */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
function dbi(): mysqli {
    static $cx = null;
    if ($cx instanceof mysqli) return $cx;
    global $DB_CONFIG;
    $cx = new mysqli($DB_CONFIG['host'], $DB_CONFIG['user'], $DB_CONFIG['pass'], $DB_CONFIG['name'], (int)$DB_CONFIG['port']);
    $cx->set_charset($DB_CONFIG['charset']);
    return $cx;
}
/** Compatibilidad */
$conn = dbi();

/** Settings con prioridad */
function db_setting(string $key, ?string $default = null): ?string {
    $sql = "
      SELECT valor FROM (
        SELECT 1 AS pr, valor FROM web_setting   WHERE clave = :k
        UNION ALL
        SELECT 2 AS pr, valor FROM config_global WHERE clave = :k AND (activo=1 OR activo IS NULL)
        UNION ALL
        SELECT 3 AS pr, valor FROM configuracion WHERE clave = :k
      ) t
      ORDER BY pr
      LIMIT 1";
    try {
        $st = db()->prepare($sql);
        $st->execute([':k' => $key]);
        $v = $st->fetchColumn();
        return ($v === false || $v === null) ? $default : (string)$v;
    } catch (Throwable $e) {
        return $default;
    }
}

/** Helpers */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) return strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
    if (!empty($_SERVER['REQUEST_SCHEME'])) return strtolower((string)$_SERVER['REQUEST_SCHEME']) === 'https';
    return false;
}
function base_url(): string {
    $forced = db_setting('base_url', '');
    if ($forced) return rtrim($forced, '/');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $sch  = is_https() ? 'https' : 'http';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/') ?: '/', '/');
    return $sch.'://'.$host.($base ? $base : '');
}
function abs_url(string $u): string {
    $u = trim($u);
    if ($u === '') return base_url().'/';
    if (preg_match('~^https?://~i', $u)) return is_https() ? preg_replace('~^http://~i', 'https://', $u) : $u;
    if ($u[0] === '/') return base_url().$u;
    return base_url().'/'.$u;
}
function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Branding dinámico */
function load_branding(mysqli $conn): array {
    $b = ['logo_url'=>null,'logo_2x'=>null,'w'=>120,'h'=>40,'align'=>'center','title'=>'Panel'];
    $stmt = $conn->prepare("
        SELECT clave, valor
        FROM config_global
        WHERE clave IN ('logo_url','logo_2x_url','logo_width','logo_height','logo_align','site_title')
          AND (activo=1 OR activo IS NULL)
        ORDER BY id DESC
    ");
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        switch ($row['clave']) {
            case 'logo_url':    $b['logo_url'] = $row['valor']; break;
            case 'logo_2x_url': $b['logo_2x']  = $row['valor']; break;
            case 'logo_width':  $b['w']        = ctype_digit($row['valor']) ? (int)$row['valor'] : $b['w']; break;
            case 'logo_height': $b['h']        = ctype_digit($row['valor']) ? (int)$row['valor'] : $b['h']; break;
            case 'logo_align':  $b['align']    = in_array($row['valor'], ['left','center','right'], true) ? $row['valor'] : $b['align']; break;
            case 'site_title':  $b['title']    = $row['valor']; break;
        }
    }
    return $b;
}

/** Guard */
function start_session_safe(): void { if (session_status() !== PHP_SESSION_ACTIVE) session_start(); }
function require_auth(array $tipos_permitidos = ['admin','staff']): void {
    start_session_safe();
    if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], $tipos_permitidos, true)) {
        header('Location: /console/auth/login/'); exit;
    }
}

/** Módulos opcionales */
foreach ([__DIR__.'/historial.php', __DIR__.'/seo.php', __DIR__.'/debug.php'] as $f) {
    if (is_file($f)) require_once $f;
}

/** Loader animado */
$__loader = __DIR__ . '/loader.php';
if (is_file($__loader)) { require_once $__loader; if (function_exists('loader_boot')) loader_boot(); }

/** Error UI y handlers */
$__err = __DIR__ . '/error.php';
if (is_file($__err)) { require_once $__err; if (function_exists('error_boot')) error_boot(); }

/** Assistant flotante WebRTC */
$__asst = __DIR__ . '/../_assistant/boot.php';
if (is_file($__asst)) require $__asst;
