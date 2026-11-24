<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Class KeycloakAuthenticationTest
 *
 * Automated verification of IDMS Centralized Authentication (Keycloak OIDC),
 * RS256 token verification, claims validation, user sync, RBAC, and 7-tier scoping.
 */
class KeycloakAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;
    protected string $publicKey;
    protected string $cleanX5cCert;
    protected string $kid = 'idms-test-key-2026';
    protected string $realm = 'idms-gov-realm';
    protected string $issuer = 'http://localhost:8080/realms/idms-gov-realm';
    protected string $audience = 'idms-backend-api';

    protected function setUp(): void
    {
        parent::setUp();

        // Locate OpenSSL configuration file on Windows if necessary
        $configPath = null;
        $candidates = [
            'C:/wamp64/bin/php/php8.4.15/extras/ssl/openssl.cnf',
            'C:/wamp64/bin/php/php8.3.28/extras/ssl/openssl.cnf',
            'C:/wamp64/bin/apache/apache2.4.63/conf/openssl.cnf',
        ];
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $configPath = $candidate;
                putenv("OPENSSL_CONF={$candidate}");
                break;
            }
        }

        $sslOpts = $configPath ? ['config' => $configPath] : [];
        $keyOpts = array_merge($sslOpts, [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        // Generate RSA 2048-bit key pair for cryptographic RS256 tests
        $res = openssl_pkey_new($keyOpts);
        $privKey = '';
        openssl_pkey_export($res, $privKey, null, $sslOpts);
        $this->privateKey = $privKey;
        $details = openssl_pkey_get_details($res);
        $this->publicKey = $details['key'];

        // Generate a self-signed X.509 certificate to provide via mock JWKS
        $dn = [
            'countryName' => 'BD',
            'organizationName' => 'Ministry of Disaster Management and Relief',
            'commonName' => 'idms-keycloak.modmr.gov.bd',
        ];
        $csr = openssl_csr_new($dn, $res, $sslOpts);
        $x509 = openssl_csr_sign($csr, null, $res, 365, $sslOpts);
        $certOut = '';
        openssl_x509_export($x509, $certOut);

        $this->cleanX5cCert = str_replace(
            ["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n"],
            '',
            $certOut
        );

        // Pre-seed Keycloak JWKS cache
        Cache::put("keycloak:jwks:{$this->realm}", [
            [
                'kid' => $this->kid,
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'x5c' => [$this->cleanX5cCert],
            ],
        ], 3600);
    }

    /**
     * Helper to forge an RS256 JWT using our RSA private key.
     */
    protected function createToken(array $payloadOverrides = [], array $headerOverrides = []): string
    {
        $header = array_merge([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $this->kid,
        ], $headerOverrides);

        $now = time();
        $payload = array_merge([
            'sub' => 'b661334c-687f-4ca7-b24e-4b6807890501',
            'preferred_username' => 'uno_dacope',
            'name' => 'Md. Faruk Hossain (UNO)',
            'email' => 'uno.dacope@modmr.gov.bd',
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'exp' => $now + 3600,
            'nbf' => $now - 60,
            'iat' => $now,
            'roles' => ['ROLE_UNO'],
            'tier_level' => 'UPAZILA',
            'ancestry_path' => '/40/47/12/',
            'scope_id' => '01900000-0000-7000-8000-000000000012',
            'designation' => 'Upazila Nirbahi Officer',
        ], $payloadOverrides);

        $headerB64 = $this->base64UrlEncode(json_encode($header));
        $payloadB64 = $this->base64UrlEncode(json_encode($payload));
        $signingInput = "{$headerB64}.{$payloadB64}";

        if (($header['alg'] ?? '') === 'none') {
            return "{$signingInput}.";
        }

        openssl_sign($signingInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $signatureB64 = $this->base64UrlEncode($signature);

        return "{$signingInput}.{$signatureB64}";
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * 1. Missing Authorization header returns 401.
     */
    public function test_missing_bearer_token_rejected_with_401(): void
    {
        $response = $this->getJson('/api/v1/auth/user');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                ],
            ]);
    }

    /**
     * 2. Malformed token structure (not 3 parts) returns 401.
     */
    public function test_malformed_token_structure_rejected_with_401(): void
    {
        $response = $this->withToken('only.two_parts')->getJson('/api/v1/auth/user');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                ],
            ]);
    }

    /**
     * 3. Algorithm 'none' attack is strictly rejected.
     */
    public function test_insecure_algorithm_none_rejected(): void
    {
        $token = $this->createToken([], ['alg' => 'none']);

        $response = $this->withToken($token)->getJson('/api/v1/auth/user');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                ],
            ]);
    }

    /**
     * 4. Symmetric algorithm (HS256) substitution attack is rejected.
     */
    public function test_symmetric_algorithm_hs256_rejected(): void
    {
        $token = $this->createToken([], ['alg' => 'HS256']);

        $response = $this->withToken($token)->getJson('/api/v1/auth/user');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                ],
            ]);
    }

    /**
     * 5. Expired token returns 401.
     */
    public function test_expired_token_rejected(): void
    {
        $token = $this->createToken([
            'exp' => time() - 120, // Expired 2 minutes ago
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/auth/user');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                ],
            ]);
    }

    /**
     * 6. Token with future nbf (not-before) returns 401.
     */
    public function test_future_not_before_token_rejected(): void
    {
        $token = $this->createToken([
            'nbf' => time() + 300, // Not valid for another 5 minutes
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/auth/user');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                ],
            ]);
    }

    /**
     * 7. Invalid issuer returns 401.
     */
    public function test_invalid_issuer_rejected(): void
    {
        $token = $this->createToken([
            'iss' => 'http://rogue-identity-server.local/realms/untrusted',
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/auth/user');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                ],
            ]);
    }

    /**
     * 8. Invalid audience returns 401.
     */
    public function test_invalid_audience_rejected(): void
    {
        $token = $this->createToken([
            'aud' => 'different-unrelated-api-service',
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/auth/user');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                ],
            ]);
    }

    /**
     * 9. Tampered cryptographic signature returns 401.
     */
    public function test_tampered_signature_rejected(): void
    {
        $token = $this->createToken();
        $parts = explode('.', $token);

        // Corrupt signature segment
        $corruptSignature = substr($parts[2], 0, -4) . 'XXXX';
        $tamperedToken = "{$parts[0]}.{$parts[1]}.{$corruptSignature}";

        $response = $this->withToken($tamperedToken)->getJson('/api/v1/auth/user');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                ],
            ]);
    }

    /**
     * 10. Valid Keycloak RS256 token authenticates, synchronizes profile without storing password.
     */
    public function test_valid_rs256_token_authenticates_and_synchronizes_user(): void
    {
        $sub = 'e1a2b3c4-5d6e-7f8a-9b0c-1d2e3f4a5b6c';
        $token = $this->createToken([
            'sub' => $sub,
            'preferred_username' => 'uno_dacope',
            'name' => 'Md. Faruk Hossain',
            'email' => 'uno.dacope@modmr.gov.bd',
            'roles' => ['ROLE_UNO'],
            'tier_level' => 'UPAZILA',
            'ancestry_path' => '/40/47/12/',
            'designation' => 'Upazila Nirbahi Officer',
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/auth/user');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'keycloak_sub' => $sub,
                    'email' => 'uno.dacope@modmr.gov.bd',
                    'name' => 'Md. Faruk Hossain',
                    'role' => 'ROLE_UNO',
                    'designation' => 'Upazila Nirbahi Officer',
                    'is_active' => true,
                    'administrative_scope' => [
                        'tier_level' => 'UPAZILA',
                        'ancestry_path' => '/40/47/12/',
                    ],
                ],
            ]);

        // Verify in database: user exists with NO password stored
        $user = User::where('keycloak_sub', $sub)->first();
        $this->assertNotNull($user);
        $this->assertEquals('uno.dacope@modmr.gov.bd', $user->email);
        $this->assertEquals('ROLE_UNO', $user->role_code);
        $this->assertEquals('', $user->getAuthPassword());
    }

    /**
     * 11. Role-Based Access Control: UNO can access Upazila operations, but not Admin Overview.
     */
    public function test_role_based_access_control(): void
    {
        $unoToken = $this->createToken(['roles' => ['ROLE_UNO']]);
        $adminToken = $this->createToken(['roles' => ['ROLE_SUPER_ADMIN']]);

        // UNO accessing Upazila operations -> 200 OK
        $this->withToken($unoToken)
            ->getJson('/api/v1/upazila/operations')
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // UNO accessing Admin Overview -> 403 Forbidden
        $this->withToken($unoToken)
            ->getJson('/api/v1/admin/overview')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'ROLE_FORBIDDEN',
                ],
            ]);

        // Super Admin accessing Admin Overview -> 200 OK
        $this->withToken($adminToken)
            ->getJson('/api/v1/admin/overview')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * 12. Hierarchical Administrative Scope checks.
     */
    public function test_hierarchical_administrative_scope_checks(): void
    {
        // UNO of Dacope Upazila (/40/47/12/)
        $unoToken = $this->createToken([
            'roles' => ['ROLE_UNO'],
            'tier_level' => 'UPAZILA',
            'ancestry_path' => '/40/47/12/',
        ]);

        // 12a: Subordinate node (Chalna Union /40/47/12/35/) -> has_access: true
        $this->withToken($unoToken)
            ->getJson('/api/v1/auth/scope-check?target_path=/40/47/12/35/')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'has_access' => true,
            ]);

        // 12b: Lateral node (Batiaghata Upazila /40/47/13/) -> has_access: false (HTTP 403 Forbidden)
        $this->withToken($unoToken)
            ->getJson('/api/v1/auth/scope-check?target_path=/40/47/13/')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'has_access' => false,
            ]);

        // 12c: Parent District node (/40/47/) -> has_access: false (HTTP 403 Upward Boundary Enforced)
        $this->withToken($unoToken)
            ->getJson('/api/v1/auth/scope-check?target_path=/40/47/')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'has_access' => false,
            ]);

        // 12d: DRRO of Khulna District (/40/47/) has access to Dacope Upazila (/40/47/12/)
        $drroToken = $this->createToken([
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => '/40/47/',
        ]);

        $this->withToken($drroToken)
            ->getJson('/api/v1/auth/scope-check?target_path=/40/47/12/')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'has_access' => true,
            ]);

        // 12e: Super Admin / Ministry Executive (National) has universal access
        $superAdminToken = $this->createToken([
            'roles' => ['ROLE_SUPER_ADMIN'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
        ]);

        $this->withToken($superAdminToken)
            ->getJson('/api/v1/auth/scope-check?target_path=/40/47/12/35/')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'has_access' => true,
            ]);
    }
}
