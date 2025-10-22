<?php declare(strict_types=1);

/**
 * /config/seo.php
 * - Meta SEO server-side (title, description, canonical, OG/Twitter, JSON-LD)
 * - Palabras clave desde DB (tabla `keywords` del dump enviado)
 * Requiere: funciones h(), db(), db_setting() / web_setting() si existen.
 */

/* ==== Helpers seguros ==== */
if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('s')) {
  function s($v): string { return trim((string)$v); }
}

/* ==== Config getters con fallback ==== */
function seo_get(string $key, ?string $default=null): string {
  if (function_exists('web_setting')) { $v = web_setting($key, null); if ($v!==null) return (string)$v; }
  if (function_exists('db_setting'))  { $v = db_setting($key, $default); return (string)$v; }
  return (string)$default;
}

/* ==== URLs absolutas ==== */
function seo_base_url(): string {
  $u = seo_get('site_url', '');
  if ($u==='') {
    $sch  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$sch://$host";
  }
  return rtrim($u,'/');
}
function seo_abs(string $path): string {
  if (preg_match('~^https?://~i',$path)) return $path;
  return seo_base_url() . '/' . ltrim($path,'/');
}
function seo_url(string $u, string $fallback): string {
  $u = s($u);
  if ($u==='') return $fallback;
  return preg_match('~^https?://~i',$u) ? $u : seo_abs($u);
}

/* ==== Defaults de la página ==== */
function seo_defaults(): array {
  $brand = seo_get('site_name','Vallasled.com');
  $desc  = seo_get('site_description','Descubre y alquila vallas LED y estáticas en República Dominicana.');
  $path  = $_SERVER['REQUEST_URI'] ?? '/';
  return [
    'title'       => $brand,
    'brand'       => $brand,
    'description' => mb_substr($desc,0,160),
    'canonical'   => seo_abs($path),
    'locale'      => seo_get('site_locale','es_DO'),
    'image'       => seo_url(seo_get('site_logo_url','/assets/logo.png'), seo_abs('/assets/logo.png')),
    'twitter'     => seo_get('site_twitter','@vallasled'),
    'og_type'     => 'website',
    'robots'      => 'index,follow',
    'site_url'    => seo_base_url(),
    'search_url'  => seo_abs('/buscar?q={search_term_string}'),
  ];
}

/* ==== Normalización ligera para búsqueda ==== */
function kw_normalize(string $q): string {
  $q = mb_strtolower($q, 'UTF-8');
  $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n'];
  $q = strtr($q, $map);
  $q = preg_replace('~[^a-z0-9/\+\-\s]~u',' ', $q);
  $q = preg_replace('~\s+~',' ', $q);
  return trim($q);
}

/* ==== Palabras clave desde DB (tabla `keywords`) ==== */
/**
 * Requisitos mínimos del SQL que enviaste:
 *   CREATE TABLE keywords (
 *     id BIGINT AUTO_INCREMENT PRIMARY KEY,
 *     keyword VARCHAR(191) NOT NULL,
 *     normalized VARCHAR(191) NOT NULL,
 *     intent ENUM('informational','commercial','navigational','local') NULL,
 *     volume INT NULL,
 *     UNIQUE KEY uq_norm (normalized),
 *     FULLTEXT KEY ft_kw (keyword, normalized)
 *   );
 */

