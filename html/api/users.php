<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

function registerUserRoutes($app, $authMiddleware)
{
    $app->group('/api/users', function ($group) {
        $group->get('', function (Request $request, Response $response, $args) {
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->query("SELECT id, username, role, created_at FROM users");
                $users = $stmt->fetchAll();
                return jsonResponse($response, $users);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        $group->post('', function (Request $request, Response $response, $args) {
            $data = json_decode($request->getBody()->getContents(), true);
            $username = $data['username'] ?? null;
            $password = $data['password'] ?? null;
            $role = $data['role'] ?? 'editor';

            if (!$username || !$password) {
                return jsonResponse($response, ['error' => 'Username and password are required'], 400);
            }

            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([$username, $hashedPassword, $role]);
                return jsonResponse($response, ['status' => 'success', 'message' => 'User created'], 201);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        $group->put('/{id}', function (Request $request, Response $response, $args) {
            $id = $args['id'];
            $data = json_decode($request->getBody()->getContents(), true);
            $username = $data['username'] ?? null;
            $role = $data['role'] ?? null;

            try {
                $pdo = getDbConnection();
                $fields = [];
                $values = [];
                if ($username) {
                    $fields[] = "username = ?";
                    $values[] = $username;
                }
                if ($role) {
                    $fields[] = "role = ?";
                    $values[] = $role;
                }

                if (empty($fields)) {
                    return jsonResponse($response, ['message' => 'No changes provided']);
                }

                $values[] = $id;
                $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);

                return jsonResponse($response, ['status' => 'success', 'message' => 'User updated']);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

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
