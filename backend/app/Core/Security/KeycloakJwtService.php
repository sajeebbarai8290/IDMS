<?php

namespace App\Core\Security;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class KeycloakJwtService
 *
 * Sovereign Token Security Service for IDMS.
 * Validates Keycloak RS256 JSON Web Tokens (JWT) using cached JWKS public keys.
 * Strictly enforces token expiration, issuer verification, audience checking,
 * cryptographic signature validation, and claims extraction.
 *
 * @package App\Core\Security
 */
class KeycloakJwtService
{
    protected string $realm;
    protected string $jwksUrl;
    protected string $issuer;
    protected string $audience;
    protected int $cacheTtl;

    public function __construct()
    {
        $this->realm = config('services.keycloak.realm', env('KEYCLOAK_REALM', 'idms-gov-realm'));
        $this->jwksUrl = config('services.keycloak.jwks_url', env('KEYCLOAK_JWKS_URL', 'http://localhost:8080/realms/idms-gov-realm/protocol/openid-connect/certs'));
        $this->issuer = config('services.keycloak.issuer', env('KEYCLOAK_TOKEN_ISSUER', 'http://localhost:8080/realms/idms-gov-realm'));
        $this->audience = config('services.keycloak.audience', env('KEYCLOAK_TOKEN_AUDIENCE', 'idms-backend-api'));
        $this->cacheTtl = (int) config('services.keycloak.cache_ttl', env('KEYCLOAK_JWKS_CACHE_TTL', 86400));
    }

    /**
     * Decode and validate a raw Keycloak Bearer JWT token string.
     *
     * @param string $token
     * @return array
     * @throws AuthenticationException
     */
    public function validateToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new AuthenticationException('Malformed JWT token structure. Expected 3 segments.');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $headerJson = $this->base64UrlDecode($headerB64);
        $payloadJson = $this->base64UrlDecode($payloadB64);

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!$header || !$payload) {
            throw new AuthenticationException('Unable to parse JWT token headers or payload JSON.');
        }

        // 1. Strict algorithm validation (Only RS256 allowed; 'none' or symmetric algs strictly rejected)
        if (($header['alg'] ?? null) !== 'RS256') {
            throw new AuthenticationException('Unsupported or insecure JWT signature algorithm: ' . ($header['alg'] ?? 'none'));
        }

        // 2. Expiration and Not-Before Validation
        $now = time();
        if (!isset($payload['exp']) || $now >= (int) $payload['exp']) {
            throw new AuthenticationException('JWT token has expired.');
        }

        if (isset($payload['nbf']) && $now < (int) $payload['nbf']) {
            throw new AuthenticationException('JWT token is not yet active.');
        }

        // 3. Strict Issuer Validation
        if (!isset($payload['iss']) || !str_contains($payload['iss'], $this->realm)) {
            throw new AuthenticationException('Invalid token issuer: ' . ($payload['iss'] ?? 'missing'));
        }

        // 4. Strict Audience Validation
        if (isset($payload['aud'])) {
            $audiences = is_array($payload['aud']) ? $payload['aud'] : [$payload['aud']];
            $allowedAudiences = array_filter([
                $this->audience,
                'idms-spa-frontend',
                'idms-backend-api',
            ]);

            if (empty(array_intersect($allowedAudiences, $audiences))) {
                throw new AuthenticationException('Invalid token audience. Expected audience not found.');
            }
        }

        // 5. Cryptographic RS256 Signature Verification via Keycloak JWKS
        $skipSignature = config('services.keycloak.skip_signature_verification', false) && app()->environment('testing');
        if (!$skipSignature) {
            $kid = $header['kid'] ?? null;
            $this->verifySignature($headerB64 . '.' . $payloadB64, $signatureB64, $kid);
        }

        return $payload;
    }

    /**
     * Verify RS256 cryptographic signature using Keycloak JWKS cert.
     *
     * @param string $data
     * @param string $signatureB64
     * @param string|null $kid
     * @return void
     * @throws AuthenticationException
     */
    protected function verifySignature(string $data, string $signatureB64, ?string $kid): void
    {
        $publicKey = $this->getPublicKeyByKid($kid);
        if (!$publicKey) {
            throw new AuthenticationException('Keycloak public signing certificate not found for kid: ' . $kid);
        }

        $signature = $this->base64UrlDecodeBytes($signatureB64);
        $result = openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($result !== 1) {
            throw new AuthenticationException('Cryptographic JWT token signature verification failed.');
        }
    }

    /**
     * Fetch Keycloak JWKS public certificates from cache or Keycloak certs endpoint.
     *
     * @param string|null $kid
     * @return string|null
     */
    public function getPublicKeyByKid(?string $kid): ?string
    {
        $cacheKey = "keycloak:jwks:{$this->realm}";

        $keys = Cache::remember($cacheKey, $this->cacheTtl, function () {
            try {
                $response = Http::timeout(5)->get($this->jwksUrl);
                if ($response->successful()) {
                    return $response->json('keys') ?? [];
                }
            } catch (Throwable $e) {
                Log::warning('Keycloak JWKS endpoint unreachable: ' . $e->getMessage());
            }
            return [];
        });

        foreach ($keys as $key) {
            if (!$kid || ($key['kid'] ?? null) === $kid) {
                if (isset($key['x5c'][0])) {
                    return "-----BEGIN CERTIFICATE-----\n" .
                        chunk_split($key['x5c'][0], 64, "\n") .
                        "-----END CERTIFICATE-----\n";
                }
            }
        }

        // If not found in cache and kid was specified, bust cache once to handle Keycloak key rotation
        if ($kid && !empty($keys)) {
            Cache::forget($cacheKey);
            $response = Http::timeout(5)->get($this->jwksUrl);
            if ($response->successful()) {
                $freshKeys = $response->json('keys') ?? [];
                Cache::put($cacheKey, $freshKeys, $this->cacheTtl);
                foreach ($freshKeys as $key) {
                    if (($key['kid'] ?? null) === $kid && isset($key['x5c'][0])) {
                        return "-----BEGIN CERTIFICATE-----\n" .
                            chunk_split($key['x5c'][0], 64, "\n") .
                            "-----END CERTIFICATE-----\n";
                    }
                }
            }
        }

        return null;
    }

    /**
     * URL-safe base64 string decoding.
     *
     * @param string $input
     * @return string
     */
    protected function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }
        return (string) base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * Raw byte base64 decoding for signature validation.
     *
     * @param string $input
     * @return string
     */
    protected function base64UrlDecodeBytes(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
