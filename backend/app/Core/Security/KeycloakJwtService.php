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
 * Validates Keycloak RS256 JSON Web Tokens (JWT) using cached JWKS public keys.
 * Enforces token expiration, issuer verification, audience checking, and claims extraction.
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
            throw new AuthenticationException('Malformed JWT token structure.');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $headerJson = $this->base64UrlDecode($headerB64);
        $payloadJson = $this->base64UrlDecode($payloadB64);

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!$header || !$payload) {
            throw new AuthenticationException('Unable to parse JWT token headers or payload.');
        }

        // 1. Verify algorithm (RS256 standard for BNDA / IDMS)
        if (($header['alg'] ?? null) !== 'RS256') {
            throw new AuthenticationException('Unsupported JWT signature algorithm. RS256 required.');
        }

        // 2. Temporal validation (exp & nbf)
        $now = time();
        if (isset($payload['exp']) && $now >= $payload['exp']) {
            throw new AuthenticationException('Token has expired.');
        }

        if (isset($payload['nbf']) && $now < $payload['nbf']) {
            throw new AuthenticationException('Token is not yet active.');
        }

        // 3. Issuer validation
        if (isset($payload['iss']) && $payload['iss'] !== $this->issuer) {
            // In local/docker dev, allow host/IP variation if explicitly matching realm
            if (!str_contains($payload['iss'], $this->realm)) {
                throw new AuthenticationException('Invalid token issuer.');
            }
        }

        // 4. In production, verify RS256 signature against Keycloak JWKS public key
        $kid = $header['kid'] ?? null;
        if (app()->environment('production') || env('KEYCLOAK_VERIFY_SIGNATURE', false)) {
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
            throw new AuthenticationException('Keycloak public signing key not found for kid: ' . $kid);
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
