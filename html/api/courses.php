<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

function registerCourseRoutes($app, $authMiddleware)
{
    $app->group('/api/courses', function ($group) {
        $group->get('', function (Request $request, Response $response, $args) {
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->query("SELECT id, title, description, display_order, is_lti, lti_tool_id, custom_launch_url, image_url, created_at FROM courses ORDER BY display_order ASC, created_at DESC");
                $courses = $stmt->fetchAll();
                return jsonResponse($response, $courses);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        $group->get('/{id}', function (Request $request, Response $response, $args) {
            $id = $args['id'];
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
                $stmt->execute([$id]);
                $course = $stmt->fetch();

                if (!$course) {
                    return jsonResponse($response, ['error' => 'Course not found'], 404);
                }

                return jsonResponse($response, $course);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        $group->post('', function (Request $request, Response $response, $args) {
            $data = json_decode($request->getBody()->getContents(), true);
            try {
                $pdo = getDbConnection();

                // Properly handle LTI fields with type conversion
                // Convert to proper types, handling null, empty string, and actual values
                $isLti = 0; // Default to 0 (false)
                if (isset($data['is_lti']) && $data['is_lti'] !== '' && $data['is_lti'] !== null) {
                    $isLti = $data['is_lti'] ? 1 : 0;
                }

                $ltiToolId = null;
                if (isset($data['lti_tool_id']) && $data['lti_tool_id'] !== '' && $data['lti_tool_id'] !== null) {
                    $ltiToolId = (int) $data['lti_tool_id'];
                }

                $customLaunchUrl = null;
                if (isset($data['custom_launch_url']) && $data['custom_launch_url'] !== '' && $data['custom_launch_url'] !== null) {
                    $customLaunchUrl = $data['custom_launch_url'];
                }

                $stmt = $pdo->prepare("INSERT INTO courses (title, description, display_order, is_lti, lti_tool_id, custom_launch_url, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $data['title'],
                    $data['description'] ?? '',
                    $data['display_order'] ?? 0,
                    $isLti,
                    $ltiToolId,
                    $customLaunchUrl,
                    $data['image_url'] ?? null
                ]);
                $courseId = $pdo->lastInsertId();
                return jsonResponse($response, ['id' => $courseId, 'message' => 'Course created'], 201);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        $group->put('/{id}', function (Request $request, Response $response, $args) {
            $id = $args['id'];
            $data = json_decode($request->getBody()->getContents(), true);

            try {
                $pdo = getDbConnection();

                // Properly handle LTI fields with type conversion
                $isLti = 0;
                if (isset($data['is_lti']) && $data['is_lti'] !== '' && $data['is_lti'] !== null) {
                    $isLti = $data['is_lti'] ? 1 : 0;
                }

                $ltiToolId = null;
                if (isset($data['lti_tool_id']) && $data['lti_tool_id'] !== '' && $data['lti_tool_id'] !== null) {
                    $ltiToolId = (int) $data['lti_tool_id'];
                }

                $customLaunchUrl = null;
                if (isset($data['custom_launch_url']) && $data['custom_launch_url'] !== '' && $data['custom_launch_url'] !== null) {
                    $customLaunchUrl = $data['custom_launch_url'];
                }

                $stmt = $pdo->prepare("UPDATE courses SET title = ?, description = ?, display_order = ?, is_lti = ?, lti_tool_id = ?, custom_launch_url = ?, image_url = ? WHERE id = ?");
                $stmt->execute([
                    $data['title'],
                    $data['description'] ?? '',
                    $data['display_order'] ?? 0,
                    $isLti,
                    $ltiToolId,
                    $customLaunchUrl,
                    $data['image_url'] ?? null,
                    $id
                ]);
                return jsonResponse($response, ['message' => 'Course updated']);
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
