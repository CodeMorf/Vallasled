<?php
declare(strict_types=1);
require_once __DIR__ . '/_webauthn_bootstrap.php';
json_guard();

$rpId = derive_rp_id($conn);
$challenge = random_bytes(32);

$_SESSION['webauthn_login_challenge'] = $challenge;
store_challenge($conn, null, 'login', $challenge);

$options = [
  'challenge' => b64u($challenge),
  'rpId' => $rpId,
  'userVerification' => 'required'
];

if (ob_get_length()) ob_clean();
echo json_encode(['ok'=>1,'options'=>$options,'rpId'=>$rpId,'origin'=>expected_origin()]);
