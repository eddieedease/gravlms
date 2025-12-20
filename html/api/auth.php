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
                        'role' => $user['role'],
                        'is_monitor' => isUserMonitor($pdo, $user['id'])
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
                        'role' => $user['role'],
                        'is_monitor' => isUserMonitor($pdo, $user['id'])
                    ]
                ]);
            } else {
                return jsonResponse($response, ['error' => 'Invalid credentials'], 401);
            }
        } catch (PDOException $e) {
            return jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    });

    $app->post('/api/forgot-password', function (Request $request, Response $response, $args) {
        $data = json_decode($request->getBody()->getContents(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return jsonResponse($response, ['error' => 'Email is required'], 400);
        }

        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expiresAt]);

                // Send email
                $config = getConfig();
                $emailConfig = $config['email'] ?? [];

                if (!empty($emailConfig) && $emailConfig['enabled']) {
                    $resetLink = "http://localhost:4200/reset-password?token=" . $token;
                    $emailService = new EmailService($emailConfig);
                    $subject = "Password Reset Request";
                    $body = "Click the following link to reset your password: <a href='$resetLink'>$resetLink</a>";
                    $emailService->send($email, $subject, $body);
                }
            }

            // Always return success to prevent email enumeration
            return jsonResponse($response, ['status' => 'success', 'message' => 'If your email exists in our system, you will receive a password reset link.']);

        } catch (PDOException $e) {
            return jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    });

    $app->post('/api/reset-password', function (Request $request, Response $response, $args) {
        $data = json_decode($request->getBody()->getContents(), true);
        $token = $data['token'] ?? null;
        $password = $data['password'] ?? null;

        if (!$token || !$password) {
            return jsonResponse($response, ['error' => 'Token and password are required'], 400);
        }

        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$token]);
            $resetRequest = $stmt->fetch();

            if (!$resetRequest) {
                return jsonResponse($response, ['error' => 'Invalid or expired token'], 400);
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $resetRequest['email']]);

            // Delete used token
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$resetRequest['email']]);

            return jsonResponse($response, ['status' => 'success', 'message' => 'Password has been reset successfully']);

        } catch (PDOException $e) {
            return jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    });
}

function isUserMonitor($pdo, $userId)
{
    if (!$userId)
        return false;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_monitors WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() > 0;
}
