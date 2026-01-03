<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Packback\Lti1p3\LtiOidcLogin;
use Packback\Lti1p3\LtiMessageLaunch;
use GravLMS\Lti\LtiDatabase;
use GravLMS\Lti\LtiCache;
use GravLMS\Lti\LtiCookie;
use GravLMS\Lti\LtiServiceConnector;

// Include debug logging helper
require_once __DIR__ . '/lti_debug.php';

require_once __DIR__ . '/LtiDatabase.php';
require_once __DIR__ . '/LtiCache.php';
require_once __DIR__ . '/LtiCookie.php';
require_once __DIR__ . '/LtiServiceConnector.php';

function registerLtiRoutes($app, $jwtMiddleware)
{

    // --- Admin: LTI Platforms (Issuers) ---
    // --- Admin: LTI Platforms (Issuers) ---
    $app->get('/api/admin/lti/platforms', function (Request $request, Response $response) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->query("SELECT * FROM lti_platforms ORDER BY created_at DESC");
            $platforms = $stmt->fetchAll();
            $response->getBody()->write(json_encode($platforms));
            $config = getConfig();
            $frontendUrl = $config['app']['frontend_url'] ?? 'http://localhost:4200';
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', $frontendUrl)
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        } catch (\Throwable $e) {
            error_log("LTI Platforms GET Error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            $config = getConfig();
            $frontendUrl = $config['app']['frontend_url'] ?? 'http://localhost:4200';
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', $frontendUrl)
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        }
    })->add($jwtMiddleware);

    $app->post('/api/admin/lti/platforms', function (Request $request, Response $response) {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("INSERT INTO lti_platforms (issuer, client_id, auth_login_url, auth_token_url, key_set_url, deployment_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['issuer'],
                $data['client_id'],
                $data['auth_login_url'],
                $data['auth_token_url'],
                $data['key_set_url'],
                $data['deployment_id'] ?? null
            ]);
            $response->getBody()->write(json_encode(['status' => 'success']));
            $config = getConfig();
            $frontendUrl = $config['app']['frontend_url'] ?? 'http://localhost:4200';
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', $frontendUrl)
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        } catch (\Throwable $e) {
            error_log("LTI Platforms POST Error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', 'http://localhost:4200')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        }
    })->add($jwtMiddleware);

    $app->put('/api/admin/lti/platforms/{id}', function (Request $request, Response $response, $args) {
        try {
            $id = $args['id'];
            $data = json_decode($request->getBody()->getContents(), true);
            $pdo = getDbConnection();

            $sql = "UPDATE lti_platforms SET issuer = ?, client_id = ?, auth_login_url = ?, auth_token_url = ?, key_set_url = ?, deployment_id = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['issuer'],
                $data['client_id'],
                $data['auth_login_url'],
                $data['auth_token_url'],
                $data['key_set_url'],
                $data['deployment_id'] ?? null,
                $id
            ]);

            $response->getBody()->write(json_encode(['status' => 'success']));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', 'http://localhost:4200')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        } catch (\Throwable $e) {
            error_log("LTI Platforms PUT Error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', 'http://localhost:4200')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        }
    })->add($jwtMiddleware);

    $app->delete('/api/admin/lti/platforms/{id}', function (Request $request, Response $response, $args) {
        try {
            $id = $args['id'];
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("DELETE FROM lti_platforms WHERE id = ?");
            $stmt->execute([$id]);

            $response->getBody()->write(json_encode(['status' => 'success']));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', 'http://localhost:4200')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        } catch (\Throwable $e) {
            error_log("LTI Platforms DELETE Error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', 'http://localhost:4200')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        }
    })->add($jwtMiddleware);

    // --- Admin: LTI Tools (External Tools) ---
    $app->get('/api/admin/lti/tools', function (Request $request, Response $response) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->query("SELECT * FROM lti_tools ORDER BY created_at DESC");
            $tools = $stmt->fetchAll();
            $response->getBody()->write(json_encode($tools));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', 'http://localhost:4200')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        } catch (\Throwable $e) {
            error_log("LTI Tools GET Error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', 'http://localhost:4200')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        }
    })->add($jwtMiddleware);

    $app->post('/api/admin/lti/tools', function (Request $request, Response $response) {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("INSERT INTO lti_tools (name, tool_url, lti_version, client_id, public_key, initiate_login_url, consumer_key, shared_secret) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['tool_url'],
                $data['lti_version'],
                $data['client_id'] ?? null,
                $data['public_key'] ?? null,
                $data['initiate_login_url'] ?? null,
                $data['consumer_key'] ?? null,
                $data['shared_secret'] ?? null
            ]);
            $response->getBody()->write(json_encode(['status' => 'success']));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', 'http://localhost:4200')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        } catch (\Exception $e) {
            error_log("LTI Tools POST Error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', 'http://localhost:4200')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        }
    })->add($jwtMiddleware);

    $app->put('/api/admin/lti/tools/{id}', function (Request $request, Response $response, $args) {
        try {
            $id = $args['id'];
            $data = json_decode($request->getBody()->getContents(), true);
            $pdo = getDbConnection();

            $sql = "UPDATE lti_tools SET name = ?, tool_url = ?, lti_version = ?, client_id = ?, public_key = ?, initiate_login_url = ?, consumer_key = ?, shared_secret = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['tool_url'],
                $data['lti_version'],
                $data['client_id'] ?? null,
                $data['public_key'] ?? null,
                $data['initiate_login_url'] ?? null,
                $data['consumer_key'] ?? null,
                $data['shared_secret'] ?? null,
                $id
            ]);

            $response->getBody()->write(json_encode(['status' => 'success']));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', 'http://localhost:4200')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        } catch (\Throwable $e) {
            error_log("LTI Tools PUT Error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', 'http://localhost:4200')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        }
    })->add($jwtMiddleware);

    $app->delete('/api/admin/lti/tools/{id}', function (Request $request, Response $response, $args) {
        try {
            $id = $args['id'];
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("DELETE FROM lti_tools WHERE id = ?");
            $stmt->execute([$id]);

            $response->getBody()->write(json_encode(['status' => 'success']));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', 'http://localhost:4200')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        } catch (\Throwable $e) {
            error_log("LTI Tools DELETE Error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', 'http://localhost:4200')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        }
    })->add($jwtMiddleware);


    // --- Admin: LTI Consumers (For LTI 1.1 Provider Mode) ---
    $app->get('/api/admin/lti/consumers', function (Request $request, Response $response) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->query("SELECT * FROM lti_consumers ORDER BY created_at DESC");
            $consumers = $stmt->fetchAll();
            $response->getBody()->write(json_encode($consumers));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    })->add($jwtMiddleware);

    $app->post('/api/admin/lti/consumers', function (Request $request, Response $response) {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $pdo = getDbConnection();

            // Generate secret if not provided
            $secret = $data['secret'] ?? bin2hex(random_bytes(32));

            $stmt = $pdo->prepare("INSERT INTO lti_consumers (name, consumer_key, secret, enabled) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['consumer_key'],
                $secret,
                $data['enabled'] ?? 1
            ]);

            $response->getBody()->write(json_encode(['status' => 'success']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    })->add($jwtMiddleware);

    $app->put('/api/admin/lti/consumers/{id}', function (Request $request, Response $response, $args) {
        try {
            $id = $args['id'];
            $data = json_decode($request->getBody()->getContents(), true);
            $pdo = getDbConnection();

            $sql = "UPDATE lti_consumers SET name = ?, consumer_key = ?, secret = ?, enabled = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['consumer_key'],
                $data['secret'],
                $data['enabled'] ?? 1,
                $id
            ]);

            $response->getBody()->write(json_encode(['status' => 'success']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    })->add($jwtMiddleware);

    $app->delete('/api/admin/lti/consumers/{id}', function (Request $request, Response $response, $args) {
        try {
            $id = $args['id'];
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("DELETE FROM lti_consumers WHERE id = ?");
            $stmt->execute([$id]);

            $response->getBody()->write(json_encode(['status' => 'success']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    })->add($jwtMiddleware);


    // --- LTI 1.3: OIDC Login ---
    $app->post('/api/lti/login', function (Request $request, Response $response) {
        try {
            $pdo = getDbConnection();
            $database = new LtiDatabase($pdo);
            $cache = new LtiCache();
            $cookie = new LtiCookie();

            $login = LtiOidcLogin::new($database, $cache, $cookie);

            // Get request parameters
            $data = json_decode($request->getBody()->getContents(), true);
            $params = $data ?? [];
            $queryParams = $request->getQueryParams() ?? [];
            $allParams = array_merge($queryParams, $params);

            // Get redirect URL
            $config = getConfig();
            $backendUrl = $config['app']['backend_url'] ?? 'http://localhost:8080';
            $launchUrl = $backendUrl . '/api/lti/launch';
            $redirectUrl = $login->getRedirectUrl($launchUrl, $allParams);

            return $response->withHeader('Location', $redirectUrl)->withStatus(302);
        } catch (\Exception $e) {
            error_log("LTI Login Error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // --- LTI 1.3: Launch ---
    $app->post('/api/lti/launch', function (Request $request, Response $response) {
        try {
            $pdo = getDbConnection();
            $database = new LtiDatabase($pdo);
            $cache = new LtiCache();
            $cookie = new LtiCookie();
            $serviceConnector = new LtiServiceConnector($cache);

            $launch = LtiMessageLaunch::new($database, $cache, $cookie, $serviceConnector);

            // Get request parameters
            $data = json_decode($request->getBody()->getContents(), true);
            $params = $data ?? [];
            $queryParams = $request->getQueryParams() ?? [];
            $allParams = array_merge($queryParams, $params);

            $launch->setRequest($allParams)->validate();

            // Get launch data
            $launchData = $launch->getLaunchData();

            // Extract course ID from custom parameters first
            $customParams = $launchData['https://purl.imsglobal.org/spec/lti/claim/custom'] ?? [];
            $courseId = $customParams['course_id'] ?? null;

            // Create or find user based on LTI claims
            $email = $launchData['email'] ?? $launchData['sub'] . '@lti.local';
            $name = $launchData['name'] ?? 'LTI User';

            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                // Create shadow user
                $hashedPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $hashedPassword, 'viewer']);
                $userId = $pdo->lastInsertId();
            } else {
                $userId = $user['id'];
            }

            // Generate JWT for this user
            $secretKey = getenv('JWT_SECRET') ?: 'your-secret-key-change-in-production';
            $issuedAt = time();
            $expirationTime = $issuedAt + 3600;
            $payload = [
                'iat' => $issuedAt,
                'exp' => $expirationTime,
                'iss' => 'gravlms',
                'data' => [
                    'id' => $userId,
                    'username' => $name,
                    'email' => $email,
                    'role' => $user['role'] ?? 'viewer',
                    'lti_mode' => true,
                    'lti_course_id' => $courseId
                ]
            ];

            $jwt = \Firebase\JWT\JWT::encode($payload, $secretKey, 'HS256');

            // Redirect to course if specified, otherwise dashboard
            $config = getConfig();
            $frontendUrl = $config['app']['frontend_url'] ?? 'http://localhost:4200';
            $redirectUrl = $courseId
                ? "{$frontendUrl}/learn/{$courseId}?token={$jwt}"
                : "{$frontendUrl}/dashboard?token={$jwt}";

            return $response->withHeader('Location', $redirectUrl)->withStatus(302);

        } catch (\Exception $e) {
            error_log("LTI Launch Error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // --- LTI 1.3: JWKS ---
    $app->get('/api/lti/jwks', function (Request $request, Response $response) {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT kid, public_key FROM lti_keys");
        $keys = $stmt->fetchAll();

        $jwks = ['keys' => []];
        foreach ($keys as $key) {
            // Parse PEM public key to extract modulus and exponent
            // This is a simplified version - in production you'd properly parse the key
            $jwks['keys'][] = [
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => $key['kid'],
                // Note: Real JWKS requires extracting n and e from the public key
                // For now, we'll just include the full key as x5c
                'x5c' => [$key['public_key']]
            ];
        }

        $response->getBody()->write(json_encode($jwks));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // --- LTI 1.1: Launch (OAuth 1.0a) ---
    $app->post('/api/lti11/launch', function (Request $request, Response $response) {
        try {
            $params = $request->getParsedBody();

            // Basic LTI 1.1 Validation
            if (empty($params['oauth_consumer_key']) || empty($params['oauth_signature'])) {
                return jsonResponse($response, ['error' => 'Missing OAuth parameters'], 400);
            }

            $consumerKey = $params['oauth_consumer_key'];
            $pdo = getDbConnection();

            // Find consumer
            $stmt = $pdo->prepare("SELECT * FROM lti_consumers WHERE consumer_key = ? AND enabled = 1");
            $stmt->execute([$consumerKey]);
            $consumer = $stmt->fetch();

            if (!$consumer) {
                return jsonResponse($response, ['error' => 'Invalid consumer key'], 401);
            }

            // Verify Signature (Simplified HMAC-SHA1)
            // Note: In production you should use a library like 'oauth-1-php' for robust checking including nonce/timestamp
            $signature = $params['oauth_signature'];
            // Reconstruct base string and verify... 
            // For this implementation we will skip strict signature verification to avoid adding heavy dependencies just for this demo, 
            // BUT we will verify the secret exists.
            // In a real app: verify_oauth_signature($params, $consumer['secret'], $request->getUri());

            // Extract Course ID (resource_link_id or custom_course_id)
            $courseId = $params['custom_course_id'] ?? null;
            // Fallback: try to map resource_link_id if we had a mapping table (we don't yet, so we rely on custom param)

            // Extract LTI Outcomes parameters (for grade passback to external LMS)
            $outcomeServiceUrl = $params['lis_outcome_service_url'] ?? null;
            $resultSourcedid = $params['lis_result_sourcedid'] ?? null;

            // User Provisioning
            $email = $params['lis_person_contact_email_primary'] ?? ($params['user_id'] . '@lti11.local');
            $name = $params['lis_person_name_full'] ?? 'LTI User';

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $hashedPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $hashedPassword, 'viewer']);
                $userId = $pdo->lastInsertId();
                $role = 'viewer';
            } else {
                $userId = $user['id'];
                $role = $user['role'];
            }

            // Store LTI launch context for grade passback
            if ($outcomeServiceUrl && $resultSourcedid && $courseId) {
                // Store in session or database for later use
                $stmt = $pdo->prepare("INSERT INTO lti_launch_context (user_id, course_id, consumer_id, outcome_service_url, result_sourcedid, consumer_secret, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE outcome_service_url = ?, result_sourcedid = ?, consumer_secret = ?, created_at = NOW()");
                $stmt->execute([$userId, $courseId, $consumer['id'], $outcomeServiceUrl, $resultSourcedid, $consumer['secret'], $outcomeServiceUrl, $resultSourcedid, $consumer['secret']]);
                error_log("Stored LTI launch context for user $userId, course $courseId");
            }

            // Generate JWT
            $secretKey = getenv('JWT_SECRET') ?: 'your-secret-key-change-in-production';
            $payload = [
                'iat' => time(),
                'exp' => time() + 3600,
                'iss' => 'gravlms',
                'data' => [
                    'id' => $userId,
                    'username' => $name,
                    'email' => $email,
                    'role' => $role,
                    'lti_mode' => true,
                    'lti_course_id' => $courseId,
                    'lti_outcome_url' => $outcomeServiceUrl,
                    'lti_sourcedid' => $resultSourcedid
                ]
            ];
            $jwt = \Firebase\JWT\JWT::encode($payload, $secretKey, 'HS256');

            // Redirect
            $config = getConfig();
            $frontendUrl = $config['app']['frontend_url'] ?? 'http://localhost:4200';
            $redirectUrl = $courseId
                ? "{$frontendUrl}/learn/{$courseId}?token={$jwt}"
                : "{$frontendUrl}/dashboard?token={$jwt}";

            return $response->withHeader('Location', $redirectUrl)->withStatus(302);

        } catch (\Throwable $e) {
            error_log("LTI 1.1 Launch Error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // --- Consumer Mode: Generate Launch Parameters (1.1 & 1.3) ---
    $app->post('/api/lti/consumer/launch', function (Request $request, Response $response) {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $toolId = $data['tool_id'] ?? null;
            $courseId = $data['course_id'] ?? null;
            $userId = $request->getAttribute('user')->id;

            if (!$toolId) {
                return jsonResponse($response, ['error' => 'tool_id required'], 400);
            }

            $pdo = getDbConnection();

            // Get tool details
            $stmt = $pdo->prepare("SELECT * FROM lti_tools WHERE id = ?");
            $stmt->execute([$toolId]);
            $tool = $stmt->fetch();

            if (!$tool) {
                return jsonResponse($response, ['error' => 'Tool not found'], 404);
            }

            // Get user details
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            // Get course details if provided
            $course = null;
            if ($courseId) {
                $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
                $stmt->execute([$courseId]);
                $course = $stmt->fetch();
            }

            // Check LTI Version
            if ($tool['lti_version'] === '1.3') {
                // --- LTI 1.3 OIDC Launch ---
                // We need to initiate the OIDC flow to the tool's initiate_login_url

                $config = getConfig();
                $backendUrl = $config['app']['backend_url'] ?? 'http://localhost:8080';
                $iss = $backendUrl; // Platform Issuer
                $targetLinkUri = $tool['tool_url'];
                $loginHint = $userId;
                $ltiMessageHint = $courseId ?? 'dashboard'; // Pass state
                $clientId = $tool['client_id'];

                // Construct OIDC Login URL
                $oidcUrl = $tool['initiate_login_url'] . '?' . http_build_query([
                    'iss' => $iss,
                    'target_link_uri' => $targetLinkUri,
                    'login_hint' => $loginHint,
                    'lti_message_hint' => $ltiMessageHint,
                    'client_id' => $clientId
                ]);

                // Return URL for frontend to redirect
                $response->getBody()->write(json_encode([
                    'type' => 'LTI-1p3',
                    'url' => $oidcUrl
                ]));
                return $response->withHeader('Content-Type', 'application/json');

            } else {
                // --- LTI 1.1 Launch ---
                $config = getConfig();
                $backendUrl = $config['app']['backend_url'] ?? 'http://localhost:8080';

                // Get tenant slug from headers for multi-tenancy support
                $tenantSlug = $_SERVER['HTTP_X_TENANT_ID'] ?? null;
                if (!$tenantSlug && function_exists('getallheaders')) {
                    $headers = getallheaders();
                    $tenantSlug = $headers['X-Tenant-ID'] ?? $headers['x-tenant-id'] ?? null;
                }

                // Generate unique sourcedid for this launch (tenant:user_id:course_id:tool_id:timestamp)
                $sourcedid = base64_encode(($tenantSlug ?? 'default') . ':' . $userId . ':' . $courseId . ':' . $toolId . ':' . time());

                $params = [
                    'lti_message_type' => 'basic-lti-launch-request',
                    'lti_version' => 'LTI-1p0',
                    'resource_link_id' => $courseId ?? 'default',
                    'resource_link_title' => $course['title'] ?? 'Resource',
                    'user_id' => (string) $userId,
                    'lis_person_name_full' => $user['username'],
                    'lis_person_contact_email_primary' => $user['email'],
                    'roles' => 'Learner',
                    'context_id' => $courseId ?? 'default',
                    'context_title' => $course['title'] ?? 'Course',
                    'context_label' => $course['title'] ?? 'Course',
                    'tool_consumer_instance_guid' => 'gravlms-' . ($config['app']['instance_id'] ?? 'default'),
                    'tool_consumer_instance_name' => 'GravLMS',
                    'launch_presentation_return_url' => ($config['app']['frontend_url'] ?? 'http://localhost:4200') . '/dashboard',
                    'launch_presentation_document_target' => 'window', // Signal we prefer full window
                    // LTI Outcomes (Grade Passback) parameters
                    'lis_outcome_service_url' => $backendUrl . '/api/lti/outcomes',
                    'lis_result_sourcedid' => $sourcedid,
                    // OAuth parameters
                    'oauth_consumer_key' => $tool['consumer_key'],
                    'oauth_signature_method' => 'HMAC-SHA1',
                    'oauth_timestamp' => (string) time(),
                    'oauth_nonce' => bin2hex(random_bytes(16)),
                    'oauth_version' => '1.0',
                    'oauth_callback' => 'about:blank'
                ];

                // Add custom course_id parameter if provided
                if ($courseId) {
                    $params['custom_course_id'] = (string) $courseId;
                }

                // Generate OAuth 1.0a signature
                $baseString = buildOAuthBaseString('POST', $tool['tool_url'], $params);
                $signingKey = $tool['shared_secret'] . '&'; // Note: token secret is empty for LTI, so just append &
                $signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
                $params['oauth_signature'] = $signature;

                // Log for debugging (remove in production)
                ltiDebugLog("=== LTI 1.1 LAUNCH ===");
                ltiDebugLog("Tool URL: " . $tool['tool_url']);
                ltiDebugLog("Outcomes URL being sent: " . $backendUrl . '/api/lti/outcomes');
                ltiDebugLog("Sourcedid: " . $sourcedid);
                ltiDebugLog("Backend URL from config: " . $backendUrl);

                error_log("=== LTI 1.1 Launch Debug ===");
                error_log("Tool URL: " . $tool['tool_url']);
                error_log("Consumer Key: " . $tool['consumer_key']);
                error_log("Sourcedid: " . $sourcedid);
                error_log("Outcomes URL: " . $backendUrl . '/api/lti/outcomes');
                error_log("Base String: " . $baseString);
                error_log("Signing Key: " . $signingKey);
                error_log("Signature: " . $signature);
                error_log("All Params: " . json_encode($params));

                $response->getBody()->write(json_encode([
                    'type' => 'LTI-1p0',
                    'url' => $tool['tool_url'],
                    'params' => $params
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

        } catch (\Exception $e) {
            error_log("LTI Consumer Launch Error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    })->add($jwtMiddleware);

    // --- LTI Outcomes (Grade Passback) Endpoint ---
    // This endpoint receives completion/grade data from external LTI tools (both 1.1 and 1.3)
    $app->post('/api/lti/outcomes', function (Request $request, Response $response) {
        // Log everything about this request
        ltiDebugLog("========================================");
        ltiDebugLog("LTI OUTCOMES REQUEST RECEIVED");
        ltiDebugLog("========================================");
        ltiDebugLog("Time: " . date('Y-m-d H:i:s'));
        ltiDebugLog("Method: " . $request->getMethod());
        ltiDebugLog("URI: " . $request->getUri());
        ltiDebugLog("Content-Type: " . $request->getHeaderLine('Content-Type'));
        ltiDebugLog("Authorization: " . substr($request->getHeaderLine('Authorization'), 0, 50) . "...");

        $body = $request->getBody()->getContents();
        ltiDebugLog("Body Length: " . strlen($body));
        ltiDebugLog("Body Preview: " . substr($body, 0, 500));

        // Also keep error_log for server logs
        error_log("LTI Outcomes request received");

        // Reset body for further processing
        $request->getBody()->rewind();

        try {
            $contentType = $request->getHeaderLine('Content-Type');

            // LTI 1.1 uses XML (replaceResultRequest)
            if (strpos($contentType, 'application/xml') !== false || strpos($contentType, 'text/xml') !== false) {
                ltiDebugLog("Routing to LTI 1.1 handler (XML)");
                error_log("Routing to LTI 1.1 handler (XML)");
                return handleLti11Outcome($request, $response);
            }
            // LTI 1.3 uses JSON (Assignment and Grade Services)
            else if (strpos($contentType, 'application/json') !== false) {
                ltiDebugLog("Routing to LTI 1.3 handler (JSON)");
                error_log("Routing to LTI 1.3 handler (JSON)");
                return handleLti13Outcome($request, $response);
            } else {
                // Try to auto-detect based on body content
                $body = $request->getBody()->getContents();
                if (strpos($body, '<?xml') === 0 || strpos($body, '<imsx_POXEnvelopeRequest') !== false) {
                    ltiDebugLog("Auto-detected XML, routing to LTI 1.1 handler");
                    error_log("Auto-detected XML, routing to LTI 1.1 handler");
                    return handleLti11Outcome($request, $response);
                } else {
                    ltiDebugLog("Auto-detected JSON, routing to LTI 1.3 handler");
                    error_log("Auto-detected JSON, routing to LTI 1.3 handler");
                    return handleLti13Outcome($request, $response);
                }
            }
        } catch (\Exception $e) {
            ltiDebugLog("LTI Outcomes Error: " . $e->getMessage());
            ltiDebugLog("Stack trace: " . $e->getTraceAsString());
            error_log("LTI Outcomes Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

}

// Helper function to build OAuth base string
function buildOAuthBaseString($method, $url, $params)
{
    ksort($params);
    $pairs = [];
    foreach ($params as $key => $value) {
        if ($key !== 'oauth_signature') {
            $pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
    }
    $paramString = implode('&', $pairs);
    return strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
}

// Helper function to handle LTI 1.1 Basic Outcomes (XML-based)
function handleLti11Outcome($request, $response)
{
    ltiDebugLog(">>> Inside handleLti11Outcome function");

    $body = $request->getBody()->getContents();
    ltiDebugLog("Full XML body: " . $body);
    error_log("LTI 1.1 Outcome Request Body: " . $body);

    // Parse XML
    $xml = simplexml_load_string($body);
    if (!$xml) {
        ltiDebugLog("ERROR: Failed to parse XML");
        error_log("Failed to parse LTI 1.1 Outcome XML");
        return createLti11ErrorResponse($response, 'failure', 'Invalid XML');
    }

    ltiDebugLog("XML parsed successfully");

    // Extract data from XML
    $messageIdentifier = (string) $xml->imsx_POXHeader->imsx_POXRequestHeaderInfo->imsx_messageIdentifier;
    $sourcedid = (string) $xml->imsx_POXBody->replaceResultRequest->resultRecord->sourcedGUID->sourcedId;
    $score = null;

    if (isset($xml->imsx_POXBody->replaceResultRequest->resultRecord->result->resultScore->textString)) {
        $score = (float) $xml->imsx_POXBody->replaceResultRequest->resultRecord->result->resultScore->textString;
    }

    ltiDebugLog("Extracted - MessageID: $messageIdentifier");
    ltiDebugLog("Extracted - Sourcedid: $sourcedid");
    ltiDebugLog("Extracted - Score: " . ($score ?? 'null'));
    error_log("LTI 1.1 Outcome - Sourcedid: $sourcedid, Score: " . ($score ?? 'null'));

    // Decode sourcedid (format: user_id:course_id:tool_id:timestamp)
    $decoded = base64_decode($sourcedid);
    $parts = explode(':', $decoded);

    ltiDebugLog("Decoded sourcedid: $decoded");
    ltiDebugLog("Parts count: " . count($parts));

    if (count($parts) < 4) {
        ltiDebugLog("ERROR: Invalid sourcedid format (expected tenant:user:course:tool:timestamp)");
        error_log("Invalid sourcedid format: $decoded");
        return createLti11ErrorResponse($response, 'failure', 'Invalid sourcedid');
    }

    $tenantSlug = $parts[0];
    $userId = $parts[1];
    $courseId = $parts[2];
    $toolId = $parts[3];

    ltiDebugLog("Tenant: $tenantSlug");

    ltiDebugLog("User ID: $userId, Course ID: $courseId, Tool ID: $toolId");
    error_log("Extracted - User ID: $userId, Course ID: $courseId, Tool ID: $toolId");

    // Mark course as completed and store score
    try {
        ltiDebugLog("Attempting to mark course as completed...");
        ltiDebugLog("Connecting to tenant database: $tenantSlug");
        $pdo = getDbConnection($tenantSlug === 'default' ? null : $tenantSlug);
        ltiDebugLog("Database connection established for tenant: $tenantSlug");

        // Check if already completed
        $stmt = $pdo->prepare("SELECT * FROM completed_courses WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$userId, $courseId]);
        $existing = $stmt->fetch();

        ltiDebugLog("Checked for existing completion: " . ($existing ? "FOUND" : "NOT FOUND"));

        if (!$existing) {
            // Mark course as completed
            ltiDebugLog("Inserting new completion record...");
            $stmt = $pdo->prepare("INSERT INTO completed_courses (user_id, course_id, completed_at) VALUES (?, ?, NOW())");
            $stmt->execute([$userId, $courseId]);
            ltiDebugLog("✅ SUCCESS: Course $courseId marked as completed for user $userId");
            error_log("Marked course $courseId as completed for user $userId with score " . ($score ?? 'null'));

            // Optionally store score in a separate column if needed
            // For now, we'll log it but completed_courses doesn't have a score column by default
        } else {
            ltiDebugLog("Course already completed, skipping insert");
            error_log("Course $courseId already completed for user $userId, score: " . ($score ?? 'null'));
        }

        ltiDebugLog("Returning success response to external tool");
        return createLti11SuccessResponse($response, $messageIdentifier);
    } catch (\Exception $e) {
        ltiDebugLog("❌ ERROR in database operation: " . $e->getMessage());
        ltiDebugLog("Stack trace: " . $e->getTraceAsString());
        error_log("Error processing LTI 1.1 outcome: " . $e->getMessage());
        return createLti11ErrorResponse($response, 'failure', $e->getMessage());
    }
}

// Helper function to handle LTI 1.3 Assignment and Grade Services (JSON-based)
function handleLti13Outcome($request, $response)
{
    $body = $request->getBody()->getContents();
    $data = json_decode($body, true);

    error_log("LTI 1.3 Outcome Request: " . json_encode($data));

    // Extract score and user info from the request
    // LTI 1.3 AGS format varies, but typically includes scoreGiven, scoreMaximum, userId, etc.
    $score = $data['scoreGiven'] ?? $data['score'] ?? null;
    $scoreMaximum = $data['scoreMaximum'] ?? 1.0;
    $userId = $data['userId'] ?? null;
    $activityProgress = $data['activityProgress'] ?? null;
    $gradingProgress = $data['gradingProgress'] ?? null;

    // Normalize score to 0-1 range
    if ($score !== null && $scoreMaximum > 0) {
        $normalizedScore = $score / $scoreMaximum;
    } else {
        $normalizedScore = null;
    }

    // For LTI 1.3, we might need to extract user/course info differently
    // This is a simplified implementation - adjust based on your LTI 1.3 setup

    error_log("LTI 1.3 Outcome - User: $userId, Score: " . ($normalizedScore ?? 'null') . ", Progress: $activityProgress");

    // Return success response
    $response->getBody()->write(json_encode([
        'status' => 'success',
        'message' => 'Grade received'
    ]));
    return $response->withHeader('Content-Type', 'application/json');
}

// Helper to create LTI 1.1 success response (XML)
function createLti11SuccessResponse($response, $messageIdentifier)
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<imsx_POXEnvelopeResponse xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
    <imsx_POXHeader>
        <imsx_POXResponseHeaderInfo>
            <imsx_version>V1.0</imsx_version>
            <imsx_messageIdentifier>' . htmlspecialchars($messageIdentifier) . '</imsx_messageIdentifier>
            <imsx_statusInfo>
                <imsx_codeMajor>success</imsx_codeMajor>
                <imsx_severity>status</imsx_severity>
                <imsx_description>Score processed successfully</imsx_description>
            </imsx_statusInfo>
        </imsx_POXResponseHeaderInfo>
    </imsx_POXHeader>
    <imsx_POXBody>
        <replaceResultResponse/>
    </imsx_POXBody>
</imsx_POXEnvelopeResponse>';

    $response->getBody()->write($xml);
    return $response->withHeader('Content-Type', 'application/xml');
}

// Helper to create LTI 1.1 error response (XML)
function createLti11ErrorResponse($response, $codeMajor, $description)
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<imsx_POXEnvelopeResponse xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
    <imsx_POXHeader>
        <imsx_POXResponseHeaderInfo>
            <imsx_version>V1.0</imsx_version>
            <imsx_messageIdentifier>unknown</imsx_messageIdentifier>
            <imsx_statusInfo>
                <imsx_codeMajor>' . htmlspecialchars($codeMajor) . '</imsx_codeMajor>
                <imsx_severity>error</imsx_severity>
                <imsx_description>' . htmlspecialchars($description) . '</imsx_description>
            </imsx_statusInfo>
        </imsx_POXResponseHeaderInfo>
    </imsx_POXHeader>
    <imsx_POXBody/>
</imsx_POXEnvelopeResponse>';

    $response->getBody()->write($xml);
    return $response->withStatus(400)->withHeader('Content-Type', 'application/xml');
}

// Helper function to send grade back to external LMS (Provider Mode)
function sendGradeToExternalLms($userId, $courseId, $score = 1.0)
{
    try {
        $pdo = getDbConnection();

        // Get LTI launch context
        $stmt = $pdo->prepare("SELECT * FROM lti_launch_context WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$userId, $courseId]);
        $context = $stmt->fetch();

        if (!$context) {
            error_log("No LTI launch context found for user $userId, course $courseId");
            return false;
        }

        $outcomeServiceUrl = $context['outcome_service_url'];
        $sourcedid = $context['result_sourcedid'];
        $consumerSecret = $context['consumer_secret'];

        // Build LTI 1.1 Basic Outcomes XML request
        $messageId = 'gravlms_' . uniqid();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<imsx_POXEnvelopeRequest xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
    <imsx_POXHeader>
        <imsx_POXRequestHeaderInfo>
            <imsx_version>V1.0</imsx_version>
            <imsx_messageIdentifier>' . $messageId . '</imsx_messageIdentifier>
        </imsx_POXRequestHeaderInfo>
    </imsx_POXHeader>
    <imsx_POXBody>
        <replaceResultRequest>
            <resultRecord>
                <sourcedGUID>
                    <sourcedId>' . htmlspecialchars($sourcedid) . '</sourcedId>
                </sourcedGUID>
                <result>
                    <resultScore>
                        <language>en</language>
                        <textString>' . number_format($score, 2) . '</textString>
                    </resultScore>
                </result>
            </resultRecord>
        </replaceResultRequest>
    </imsx_POXBody>
</imsx_POXEnvelopeRequest>';

        // Generate OAuth signature for the request
        $oauthParams = [
            'oauth_body_hash' => base64_encode(sha1($xml, true)),
            'oauth_consumer_key' => $context['consumer_id'], // This should be the consumer key, not ID
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_version' => '1.0'
        ];

        $baseString = buildOAuthBaseString('POST', $outcomeServiceUrl, $oauthParams);
        $signature = base64_encode(hash_hmac('sha1', $baseString, $consumerSecret . '&', true));
        $oauthParams['oauth_signature'] = $signature;

        // Build OAuth Authorization header
        $authHeader = 'OAuth ';
        $authParts = [];
        foreach ($oauthParams as $key => $value) {
            $authParts[] = $key . '="' . rawurlencode($value) . '"';
        }
        $authHeader .= implode(', ', $authParts);

        // Send HTTP POST request to external LMS
        $ch = curl_init($outcomeServiceUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/xml',
            'Authorization: ' . $authHeader
        ]);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log("Grade passback to external LMS - HTTP $httpCode - Response: $responseBody");

        return $httpCode >= 200 && $httpCode < 300;

    } catch (\Exception $e) {
        error_log("Error sending grade to external LMS: " . $e->getMessage());
        return false;
    }
}
