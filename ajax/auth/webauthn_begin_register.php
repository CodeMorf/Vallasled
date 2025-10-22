<?php
declare(strict_types=1);
require_once __DIR__ . '/_webauthn_bootstrap.php';
json_guard();

if (empty($_SESSION['uid'])) { http_response_code(401); echo json_encode(['ok'=>0,'error'=>'No autenticado']); exit; }

$rpId = derive_rp_id($conn);
$uid  = (int)$_SESSION['uid'];

$stmt = $conn->prepare("SELECT email, COALESCE(nombre,email) AS nombre FROM usuarios WHERE id=? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
if (!$u) { http_response_code(400); echo json_encode(['ok'=>0,'error'=>'Usuario inexistente']); exit; }

$challenge = random_bytes(32);
$userId    = random_bytes(32);

$_SESSION['webauthn_register_challenge'] = $challenge;
$_SESSION['webauthn_register_userHandle'] = $userId;
store_challenge($conn, $uid, 'register', $challenge);

$options = [
  'rp' => ['id'=>$rpId, 'name'=>$rpId],
  'user' => ['id'=> b64u($userId), 'name'=>$u['email'], 'displayName'=>$u['nombre']],
  'challenge' => b64u($challenge),
  'pubKeyCredParams' => [
    ['type'=>'public-key','alg'=>-7], ['type'=>'public-key','alg'=>-257],
  ],
  'authenticatorSelection' => [
    'residentKey' => 'required','requireResidentKey'=>true,'userVerification'=>'required','authenticatorAttachment'=>'platform'
  ],
  'attestation' => 'none',
  'timeout' => 60000
];

if (ob_get_length()) ob_clean();
echo json_encode(['ok'=>1,'options'=>$options,'rpId'=>$rpId,'origin'=>expected_origin()]);
