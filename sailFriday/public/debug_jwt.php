<?php
/**
 * JWT Debug Script
 *
 * USAGE:
 * ======
 *
 * 1. Deploy this file to your server's public directory
 *
 * 2. Access without token to check configuration:
 *    curl https://your-server.com/debug_jwt.php
 *
 * 3. Access with a JWT token to test authentication:
 *    curl -H "Authorization: Bearer YOUR_TOKEN_HERE" https://your-server.com/debug_jwt.php
 *
 * 4. Or access from browser:
 *    https://your-server.com/debug_jwt.php
 *
 * WHAT TO LOOK FOR:
 * =================
 *
 * ✓ JWT_SECRET_set should be true (not using default)
 * ✓ JWT_SECRET_length should be >= 32 characters
 * ✓ JWT_SECRET_is_default should be false in production
 * ✓ headers should contain "Authorization" key when you send one
 * ✓ test_validation.success should be true
 * ✓ If you provide a token, provided_token.validation_success should be true
 *
 * COMMON ISSUES:
 * ==============
 *
 * - Missing Authorization header in headers array?
 *   → Your web server is not forwarding the header to PHP
 *   → Solution: Update .htaccess (Apache) or nginx config
 *
 * - JWT_SECRET_is_default is true?
 *   → You haven't set JWT_SECRET environment variable
 *   → Solution: Create .env file with JWT_SECRET
 *
 * - provided_token.validation_success is false?
 *   → Token was generated with different secret
 *   → Token is expired
 *   → Token is malformed
 *
 * **SECURITY WARNING**: Delete this file after debugging!
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Populate putenv
foreach ($_ENV as $key => $value) {
    if (!getenv($key)) {
        putenv("{$key}={$value}");
    }
}

header('Content-Type: application/json');

// 1. Check JWT configuration
$jwtSecret = getenv('JWT_SECRET') ?: 'CHANGE_IN_PRODUCTION_MIN_32_CHARS';
$jwtExpiration = (int)(getenv('JWT_EXPIRATION_MINUTES') ?: 60);

$debug = [
    'instructions' => [
        'message' => 'JWT Authentication Diagnostics',
        'usage' => 'See file header comments for detailed usage instructions',
        'test_with_token' => 'curl -H "Authorization: Bearer YOUR_TOKEN" ' . ($_SERVER['HTTP_HOST'] ?? 'your-server.com') . '/debug_jwt.php',
        'delete_after_use' => 'IMPORTANT: Delete this file after debugging (security risk)',
    ],
    'environment' => [
        'JWT_SECRET_set' => !empty(getenv('JWT_SECRET')),
        'JWT_SECRET_length' => strlen($jwtSecret),
        'JWT_SECRET_is_default' => $jwtSecret === 'CHANGE_IN_PRODUCTION_MIN_32_CHARS',
        'JWT_EXPIRATION_MINUTES' => $jwtExpiration,
        'status' => !empty(getenv('JWT_SECRET')) && strlen($jwtSecret) >= 32 ? '✓ OK' : '✗ PROBLEM: Set JWT_SECRET in .env file',
    ],
    'headers' => [],
    'server_vars' => [],
];

// 2. Get all headers
if (function_exists('getallheaders')) {
    $debug['headers'] = getallheaders() ?: [];
} else {
    // Fallback
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) === 'HTTP_') {
            $header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $debug['headers'][$header] = $value;
        }
    }
}

// Check if Authorization header was received
$hasAuthHeader = isset($debug['headers']['Authorization']) || isset($debug['headers']['authorization']);
$debug['headers']['_status'] = $hasAuthHeader
    ? '✓ Authorization header received'
    : '✗ No Authorization header (send: curl -H "Authorization: Bearer TOKEN" ...)';

// 3. Get relevant SERVER variables
$serverKeys = ['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'PHP_AUTH_USER', 'PHP_AUTH_PW'];
foreach ($serverKeys as $key) {
    if (isset($_SERVER[$key])) {
        $debug['server_vars'][$key] = $_SERVER[$key];
    }
}

// 4. Test token generation and validation
try {
    require_once __DIR__ . '/../config/config.php';
    $config = require __DIR__ . '/../config/config.php';

    $tokenService = new \App\Infrastructure\Service\JwtTokenService(
        $config['jwt']['secret'],
        $config['jwt']['expiration_minutes']
    );

    // Generate a test token
    $testToken = $tokenService->generate(1, 'test@example.com', 'crew', false);
    $debug['test_token'] = [
        'generated' => substr($testToken, 0, 50) . '...',
        'parts_count' => count(explode('.', $testToken)),
    ];

    // Try to validate it
    $validated = $tokenService->validate($testToken);
    $debug['test_validation'] = [
        'success' => $validated !== null,
        'payload' => $validated,
    ];

    // If there's an Authorization header, try to validate that too
    $authHeader = $debug['headers']['Authorization'] ?? $debug['headers']['authorization'] ?? null;
    if ($authHeader) {
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $providedToken = $matches[1];
            $providedValidation = $tokenService->validate($providedToken);

            $debug['provided_token'] = [
                'status' => $providedValidation !== null ? '✓ Token is valid' : '✗ Token validation failed',
                'token_preview' => substr($providedToken, 0, 50) . '...',
                'validation_success' => $providedValidation !== null,
                'payload' => $providedValidation,
                'reason_if_failed' => $providedValidation === null
                    ? 'Token is expired, malformed, or signed with different secret'
                    : null,
            ];
        } else {
            $debug['provided_token'] = [
                'status' => '✗ Malformed Authorization header',
                'received' => $authHeader,
                'expected_format' => 'Bearer YOUR_TOKEN_HERE',
            ];
        }
    } else {
        $debug['provided_token'] = [
            'status' => 'No token provided - test with: curl -H "Authorization: Bearer TOKEN" ...',
        ];
    }

} catch (\Exception $e) {
    $debug['error'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ];
}

// Add summary at the end
$hasAuthHeader = isset($debug['headers']['Authorization']) || isset($debug['headers']['authorization']);
$jwtConfigured = !empty(getenv('JWT_SECRET')) && strlen($jwtSecret) >= 32;
$testPassed = isset($debug['test_validation']['success']) && $debug['test_validation']['success'];

$debug['summary'] = [
    'jwt_configured' => $jwtConfigured ? '✓ YES' : '✗ NO - Set JWT_SECRET in .env',
    'authorization_header_working' => $hasAuthHeader ? '✓ YES' : '? UNKNOWN - No token sent to test',
    'token_generation_working' => $testPassed ? '✓ YES' : '✗ NO',
    'overall_status' => ($jwtConfigured && $testPassed) ? '✓ READY' : '✗ NEEDS CONFIGURATION',
    'next_steps' => [],
];

if (!$jwtConfigured) {
    $debug['summary']['next_steps'][] = 'Create .env file with JWT_SECRET (minimum 32 chars)';
}

if (!$hasAuthHeader) {
    $debug['summary']['next_steps'][] = 'Test with a token: curl -H "Authorization: Bearer TOKEN" ' . ($_SERVER['HTTP_HOST'] ?? 'your-server.com') . '/debug_jwt.php';
}

if (isset($debug['provided_token']) &&
    isset($debug['provided_token']['validation_success']) &&
    !$debug['provided_token']['validation_success']) {
    $debug['summary']['next_steps'][] = 'Token validation failed - check if token was generated with same JWT_SECRET';
}

$debug['summary']['next_steps'][] = '⚠️  DELETE THIS FILE after debugging (security risk)';

echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
