<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

function registerUserRoutes($app, $authMiddleware)
{
    $app->group('/api/users', function ($group) {

        // Get all users
        $group->get('', function (Request $request, Response $response, $args) {
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->query("SELECT id, username, email, role, created_at, updated_at FROM users");
                $users = $stmt->fetchAll();
                return jsonResponse($response, $users);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Create a user
        $group->post('', function (Request $request, Response $response, $args) {
            $data = json_decode($request->getBody()->getContents(), true);
            $username = $data['username'] ?? null;
            $email = $data['email'] ?? null;
            $password = $data['password'] ?? null;
            $role = $data['role'] ?? 'viewer';

            if (!$username || !$password || !$email) {
                return jsonResponse($response, ['error' => 'Username, email, and password are required'], 400);
            }

            try {
                $pdo = getDbConnection();
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashedPassword, $role]);
                return jsonResponse($response, ['status' => 'success', 'message' => 'User created'], 201);
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    return jsonResponse($response, ['error' => 'Username or email already exists'], 409);
                }
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Update a user
        $group->put('/{id}', function (Request $request, Response $response, $args) {
            $id = $args['id'];
            $data = json_decode($request->getBody()->getContents(), true);
            $username = $data['username'] ?? null;
            $email = $data['email'] ?? null;
            $password = $data['password'] ?? null;
            $role = $data['role'] ?? null;

            try {
                $pdo = getDbConnection();
                $fields = [];
                $values = [];

                if ($username) {
                    $fields[] = "username = ?";
                    $values[] = $username;
                }
                if ($email) {
                    $fields[] = "email = ?";
                    $values[] = $email;
                }
                if ($password) {
                    $fields[] = "password = ?";
                    $values[] = password_hash($password, PASSWORD_DEFAULT);
                }
                if ($role) {
                    $fields[] = "role = ?";
                    $values[] = $role;
                }

                if (empty($fields)) {
                    return jsonResponse($response, ['message' => 'No changes provided']);
                }

                // Add updated_at
                $fields[] = "updated_at = NOW()";

                $values[] = $id;
                $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);

                return jsonResponse($response, ['status' => 'success', 'message' => 'User updated']);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Delete a user
        $group->delete('/{id}', function (Request $request, Response $response, $args) {
            $id = $args['id'];
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                return jsonResponse($response, ['status' => 'success', 'message' => 'User deleted']);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });
    })->add($authMiddleware);
}
