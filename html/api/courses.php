<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

function registerCourseRoutes($app, $authMiddleware)
{
    $app->group('/api/courses', function ($group) {
        $group->get('', function (Request $request, Response $response, $args) {
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->query("SELECT * FROM courses ORDER BY display_order ASC, created_at DESC");
                $courses = $stmt->fetchAll();
                return jsonResponse($response, $courses);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        $group->post('', function (Request $request, Response $response, $args) {
            $data = json_decode($request->getBody()->getContents(), true);
            $title = $data['title'] ?? null;
            $description = $data['description'] ?? '';

            if (!$title) {
                return jsonResponse($response, ['error' => 'Title is required'], 400);
            }

            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("INSERT INTO courses (title, description) VALUES (?, ?)");
                $stmt->execute([$title, $description]);
                return jsonResponse($response, ['status' => 'success', 'message' => 'Course created'], 201);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        $group->put('/{id}', function (Request $request, Response $response, $args) {
            $id = $args['id'];
            $data = json_decode($request->getBody()->getContents(), true);
            $title = $data['title'] ?? null;
            $description = $data['description'] ?? null;
            $display_order = $data['display_order'] ?? null;

            try {
                $pdo = getDbConnection();
                $fields = [];
                $values = [];
                if ($title) {
                    $fields[] = "title = ?";
                    $values[] = $title;
                }
                if ($description !== null) {
                    $fields[] = "description = ?";
                    $values[] = $description;
                }
                if ($display_order !== null) {
                    $fields[] = "display_order = ?";
                    $values[] = $display_order;
                }

                if (empty($fields)) {
                    return jsonResponse($response, ['message' => 'No changes provided']);
                }

                $values[] = $id;
                $sql = "UPDATE courses SET " . implode(', ', $fields) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);

                return jsonResponse($response, ['status' => 'success', 'message' => 'Course updated']);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        $group->delete('/{id}', function (Request $request, Response $response, $args) {
            $id = $args['id'];
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                $stmt->execute([$id]);
                return jsonResponse($response, ['status' => 'success', 'message' => 'Course deleted']);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });
    })->add($authMiddleware);
}
