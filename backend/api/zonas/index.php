<?php
// /api/zonas/index.php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
only_methods(['GET','POST']);

$T = 'zonas_canon';
if (!table_exists($conn,$T)) json_exit(['ok'=>false,'error'=>'TABLE_MISSING:zonas_canon'],500);
$C = columns_of($conn,$T);
$hasSyn = in_array('synonyms',$C,true);
$hasNorm= in_array('normalized',$C,true);

/* GET */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $a = $_GET['a'] ?? '';
    if ($a === 'sugerir') {
        $texto = trim((string)($_GET['texto'] ?? ''));
        if ($texto === '') json_exit(['ok'=>false,'error'=>'MISSING:texto'],422);

        $norm = str_norm($texto);
        $stmt = $conn->prepare("SELECT id,nombre".($hasNorm?",normalized":"")." FROM `$T` WHERE activo=1");
        $stmt->execute(); $res = $stmt->get_result();
        $best=null;$bestScore=-1.0;
        while ($r=$res->fetch_assoc()) {
            $n = $hasNorm ? $r['normalized'] : $r['nombre'];
            $a = array_unique(explode(' ', $norm));
            $b = array_unique(explode(' ', str_norm($n)));
            $inter = count(array_intersect($a,$b));
            $union = max(1,count(array_unique(array_merge($a,$b))));
            $score = $inter/$union;
            if ($score>$bestScore) { $bestScore=$score; $best=$r; }
        }
        if (!$best) json_exit(['ok'=>false,'error'=>'NO_MATCH','score'=>0.0],404);
        json_exit(['ok'=>true,'match'=>['id'=>(int)$best['id'],'nombre'=>$best['nombre'],'score'=>$bestScore,'via'=>'heuristic']]);
    }

    $q = trim((string)($_GET['q'] ?? ''));
    if ($q==='') {
        $sql = "SELECT id,nombre".($hasNorm?",normalized":"").($hasSyn?",synonyms":"")." FROM `$T` WHERE activo=1 ORDER BY nombre ASC";
        $stmt = $conn->prepare($sql);
    } else {
        if ($hasNorm) {
            $likeN = '%'.str_norm($q).'%'; $likeName = '%'.$q.'%';
            $stmt = $conn->prepare("SELECT id,nombre,normalized".($hasSyn?",synonyms":"")." FROM `$T` WHERE activo=1 AND (normalized LIKE ? OR nombre LIKE ?) ORDER BY nombre ASC");
            $stmt->bind_param('ss',$likeN,$likeName);
        } else {
            $likeName = '%'.mb_strtolower($q,'UTF-8').'%';
            $stmt = $conn->prepare("SELECT id,nombre".($hasSyn?",synonyms":"")." FROM `$T` WHERE activo=1 AND LOWER(nombre) LIKE ? ORDER BY nombre ASC");
            $stmt->bind_param('s',$likeName);
        }
    }
    $stmt->execute(); $res=$stmt->get_result();
    $items=[]; while($r=$res->fetch_assoc()){
        $row=['id'=>(int)$r['id'],'nombre'=>$r['nombre']];
        if ($hasNorm) $row['normalized']=$r['normalized'];
        if ($hasSyn)  $row['synonyms']=$r['synonyms'];
        $items[]=$row;
    }
    json_exit(['ok'=>true,'items'=>$items]);
}

/* POST (upsert) */
need_csrf_for_write();

$nombre = trim((string)($_POST['nombre'] ?? ''));
if ($nombre==='') json_exit(['ok'=>false,'error'=>'MISSING:nombre'],422);

$normalized = str_norm($nombre);
$syn_json = null;

if ($hasSyn) {
    $synonyms = $_POST['synonyms'] ?? null;
    if (is_string($synonyms) && $synonyms!=='') $synonyms = @json_decode($synonyms,true);
    if (is_array($synonyms)) {
        $syn_norm = array_values(array_unique(array_filter(array_map('str_norm',$synonyms))));
        $syn_json = json_encode($syn_norm, JSON_UNESCAPED_UNICODE);
    }
}

/* duplicado */
if ($hasNorm) {
    $stmt = $conn->prepare("SELECT id FROM `$T` WHERE normalized=? LIMIT 1");
    $stmt->bind_param('s',$normalized);
} else {
    $lc = mb_strtolower($nombre,'UTF-8');
    $stmt = $conn->prepare("SELECT id FROM `$T` WHERE LOWER(nombre)=? LIMIT 1");
    $stmt->bind_param('s',$lc);
}
$stmt->execute(); $ex=$stmt->get_result()->fetch_assoc();
if ($ex) json_exit(['ok'=>false,'error'=>'DUPLICATE','id'=>(int)$ex['id']],409);

/* insert */
if ($hasNorm && $hasSyn) {
    $stmt = $conn->prepare("INSERT INTO `$T` (nombre,normalized,synonyms,activo) VALUES (?,?,?,1)");
    $stmt->bind_param('sss',$nombre,$normalized,$syn_json);
} elseif ($hasNorm) {
    $stmt = $conn->prepare("INSERT INTO `$T` (nombre,normalized,activo) VALUES (?,?,1)");
    $stmt->bind_param('ss',$nombre,$normalized);
} else {
    $stmt = $conn->prepare("INSERT INTO `$T` (nombre,activo) VALUES (?,1)");
    $stmt->bind_param('s',$nombre);
}
$stmt->execute();
json_exit(['ok'=>true,'id'=>(int)$conn->insert_id]);
