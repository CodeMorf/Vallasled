<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
start_session_safe();

/* ===== Utilidades JSON ===== */
function json_guard(): void {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors','0'); ini_set('html_errors','0');
    if (!ob_get_level()) ob_start();
    set_error_handler(function($s,$m,$f,$l){ throw new ErrorException($m,0,$s,$f,$l); });
    set_exception_handler(function($e){
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        echo json_encode(['ok'=>0,'error'=>$e->getMessage()]);
        exit;
    });
}
function b64u(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }
function b64u_to_bin(string $s): string { return base64_decode(strtr($s, '-_', '+/')); }

function derive_rp_id(mysqli $conn): string {
    try {
        $q = $conn->query("SELECT `value` FROM config_kv WHERE `key`='webauthn_rp_id' LIMIT 1");
        if ($q && ($r = $q->fetch_assoc()) && !empty($r['value'])) return strtolower($r['value']);
    } catch (Throwable $e) {}
    $host = strtolower(preg_replace('~:\d+$~','', $_SERVER['HTTP_HOST'] ?? 'auth.vallasled.com'));
    $p = explode('.', $host);
    return count($p) >= 3 ? implode('.', array_slice($p, -2)) : $host; // eTLD+1
}
function expected_origin(): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'auth.vallasled.com';
    return 'https://' . strtolower(preg_replace('~:\d+$~','',$host));
}

/* ===== Imports únicos (sin duplicados) ===== */
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use CBOR\Decoder;
use CBOR\Normalizers\RelaxedIntegerNormalizer;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\TokenBinding\TokenBindingNotSupportedHandler;
use Webauthn\Counter\ThrowExceptionIfInvalid;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Cose\Algorithm\Manager as CoseAlgorithmManager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\RSA\RS256;

/* ===== Repo MySQL (sin bind_param corrupto) ===== */
final class MySqlCredentialRepository implements PublicKeyCredentialSourceRepository {
    private mysqli $db;
    public function __construct(mysqli $db){ $this->db=$db; }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource {
        $sql="SELECT * FROM webauthn_credentials WHERE credential_id=? LIMIT 1";
        $stmt=$this->db->prepare($sql);
        $stmt->bind_param("s", $publicKeyCredentialId);
        $stmt->execute();
        $row=$stmt->get_result()->fetch_assoc();
        if (!$row) return null;

        return PublicKeyCredentialSource::createFromArray([
            'publicKeyCredentialId' => $row['credential_id'],
            'type'                  => 'public-key',
            'transports'            => $row['transports'] ? explode(',', $row['transports']) : [],
            'attestationType'       => $row['attestation_type'] ?: 'none',
            'trustPath'             => null,
            'aaguid'                => $row['aaguid'] ?? random_bytes(16),
            'credentialPublicKey'   => $row['public_key_cose'],
            'userHandle'            => $row['user_handle'] ?: null,
            'counter'               => (int)$row['counter'],
            'otherUI'               => [],
            'rpId'                  => $row['rp_id'],
        ]);
    }

    /** @return PublicKeyCredentialSource[] */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $userEntity): array {
        $uid = (int)$userEntity->getId();
        $res = $this->db->query("SELECT * FROM webauthn_credentials WHERE usuario_id=".$uid);
        $out=[];
        while($row=$res->fetch_assoc()){
            $out[] = PublicKeyCredentialSource::createFromArray([
                'publicKeyCredentialId' => $row['credential_id'],
                'type'                  => 'public-key',
                'transports'            => $row['transports'] ? explode(',', $row['transports']) : [],
                'attestationType'       => $row['attestation_type'] ?: 'none',
                'trustPath'             => null,
                'aaguid'                => $row['aaguid'] ?? random_bytes(16),
                'credentialPublicKey'   => $row['public_key_cose'],
                'userHandle'            => $row['user_handle'] ?: null,
                'counter'               => (int)$row['counter'],
                'otherUI'               => [],
                'rpId'                  => $row['rp_id'],
            ]);
        }
        return $out;
    }

    public function saveCredentialSource(PublicKeyCredentialSource $s): void {
        $sql="INSERT INTO webauthn_credentials
            (usuario_id, rp_id, credential_id, public_key_cose, aaguid, counter, transports, user_handle, attestation_type, att_trust_path)
            VALUES (?,?,?,?,?,?,?,?,?,?)";
        $stmt=$this->db->prepare($sql);

        $uid = (int)($_SESSION['uid'] ?? 0);
        $rp  = $s->getRpId();
        $cid = $s->getPublicKeyCredentialId();
        $pub = $s->getCredentialPublicKey();
        $aag = $s->getAaguid();
        $ctr = (int)$s->getCounter();
        $tra = $s->getTransports() ? implode(',', $s->getTransports()) : '';
        $uh  = $s->getUserHandle();
        $att = $s->getAttestationType() ?? 'none';
        $tp  = null;

        /* usar 's' para binarios también: estable en mysqli */
        $stmt->bind_param("issssissss",
            $uid, $rp, $cid, $pub, $aag, $ctr, $tra, $uh, $att, $tp
        );
        $stmt->execute();
    }

    public function updateCounter(string $publicKeyCredentialId, int $newCounter): void {
        $stmt=$this->db->prepare("UPDATE webauthn_credentials SET counter=? WHERE credential_id=? LIMIT 1");
        $stmt->bind_param("is", $newCounter, $publicKeyCredentialId);
        $stmt->execute();
    }
}

/* ===== Fábricas/validadores ===== */
function webauthn_services(mysqli $conn): array {
    $psr17 = new Psr17Factory();
    $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
    $serverRequest = $creator->fromGlobals();

    $decoder = new Decoder(new RelaxedIntegerNormalizer());
    $attObjLoader = new AttestationObjectLoader($decoder);
    $pkLoader = new PublicKeyCredentialLoader($attObjLoader);

    $attMgr = new AttestationStatementSupportManager();
    $attMgr->add(new NoneAttestationStatementSupport());

    $algMgr = new CoseAlgorithmManager([new ES256(), new RS256()]);
    $tokenBinding = new TokenBindingNotSupportedHandler();
    $extChecker = new ExtensionOutputCheckerHandler();
    $counterChecker = new ThrowExceptionIfInvalid();
    $repo = new MySqlCredentialRepository($conn);

    $attValidator = new AuthenticatorAttestationResponseValidator(
        $attMgr, $repo, $tokenBinding, $extChecker, null
    );

    $assertValidator = new AuthenticatorAssertionResponseValidator(
        $repo, $tokenBinding, $extChecker, $algMgr, $counterChecker
    );

    return [$serverRequest, $pkLoader, $attValidator, $assertValidator, $repo];
}

/* ===== Challenges ===== */
function store_challenge(mysqli $c, ?int $uid, string $type, string $ch): void {
    $stmt = $c->prepare("INSERT INTO webauthn_challenges (usuario_id, type, challenge) VALUES (?,?,?)");
    $stmt->bind_param("iss", $uid, $type, $ch);
    $stmt->execute();
}
function mark_challenge_used(mysqli $c, string $ch): void {
    $stmt = $c->prepare("UPDATE webauthn_challenges SET used=1 WHERE challenge=? LIMIT 1");
    $stmt->bind_param("s", $ch);
    $stmt->execute();
}
