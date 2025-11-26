<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Ensure autoloader is loaded (should be loaded via index.php)
if (!class_exists('Firebase\JWT\JWT')) {
    throw new \Exception('Firebase JWT library not loaded. Ensure vendor/autoload.php is included.');
}

function registerAuthRoutes($app)
{
    $app->post('/api/login', function (Request $request, Response $response, $args) {
        $data = json_decode($request->getBody()->getContents(), true);
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return jsonResponse($response, ['error' => 'Email and password are required'], 400);
        }

        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // JWT Configuration
                $secretKey = getenv('JWT_SECRET') ?: 'your-secret-key-change-in-production';
                $issuedAt = time();
                $expirationTime = $issuedAt + 3600; // 1 hour
                $payload = [
                    'iat' => $issuedAt,
                    'exp' => $expirationTime,
                    'iss' => 'gravlms',
                    'data' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ]
                ];

                $jwt = JWT::encode($payload, $secretKey, 'HS256');

                return jsonResponse($response, [
                    'status' => 'success',
                    'token' => $jwt,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ]
                ]);
            } else {
                return jsonResponse($response, ['error' => 'Invalid credentials'], 401);
            }
        } catch (PDOException $e) {
            return jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    });
}
