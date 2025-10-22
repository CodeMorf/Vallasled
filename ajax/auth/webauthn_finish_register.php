<?php
declare(strict_types=1);
require_once __DIR__ . '/_webauthn_bootstrap.php';
json_guard();

use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialRpEntity;

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) { http_response_code(400); echo json_encode(['ok'=>0,'error'=>'JSON inválido']); exit; }

if (empty($_SESSION['webauthn_register_challenge']) || empty($_SESSION['uid'])) {
  http_response_code(400); echo json_encode(['ok'=>0,'error'=>'Sesión expirada']); exit;
}

[$serverRequest, $pkLoader, $attValidator, $assertValidator, $repo] = webauthn_services($conn);
$rpId = derive_rp_id($conn);
$rpEntity = new PublicKeyCredentialRpEntity($rpId, $rpId);
$userEntity = new PublicKeyCredentialUserEntity(
  (string)($_SESSION['uid']),
  $_SESSION['webauthn_register_userHandle'],
  'User'
);

$publicKeyCredential = $pkLoader->loadArray($data);

$creationOptions = PublicKeyCredentialCreationOptions::create(
  $rpEntity,
  $userEntity,
  $_SESSION['webauthn_register_challenge'],
  [ -7, -257 ]
)->setAuthenticatorSelection(
  ['residentKey'=>'required','requireResidentKey'=>true,'userVerification'=>'required','authenticatorAttachment'=>'platform']
)->setAttestation('none');

$source = $attValidator->check(
  $publicKeyCredential->getResponse(),
  $creationOptions,
  expected_origin(),
  null,       // token binding
  $serverRequest
);

/* Persistir */
$repo->saveCredentialSource($source);

mark_challenge_used($conn, $_SESSION['webauthn_register_challenge']);
unset($_SESSION['webauthn_register_challenge'], $_SESSION['webauthn_register_userHandle']);

if (ob_get_length()) ob_clean();
echo json_encode(['ok'=>1]);