/** Top N keywords para sugerir o enlazar internamente */
function seo_kw_top(int $limit=50): array {
  $out = [];
  try {
    if (!function_exists('db')) return $out;
    $pdo = db();
    $sql = "SELECT keyword, normalized, COALESCE(volume,0) AS volume, intent
            FROM keywords
            ORDER BY volume DESC, CHAR_LENGTH(keyword) ASC
            LIMIT :lim";
    $st = $pdo->prepare($sql);
    $st->bindValue(':lim', max(1,min(500,$limit)), PDO::PARAM_INT);
    $st->execute();
    $out = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { /* silencio seguro */ }
  return $out;
}

/** Búsqueda FULLTEXT en `keywords` (modo booleano con prefijo *) */
function seo_kw_search(string $q, int $limit=20): array {
  $out = [];
  $q0 = kw_normalize($q);
  if ($q0==='') return $out;

  // Construir consulta booleana: cada token como +token*
  $parts = preg_split('~\s+~', $q0);
  $boolean = implode(' ', array_map(fn($t)=>'+'.$t.'*', array_slice($parts,0,8)));

  try {
    if (!function_exists('db')) return $out;
    $pdo = db();
    $sql = "SELECT keyword, normalized, COALESCE(volume,0) AS volume, intent
            FROM keywords
            WHERE MATCH(keyword, normalized) AGAINST(:q IN BOOLEAN MODE)
            ORDER BY volume DESC, keyword ASC
            LIMIT :lim";
    $st = $pdo->prepare($sql);
    $st->bindValue(':q', $boolean, PDO::PARAM_STR);
    $st->bindValue(':lim', max(1,min(200,$limit)), PDO::PARAM_INT);
    $st->execute();
    $out = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { /* silencio seguro */ }
  return $out;
}

/** Palabras clave para una página usando tabla opcional page_keywords */
function seo_kw_for_page(?int $page_id, int $limit=20): array {
  $out = [];
  if (!$page_id) return $out;
  try {
    if (!function_exists('db')) return $out;
    $pdo = db();
    // Si no existe la tabla, no falla gravemente
    $sql = "SELECT k.keyword, k.normalized, COALESCE(k.volume,0) AS volume, k.intent, pk.score
            FROM page_keywords pk
            JOIN keywords k ON k.id = pk.keyword_id
            WHERE pk.page_id = :pid
            ORDER BY pk.score DESC, k.volume DESC
            LIMIT :lim";
    $st = $pdo->prepare($sql);
    $st->bindValue(':pid', $page_id, PDO::PARAM_INT);
    $st->bindValue(':lim', max(1,min(200,$limit)), PDO::PARAM_INT);
    $st->execute();
    $out = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { /* tabla opcional, continuar */ }
  return $out;
}

/* ==== Construcción de meta y JSON-LD ==== */
function seo_page(array $o=[]): array {
  $m = array_merge(seo_defaults(), $o);
  $m['title']       = mb_substr(s($m['title']),0,60);
  $m['description'] = mb_substr(s($m['description']),0,160);
  $m['canonical']   = seo_url((string)$m['canonical'],$m['canonical']);
  $m['image']       = seo_url((string)$m['image'],$m['image']);
  $m['og_type']     = in_array($m['og_type'], ['website','article'], true) ? $m['og_type'] : 'website';
  $m['robots']      = ($m['robots'] === 'noindex') ? 'noindex,nofollow' : 'index,follow';
  return $m;
}

function seo_head(array $m, array $opts=[]): string {
  // $opts['emit_meta_keywords']=true para emitir <meta name="keywords"> (no recomendado por Google)
  $emit_meta_keywords = !empty($opts['emit_meta_keywords']);

  $t = [];
  $t[] = '<title>'.h($m['title']).'</title>';
  $t[] = '<meta name="description" content="'.h($m['description']).'">';
  $t[] = '<link rel="canonical" href="'.h($m['canonical']).'">';
  $t[] = '<meta name="robots" content="'.h($m['robots']).'">';

  if ($emit_meta_keywords && !empty($opts['keywords']) && is_array($opts['keywords'])) {
    // Limitar a ~15 términos, coma-separados
    $kw = array_slice(array_unique(array_map('s', $opts['keywords'])), 0, 15);
    $t[] = '<meta name="keywords" content="'.h(implode(', ', $kw)).'">';
  }

  // Open Graph
  $t[] = '<meta property="og:title" content="'.h($m['title']).'">';
  $t[] = '<meta property="og:description" content="'.h($m['description']).'">';
  $t[] = '<meta property="og:type" content="'.h($m['og_type']).'">';
  $t[] = '<meta property="og:url" content="'.h($m['canonical']).'">';
  $t[] = '<meta property="og:image" content="'.h($m['image']).'">';
  $t[] = '<meta property="og:site_name" content="'.h($m['brand']).'">';
  $t[] = '<meta property="og:locale" content="'.h($m['locale']).'">';

  // Twitter
  $t[] = '<meta name="twitter:card" content="summary_large_image">';
  $t[] = '<meta name="twitter:site" content="'.h($m['twitter']).'">';
  $t[] = '<meta name="twitter:title" content="'.h($m['title']).'">';
  $t[] = '<meta name="twitter:description" content="'.h($m['description']).'">';
  $t[] = '<meta name="twitter:image" content="'.h($m['image']).'">';

  // JSON-LD: Organization + WebSite+SearchAction
  $org = [
    '@context'=>'https://schema.org',
    '@type'=>'Organization',
    'name'=>$m['brand'],
    'url'=>$m['site_url'],
    'logo'=>$m['image']
  ];
  $site = [
    '@context'=>'https://schema.org',
    '@type'=>'WebSite',
    'name'=>$m['brand'],
    'url'=>$m['site_url'],
    'potentialAction'=>[
      '@type'=>'SearchAction',
      'target'=>$m['search_url'],
      'query-input'=>'required name=search_term_string'
    ]
  ];
  $t[] = '<script type="application/ld+json">'.json_encode($org, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).'</script>';
  $t[] = '<script type="application/ld+json">'.json_encode($site, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).'</script>';

  return implode("\n", $t)."\n";
}

/** Echo directo */
function seo_emit(array $overrides=[], array $opts=[]): void {
  echo seo_head(seo_page($overrides), $opts);
}

/* ==== Utilidades para vistas ==== */
/**
 * Devuelve hasta $n keywords locales para enlazado interno o meta opcional.
 * Si $q está vacío, usa top por volumen.
 */
function seo_keywords(?string $q=null, int $n=20): array {
  if ($q===null || trim($q)==='') return array_map(fn($r)=>$r['keyword'], seo_kw_top($n));
  return array_map(fn($r)=>$r['keyword'], seo_kw_search($q, $n));
}

/**
 * Render opcional de lista de keywords como enlaces internos
 * $hrefGen recibe el texto y debe devolver el href final.
 */
function seo_render_keywords(array $keywords, callable $hrefGen): string {
  if (!$keywords) return '';
  $buf = [];
  foreach ($keywords as $k) {
    $href = (string)$hrefGen($k);
    $buf[] = '<a class="tag tag--kw" href="'.h($href).'">'.h($k).'</a>';
  }
  return '<nav class="kw-cloud">'.implode(' ', $buf).'</nav>';
}
