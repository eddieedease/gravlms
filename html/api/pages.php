<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

function registerPageRoutes($app, $authMiddleware)
{
    $app->group('/api/pages', function ($group) {
        $group->get('', function (Request $request, Response $response, $args) {
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->query("SELECT id, title, content, type, course_id, display_order, created_at FROM course_pages ORDER BY display_order ASC, created_at ASC");
                $pages = $stmt->fetchAll();
                return jsonResponse($response, $pages);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        $group->post('', function (Request $request, Response $response, $args) {
            $data = json_decode($request->getBody()->getContents(), true);
            $title = $data['title'] ?? null;
            $content = $data['content'] ?? '';
            $type = $data['type'] ?? 'page';
            $course_id = $data['course_id'] ?? null;

            if (!$title) {
                return jsonResponse($response, ['error' => 'Title is required'], 400);
            }

            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("INSERT INTO course_pages (title, content, type, course_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $content, $type, $course_id]);
                $id = $pdo->lastInsertId();
                return jsonResponse($response, ['status' => 'success', 'message' => 'Page created', 'id' => $id], 201);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        $group->put('/{id}', function (Request $request, Response $response, $args) {
            $id = $args['id'];
            $data = json_decode($request->getBody()->getContents(), true);
            $title = $data['title'] ?? null;
            $content = $data['content'] ?? null;
            $type = $data['type'] ?? null;
            $course_id = $data['course_id'] ?? null;
            $display_order = $data['display_order'] ?? null;

            try {
                $pdo = getDbConnection();
                $fields = [];
                $values = [];
                if ($title) {
                    $fields[] = "title = ?";
                    $values[] = $title;
                }
                if ($content !== null) {
                    $fields[] = "content = ?";
                    $values[] = $content;
                }
                if ($type !== null) {
                    $fields[] = "type = ?";
                    $values[] = $type;
                }
                if ($course_id !== null) {
                    $fields[] = "course_id = ?";
                    $values[] = $course_id;
                }
                if ($display_order !== null) {
                    $fields[] = "display_order = ?";
                    $values[] = $display_order;
                }

                if (empty($fields)) {
                    return jsonResponse($response, ['message' => 'No changes provided']);
                }

                $values[] = $id;
                $sql = "UPDATE course_pages SET " . implode(', ', $fields) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);

                return jsonResponse($response, ['status' => 'success', 'message' => 'Page updated']);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        $group->delete('/{id}', function (Request $request, Response $response, $args) {
            $id = $args['id'];
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("DELETE FROM course_pages WHERE id = ?");
                $stmt->execute([$id]);
                return jsonResponse($response, ['status' => 'success', 'message' => 'Page deleted']);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });
    })->add($authMiddleware);
}
