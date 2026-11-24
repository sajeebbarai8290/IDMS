<?php

namespace App\Core\Http\Controllers\Api\V1;

use App\Core\Security\KeycloakJwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Class HealthController
 *
 * Provides RESTful system health inspection, database readiness,
 * Redis availability, and Keycloak IAM status verification for IDMS.
 *
 * @package App\Core\Http\Controllers\Api\V1
 */
class HealthController extends Controller
{
    /**
     * Complete System Health Check.
     *
     * @param KeycloakJwtService $keycloakService
     * @return JsonResponse
     */
    public function check(KeycloakJwtService $keycloakService): JsonResponse
    {
        $status = 'healthy';
        $checks = [];

        // 1. Database & PostGIS Check
        try {
            $dbStart = microtime(true);
            $dbResult = DB::selectOne('SELECT 1 as alive');
            $dbLatency = round((microtime(true) - $dbStart) * 1000, 2);

            $hasPostgis = false;
            try {
                $postgisCheck = DB::selectOne("SELECT PostGIS_Version() as version");
                $hasPostgis = !empty($postgisCheck?->version);
            } catch (Throwable) {
                // PostGIS check is soft until migration runs
            }

            $checks['database'] = [
                'status' => $dbResult ? 'UP' : 'DOWN',
                'latency_ms' => $dbLatency,
                'connection' => config('database.default'),
                'postgis_enabled' => $hasPostgis,
            ];
        } catch (Throwable $e) {
            $status = 'degraded';
            $checks['database'] = [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
                'connection' => config('database.default'),
            ];
        }

        // 2. Redis & Cache Check
        try {
            $redisStart = microtime(true);
            $cacheKey = 'idms:health:ping';
            Cache::put($cacheKey, 'pong', 10);
            $redisResult = Cache::get($cacheKey) === 'pong';
            $redisLatency = round((microtime(true) - $redisStart) * 1000, 2);

            $checks['cache_redis'] = [
                'status' => $redisResult ? 'UP' : 'DOWN',
                'latency_ms' => $redisLatency,
                'driver' => config('cache.default'),
            ];
        } catch (Throwable $e) {
            $status = 'degraded';
            $checks['cache_redis'] = [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
                'driver' => config('cache.default'),
            ];
        }

        // 3. Keycloak IAM Connectivity
        try {
            $jwksUrl = config('services.keycloak.jwks_url', env('KEYCLOAK_JWKS_URL'));
            $keycloakUp = false;
            if ($jwksUrl) {
                $key = $keycloakService->getPublicKeyByKid(null);
                $keycloakUp = !empty($key);
            }

            $checks['keycloak_iam'] = [
                'status' => $keycloakUp ? 'UP' : 'STANDBY',
                'realm' => config('services.keycloak.realm', env('KEYCLOAK_REALM')),
                'jwks_configured' => !empty($jwksUrl),
            ];
        } catch (Throwable $e) {
            $checks['keycloak_iam'] = [
                'status' => 'STANDBY',
                'error' => $e->getMessage(),
            ];
        }

        $httpCode = $status === 'healthy' ? 200 : 503;

        return response()->json([
            'success' => $status === 'healthy',
            'status' => $status,
            'service' => 'IDMS Core API',
            'version' => '1.0.0-PROPOSED',
            'environment' => app()->environment(),
            'timestamp' => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
            'checks' => $checks,
        ], $httpCode);
    }

    /**
     * System metadata and supported architectural tiers.
     *
     * @return JsonResponse
     */
    public function systemInfo(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'system' => 'Integrated Disaster Management System (IDMS)',
            'agency' => 'Ministry of Disaster Management and Relief (MoDMR)',
            'country' => 'People\'s Republic of Bangladesh',
            'api_version' => 'v1',
            'architecture' => 'Modular Monolith with Decoupled React SPA & Sovereign Keycloak IAM',
            'supported_tiers' => [
                ['tier' => 1, 'code' => 'NATIONAL', 'name_en' => 'National / Ministry', 'name_bn' => 'জাতীয় / মন্ত্রণালয়'],
                ['tier' => 2, 'code' => 'DIRECTORATE', 'name_en' => 'Directorate (DDM)', 'name_bn' => 'দুর্যোগ ব্যবস্থাপনা অধিদপ্তর'],
                ['tier' => 3, 'code' => 'DIVISION', 'name_en' => 'Division', 'name_bn' => 'বিভাগ'],
                ['tier' => 4, 'code' => 'DISTRICT', 'name_en' => 'District', 'name_bn' => 'জেলা'],
                ['tier' => 5, 'code' => 'UPAZILA', 'name_en' => 'Upazila', 'name_bn' => 'উপজেলা'],
                ['tier' => 6, 'code' => 'UNION', 'name_en' => 'Union / Pouroshava', 'name_bn' => 'ইউনিয়ন / পৌরসভা'],
                ['tier' => 7, 'code' => 'FIELD', 'name_en' => 'Field Operation / Shelter / Ward', 'name_bn' => 'মাঠ পর্যায় / আশ্রয়কেন্দ্র'],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Authenticated User Profile & Jurisdiction Status.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function authStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'keycloak_sub' => $user->keycloak_sub,
                'email' => $user->email,
                'full_name' => $user->full_name_en,
                'role' => $user->role_code,
                'administrative_scope' => $user->getAdministrativeScope(),
            ],
            'claims' => $request->attributes->get('jwt_claims'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
