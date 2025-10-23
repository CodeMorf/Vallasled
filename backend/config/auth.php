<?php
declare(strict_types=1);

/**
 * Auth y utilidades comunes para /console/*
 * - No crea tablas nuevas.
 * - Admin único: lee de config_global (admin_email, admin_pass_hash) si existen.
 * - Fallback a constantes si no existen en DB.
 */

function cm_db(): PDO {
  $DOCROOT = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 1), '/');
  /** @var callable db() en tu /config/db.php */
  $DB = require $DOCROOT . '/config/db.php';
  if (is_callable($DB)) return $DB();
  if (function_exists('db')) return db();
  throw new RuntimeException('db() no disponible');
}

/* ===== Sesión segura ===== */
function cm_session_start(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
  ini_set('session.use_strict_mode','1');
  ini_set('session.cookie_httponly','1');
  ini_set('session.cookie_samesite','Lax');
  ini_set('session.gc_maxlifetime','86400');
  session_set_cookie_params(['lifetime'=>86400,'path'=>'/','secure'=>$isHttps,'httponly'=>true,'samesite'=>'Lax']);
  session_start();
  if (!isset($_SESSION['__regen'])) { $_SESSION['__regen']=time(); }
  if (time() - (int)$_SESSION['__regen'] > 1800) { session_regenerate_id(true); $_SESSION['__regen']=time(); }
}

/* ===== Helpers ===== */
function cm_h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function cm_json(array $a, int $code=200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($a, JSON_UNESCAPED_UNICODE);
  exit;
}

function cm_csrf_token(): string {
  cm_session_start();
  if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function cm_csrf_check(): void {
  cm_session_start();
  $ok = isset($_POST['csrf']) && isset($_SESSION['csrf']) && hash_equals((string)$_SESSION['csrf'], (string)$_POST['csrf']);
  if (!$ok) cm_json(['ok'=>false,'error'=>'csrf_invalid'], 403);
}

/* ===== Config brand + credenciales admin desde DB ===== */
function cm_brand(PDO $pdo): array {
  $out = ['logo_url'=>null,'favicon_url'=>null,'brand'=>'Console'];
  try {
    $st = $pdo->query("SELECT nombre, valor FROM config_global");
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      if ($r['nombre']==='brand')        $out['brand'] = (string)$r['valor'];
      if ($r['nombre']==='logo_url')     $out['logo_url'] = (string)$r['valor'];
      if ($r['nombre']==='favicon_url')  $out['favicon_url'] = (string)$r['valor'];
    }
  } catch(Throwable $e) {}
  return $out;
}

/** Credenciales admin: primero DB, luego constantes */
const CM_ADMIN_EMAIL_FALLBACK = 'admin@codemorf.local';
/* Reemplaza por tu hash real: password_hash('TU_CLAVE', PASSWORD_BCRYPT) */
const CM_ADMIN_PASSHASH_FALLBACK = '$2y$10$U4OqD0m7x3bQ0xW8m1a6fO1m8w8z5y3y3qj3d2Yy0hQ2q1s6oZ5nW'; // "change_me"

function cm_admin_creds(PDO $pdo): array {
  $email=null; $hash=null;
  try {
    $st = $pdo->prepare("SELECT valor FROM config_global WHERE nombre IN ('admin_email','admin_pass_hash') ORDER BY nombre");
    $st->execute();
    while($r=$st->fetch(PDO::FETCH_ASSOC)){
      // No garantizamos orden; mejor 2 consultas:
    }
    $st1=$pdo->prepare("SELECT valor FROM config_global WHERE nombre='admin_email' LIMIT 1");
    $st2=$pdo->prepare("SELECT valor FROM config_global WHERE nombre='admin_pass_hash' LIMIT 1");
    $st1->execute(); $st2->execute();
    $email=($st1->fetchColumn()) ?: null;
    $hash =($st2->fetchColumn()) ?: null;
  } catch(Throwable $e) {}
  return [
    'email' => $email ?: CM_ADMIN_EMAIL_FALLBACK,
    'hash'  => $hash  ?: CM_ADMIN_PASSHASH_FALLBACK,
  ];
}

/* ===== Estado de usuario ===== */
function cm_user(): ?array {
  cm_session_start();
  return $_SESSION['user'] ?? null;
}
function cm_is_admin(): bool {
  $u = cm_user(); return $u && ($u['role'] ?? '') === 'admin';
}
function cm_require_admin(): void {
  if (!cm_is_admin()) {
    header('Location: /console/auth/login/');
    exit;
  }
}

/* ===== Login / Logout (solo admin) ===== */
function cm_login_admin(PDO $pdo, string $email, string $pass): bool {
  $creds = cm_admin_creds($pdo);
  $ok = hash_equals(strtolower(trim($creds['email'])), strtolower(trim($email)))
        && password_verify($pass, $creds['hash']);
  if ($ok) {
    cm_session_start();
    $_SESSION['user'] = ['email'=>$creds['email'], 'role'=>'admin', 'name'=>'Admin'];
  }
  return $ok;
}
function cm_logout(): void {
  cm_session_start();
  $_SESSION=[];
  if (ini_get('session.use_cookies')) {
    $params=session_get_cookie_params();
    setcookie(session_name(),'',time()-42000,$params['path'],$params['domain']??'',$params['secure'],$params['httponly']);
  }
  session_destroy();
}
