<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

function registerGroupRoutes($app, $authMiddleware)
{
    $app->group('/api/groups', function ($group) {

        // List all groups
        $group->get('', function (Request $request, Response $response, $args) {
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->query("SELECT * FROM `groups` ORDER BY created_at DESC");
                $groups = $stmt->fetchAll();
                return jsonResponse($response, $groups);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Create a group
        $group->post('', function (Request $request, Response $response, $args) {
            $data = json_decode($request->getBody()->getContents(), true);
            $name = $data['name'] ?? null;
            $description = $data['description'] ?? '';

            if (!$name) {
                return jsonResponse($response, ['error' => 'Group name is required'], 400);
            }

            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("INSERT INTO `groups` (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                return jsonResponse($response, ['status' => 'success', 'message' => 'Group created'], 201);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Update a group
        $group->put('/{id}', function (Request $request, Response $response, $args) {
            $id = $args['id'];
            $data = json_decode($request->getBody()->getContents(), true);
            $name = $data['name'] ?? null;
            $description = $data['description'] ?? null;

            try {
                $pdo = getDbConnection();
                $fields = [];
                $values = [];

                if ($name) {
                    $fields[] = "name = ?";
                    $values[] = $name;
                }
                if ($description !== null) {
                    $fields[] = "description = ?";
                    $values[] = $description;
                }

                if (empty($fields)) {
                    return jsonResponse($response, ['message' => 'No changes provided']);
                }

                $values[] = $id;
                $fields[] = "updated_at = NOW()";
                $sql = "UPDATE `groups` SET " . implode(', ', $fields) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);

                return jsonResponse($response, ['status' => 'success', 'message' => 'Group updated']);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Delete a group
        $group->delete('/{id}', function (Request $request, Response $response, $args) {
            $id = $args['id'];
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("DELETE FROM `groups` WHERE id = ?");
                $stmt->execute([$id]);
                return jsonResponse($response, ['status' => 'success', 'message' => 'Group deleted']);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Add user to group
        $group->post('/{groupId}/users', function (Request $request, Response $response, $args) {
            $groupId = $args['groupId'];
            $data = json_decode($request->getBody()->getContents(), true);
            $userId = $data['user_id'] ?? null;

            if (!$userId) {
                return jsonResponse($response, ['error' => 'User ID is required'], 400);
            }

            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("INSERT INTO group_users (group_id, user_id) VALUES (?, ?)");
                $stmt->execute([$groupId, $userId]);
                return jsonResponse($response, ['status' => 'success', 'message' => 'User added to group']);
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    return jsonResponse($response, ['error' => 'User already in group'], 409);
                }
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Remove user from group
        $group->delete('/{groupId}/users/{userId}', function (Request $request, Response $response, $args) {
            $groupId = $args['groupId'];
            $userId = $args['userId'];

            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("DELETE FROM group_users WHERE group_id = ? AND user_id = ?");
                $stmt->execute([$groupId, $userId]);
                return jsonResponse($response, ['status' => 'success', 'message' => 'User removed from group']);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Get users in group
        $group->get('/{groupId}/users', function (Request $request, Response $response, $args) {
            $groupId = $args['groupId'];
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("
                    SELECT u.id, u.username, u.role 
                    FROM users u 
                    JOIN group_users gu ON u.id = gu.user_id 
                    WHERE gu.group_id = ?
                ");
                $stmt->execute([$groupId]);
                $users = $stmt->fetchAll();
                return jsonResponse($response, $users);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Add course to group
        $group->post('/{groupId}/courses', function (Request $request, Response $response, $args) {
            $groupId = $args['groupId'];
            $data = json_decode($request->getBody()->getContents(), true);
            $courseId = $data['course_id'] ?? null;

            if (!$courseId) {
                return jsonResponse($response, ['error' => 'Course ID is required'], 400);
            }

            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("INSERT INTO group_courses (group_id, course_id) VALUES (?, ?)");
                $stmt->execute([$groupId, $courseId]);
                return jsonResponse($response, ['status' => 'success', 'message' => 'Course added to group']);
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    return jsonResponse($response, ['error' => 'Course already in group'], 409);
                }
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Remove course from group
        $group->delete('/{groupId}/courses/{courseId}', function (Request $request, Response $response, $args) {
            $groupId = $args['groupId'];
            $courseId = $args['courseId'];

            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("DELETE FROM group_courses WHERE group_id = ? AND course_id = ?");
                $stmt->execute([$groupId, $courseId]);
                return jsonResponse($response, ['status' => 'success', 'message' => 'Course removed from group']);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Get courses in group
        $group->get('/{groupId}/courses', function (Request $request, Response $response, $args) {
            $groupId = $args['groupId'];
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("
                    SELECT c.* 
                    FROM courses c 
                    JOIN group_courses gc ON c.id = gc.course_id 
                    WHERE gc.group_id = ?
                ");
                $stmt->execute([$groupId]);
                $courses = $stmt->fetchAll();
                return jsonResponse($response, $courses);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

    })->add($authMiddleware);
}
