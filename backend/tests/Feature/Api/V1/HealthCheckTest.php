<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /**
     * Test health check endpoint returns valid JSON structure.
     */
    public function test_health_check_endpoint_responds(): void
    {
        $response = $this->getJson('/api/v1/health');

        // Can be 200 (healthy) or 503 (degraded in test without live pgsql/redis)
        $this->assertContains($response->status(), [200, 503]);

        $response->assertJsonStructure([
            'success',
            'status',
            'service',
            'version',
            'environment',
            'timestamp',
            'php_version',
            'checks' => [
                'database',
                'cache_redis',
                'keycloak_iam',
            ],
        ]);
    }

    /**
     * Test system info endpoint exposes the 7 administrative tiers.
     */
    public function test_system_info_endpoint_returns_administrative_tiers(): void
    {
        $response = $this->getJson('/api/v1/system/info');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'system' => 'Integrated Disaster Management System (IDMS)',
                'api_version' => 'v1',
            ])
            ->assertJsonStructure([
                'success',
                'system',
                'agency',
                'supported_tiers' => [
                    '*' => ['tier', 'code', 'name_en', 'name_bn'],
                ],
            ]);

        $tiers = $response->json('supported_tiers');
        $this->assertCount(7, $tiers);
        $this->assertEquals('NATIONAL', $tiers[0]['code']);
        $this->assertEquals('FIELD', $tiers[6]['code']);
    }

    /**
     * Test protected endpoint without Keycloak token returns 401 JSON envelope.
     */
    public function test_protected_auth_status_requires_keycloak_token(): void
    {
        $response = $this->getJson('/api/v1/auth/user');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'error' => ['code', 'message'],
                'timestamp',
            ]);
    }

    /**
     * Test nonexistent API route returns standardized 404 JSON response.
     */
    public function test_unknown_api_route_returns_404_json(): void
    {
        $response = $this->getJson('/api/v1/nonexistent/resource');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                ],
            ]);
    }
}
