<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function registerAuthRoutes($app)
{
    $app->post('/api/login', function (Request $request, Response $response, $args) {
        $data = json_decode($request->getBody()->getContents(), true);
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;

        if (!$username || !$password) {
            return jsonResponse($response, ['error' => 'Username and password are required'], 400);
        }

        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
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
