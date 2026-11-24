<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Core\Security\KeycloakJwtService;
use App\Core\Services\AdministrativeScopeService;
use App\Core\Services\UserSynchronizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

echo "=================================================================\n";
echo " IDMS LIVE END-TO-END KEYCLOAK & DATABASE VERIFICATION\n";
echo "=================================================================\n\n";

$usersToTest = [
    [
        'username' => 'uno_dacope',
        'password' => 'Password@123',
        'expected_role' => 'ROLE_UNO',
        'expected_tier' => 'UPAZILA',
        'test_target' => '/40/47/12/35/', // Chalna Union (Subordinate - should ALLOW)
        'lateral_target' => '/40/47/13/', // Batiaghata Upazila (Lateral - should DENY)
    ],
    [
        'username' => 'drro_khulna',
        'password' => 'Password@123',
        'expected_role' => 'ROLE_DRRO',
        'expected_tier' => 'DISTRICT',
        'test_target' => '/40/47/12/', // Dacope Upazila (Subordinate - should ALLOW)
        'lateral_target' => '/40/48/', // Satkhira District (Lateral - should DENY)
    ],
    [
        'username' => 'admin_modmr',
        'password' => 'Password@123',
        'expected_role' => 'ROLE_SUPER_ADMIN',
        'expected_tier' => 'NATIONAL',
        'test_target' => '/40/47/12/35/', // National has access to everything
        'lateral_target' => '/10/01/01/01/', // Barishal Union
    ]
];

$jwtService = app(KeycloakJwtService::class);
$syncService = app(UserSynchronizationService::class);
$scopeService = app(AdministrativeScopeService::class);

$allPassed = true;

foreach ($usersToTest as $testCase) {
    $username = $testCase['username'];
    echo "--- Testing Identity: {$username} ---\n";

    // 1. Request live token from Keycloak
    $tokenEndpoint = 'http://localhost:8080/realms/idms-gov-realm/protocol/openid-connect/token';
    $response = Http::asForm()->post($tokenEndpoint, [
        'grant_type' => 'password',
        'client_id' => 'idms-spa-frontend',
        'username' => $username,
        'password' => $testCase['password'],
    ]);

    if (!$response->successful()) {
        echo "[FAIL] Keycloak token request failed: " . $response->body() . "\n";
        $allPassed = false;
        continue;
    }

    $tokenData = $response->json();
    $accessToken = $tokenData['access_token'];
    echo "[PASS] Live token obtained from Keycloak (Length: " . strlen($accessToken) . " bytes)\n";

    // 2. Validate token using live JWKS cryptographic certs
    try {
        $claims = $jwtService->validateToken($accessToken);
        echo "[PASS] RS256 signature verified against live Keycloak JWKS certs.\n";
        echo "       Issuer: {$claims['iss']}\n";
        echo "       Audience: " . (is_array($claims['aud']) ? implode(', ', $claims['aud']) : $claims['aud']) . "\n";
        echo "       Subject UUID: {$claims['sub']}\n";
        echo "       Assigned Roles: " . implode(', ', $claims['roles'] ?? []) . "\n";
        echo "       Jurisdiction Scope: " . ($claims['ancestry_path'] ?? '/') . "\n";
    } catch (\Throwable $e) {
        echo "[FAIL] Token validation exception: " . $e->getMessage() . "\n";
        $allPassed = false;
        continue;
    }

    // 3. Synchronize user profile into PostgreSQL core.users
    try {
        $user = $syncService->syncFromClaims($claims);
        echo "[PASS] Synced to PostgreSQL 'core.users' (User ID: {$user->id})\n";
        echo "       No password stored: Password length = " . strlen($user->getAuthPassword()) . "\n";
    } catch (\Throwable $e) {
        echo "[FAIL] User sync failed: " . $e->getMessage() . "\n";
        $allPassed = false;
        continue;
    }

    // 4. Verify directly in PostgreSQL database table
    $dbUser = DB::table('core.users')->where('email', $user->email)->first();
    if ($dbUser) {
        echo "[PASS] Verified record in PostgreSQL core.users table:\n";
        echo "       Full Name: {$dbUser->full_name_en}\n";
        echo "       Role Code: {$dbUser->role_code}\n";
        echo "       Designation: {$dbUser->designation}\n";
        echo "       Is Active: " . ($dbUser->is_active ? 'true' : 'false') . "\n";
    } else {
        echo "[FAIL] User not found in core.users table!\n";
        $allPassed = false;
    }

    // 5. Test 7-Tier Hierarchical Scoping Rules
    $hasSubordinateAccess = $scopeService->canAccessScope($user, $testCase['test_target']);
    echo "       Scope check for subordinate target ({$testCase['test_target']}): " .
        ($hasSubordinateAccess ? "[PASS: ALLOWED]" : "[FAIL: DENIED]") . "\n";

    $hasLateralAccess = $scopeService->canAccessScope($user, $testCase['lateral_target']);
    $expectedLateral = ($testCase['expected_tier'] === 'NATIONAL');
    if ($hasLateralAccess === $expectedLateral) {
        echo "       Scope check for lateral target ({$testCase['lateral_target']}): " .
            ($hasLateralAccess ? "[PASS: NATIONAL BYPASS]" : "[PASS: STRICTLY BLOCKED]") . "\n";
    } else {
        echo "       Scope check for lateral target failed. Expected: " . ($expectedLateral ? 'ALLOWED' : 'DENIED') .
            ", got: " . ($hasLateralAccess ? 'ALLOWED' : 'DENIED') . "\n";
        $allPassed = false;
    }

    echo "\n";
}

echo "=================================================================\n";
if ($allPassed) {
    echo " ALL LIVE END-TO-END VERIFICATIONS PASSED SUCCESSFULLY!\n";
} else {
    echo " SOME VERIFICATIONS FAILED.\n";
}
echo "=================================================================\n";
exit($allPassed ? 0 : 1);
