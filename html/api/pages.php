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
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO course_pages (title, content, type, course_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $content, $type, $course_id]);
                $id = $pdo->lastInsertId();

                if ($type === 'assessment') {
                    $instructions = $data['instructions'] ?? '';
                    $stmtAss = $pdo->prepare("INSERT INTO assessments (page_id, instructions) VALUES (?, ?)");
                    $stmtAss->execute([$id, $instructions]);
                }

                $pdo->commit();
                return jsonResponse($response, ['status' => 'success', 'message' => 'Page created', 'id' => $id], 201);
            } catch (PDOException $e) {
                $pdo->rollBack();
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
                    // It's possible we only want to update assessment table
                    // But usually frontend sends some page fields too.
                }

                $pdo->beginTransaction();

                if (!empty($fields)) {
                    $values[] = $id;
                    $sql = "UPDATE course_pages SET " . implode(', ', $fields) . " WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);
                }

                // Update Assessment if needed
                if ($type === 'assessment' || ($type === null && isset($data['instructions']))) {
                    $instructions = $data['instructions'] ?? null;
                    if ($instructions !== null) {
                        // Upsert assessment
                        $stmtCheck = $pdo->prepare("SELECT id FROM assessments WHERE page_id = ?");
                        $stmtCheck->execute([$id]);
                        if ($stmtCheck->fetch()) {
                            $stmtUpd = $pdo->prepare("UPDATE assessments SET instructions = ? WHERE page_id = ?");
                            $stmtUpd->execute([$instructions, $id]);
                        } else {
                            $stmtIns = $pdo->prepare("INSERT INTO assessments (page_id, instructions) VALUES (?, ?)");
                            $stmtIns->execute([$id, $instructions]);
                        }
                    }
                }

                $pdo->commit();
                return jsonResponse($response, ['status' => 'success', 'message' => 'Page updated']);
            } catch (PDOException $e) {
                $pdo->rollBack();
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
