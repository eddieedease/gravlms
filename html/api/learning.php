<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

function registerLearningRoutes($app, $authMiddleware)
{
    $app->group('/api/learning', function ($group) {

        // Assign course to user (Admin only ideally, but for now authenticated)
        $group->post('/assign', function (Request $request, Response $response, $args) {
            $data = json_decode($request->getBody()->getContents(), true);
            $userId = $data['user_id'] ?? null;
            $courseId = $data['course_id'] ?? null;

            if (!$userId || !$courseId) {
                return jsonResponse($response, ['error' => 'User ID and Course ID are required'], 400);
            }

            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("INSERT INTO user_courses (user_id, course_id) VALUES (?, ?)");
                $stmt->execute([$userId, $courseId]);
                return jsonResponse($response, ['status' => 'success', 'message' => 'Course assigned successfully']);
            } catch (PDOException $e) {
                // Check for duplicate entry
                if ($e->getCode() == 23000) {
                    return jsonResponse($response, ['error' => 'Course already assigned to this user'], 409);
                }
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Detach course from user
        $group->delete('/assign/{userId}/{courseId}', function (Request $request, Response $response, $args) {
            $userId = $args['userId'];
            $courseId = $args['courseId'];

            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("DELETE FROM user_courses WHERE user_id = ? AND course_id = ?");
                $stmt->execute([$userId, $courseId]);

                if ($stmt->rowCount() > 0) {
                    return jsonResponse($response, ['status' => 'success', 'message' => 'Course detached successfully']);
                } else {
                    return jsonResponse($response, ['error' => 'Assignment not found'], 404);
                }
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Get assigned courses for logged-in user
        $group->get('/my-courses', function (Request $request, Response $response, $args) {
            $user = $request->getAttribute('user');
            $userId = $user->id;

            try {
                $pdo = getDbConnection();
                // Get courses assigned to user, include completion status if needed
                // For now just get the courses
                $sql = "SELECT c.*, 
                        (SELECT COUNT(*) FROM completed_courses cc WHERE cc.course_id = c.id AND cc.user_id = ?) as is_completed
                        FROM courses c
                        JOIN user_courses uc ON c.id = uc.course_id
                        WHERE uc.user_id = ?
                        ORDER BY c.display_order ASC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userId, $userId]);
                $courses = $stmt->fetchAll();
                return jsonResponse($response, $courses);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Get assigned courses for a specific user (Admin)
        $group->get('/user-courses/{userId}', function (Request $request, Response $response, $args) {
            $userId = $args['userId'];

            try {
                $pdo = getDbConnection();
                $sql = "SELECT c.* FROM courses c
                        JOIN user_courses uc ON c.id = uc.course_id
                        WHERE uc.user_id = ?
                        ORDER BY c.display_order ASC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userId]);
                $courses = $stmt->fetchAll();
                return jsonResponse($response, $courses);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Get course progress (lessons completed)
        $group->get('/progress/{courseId}', function (Request $request, Response $response, $args) {
            $user = $request->getAttribute('user');
            $userId = $user->id;
            $courseId = $args['courseId'];

            try {
                $pdo = getDbConnection();

                // Get all pages for the course
                $stmtPages = $pdo->prepare("SELECT id FROM course_pages WHERE course_id = ?");
                $stmtPages->execute([$courseId]);
                $pages = $stmtPages->fetchAll(PDO::FETCH_COLUMN);

                // Get completed pages for this course
                $stmtCompleted = $pdo->prepare("SELECT page_id FROM completed_lessons 
                                              WHERE user_id = ? AND page_id IN (SELECT id FROM course_pages WHERE course_id = ?)");
                $stmtCompleted->execute([$userId, $courseId]);
                $completedPages = $stmtCompleted->fetchAll(PDO::FETCH_COLUMN);

                return jsonResponse($response, [
                    'total_lessons' => count($pages),
                    'completed_lessons' => count($completedPages),
                    'completed_page_ids' => $completedPages
                ]);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Complete a lesson
        $group->post('/complete-lesson', function (Request $request, Response $response, $args) {
            $user = $request->getAttribute('user');
            $userId = $user->id;

            $data = json_decode($request->getBody()->getContents(), true);
            $pageId = $data['page_id'] ?? null;
            $courseId = $data['course_id'] ?? null; // Pass course_id to check for course completion

            if (!$pageId || !$courseId) {
                return jsonResponse($response, ['error' => 'Page ID and Course ID are required'], 400);
            }

            try {
                $pdo = getDbConnection();
                $pdo->beginTransaction();

                // 1. Mark lesson as complete
                $stmt = $pdo->prepare("INSERT IGNORE INTO completed_lessons (user_id, page_id) VALUES (?, ?)");
                $stmt->execute([$userId, $pageId]);

                // 2. Check if all lessons in the course are completed
                $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM course_pages WHERE course_id = ?");
                $stmtTotal->execute([$courseId]);
                $totalLessons = $stmtTotal->fetchColumn();

                $stmtCompleted = $pdo->prepare("SELECT COUNT(*) FROM completed_lessons cl 
                                              JOIN course_pages cp ON cl.page_id = cp.id 
                                              WHERE cl.user_id = ? AND cp.course_id = ?");
                $stmtCompleted->execute([$userId, $courseId]);
                $completedCount = $stmtCompleted->fetchColumn();

                $courseCompleted = false;
                if ($totalLessons > 0 && $completedCount == $totalLessons) {
                    // Mark course as complete
                    $stmtCourse = $pdo->prepare("INSERT IGNORE INTO completed_courses (user_id, course_id) VALUES (?, ?)");
                    $stmtCourse->execute([$userId, $courseId]);
                    $courseCompleted = true;
                }

                $pdo->commit();
                return jsonResponse($response, [
                    'status' => 'success',
                    'message' => 'Lesson completed',
                    'course_completed' => $courseCompleted
                ]);

            } catch (PDOException $e) {
                $pdo->rollBack();
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

    })->add($authMiddleware);
}
