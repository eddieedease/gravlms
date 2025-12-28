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
                // Get courses assigned to user, include completion status, validity, and progress counts
                // Fetch last_completed_at and max validity_days (if in multiple groups)
                $sql = "SELECT DISTINCT c.*, 
                        (SELECT completed_at FROM completed_courses cc WHERE cc.course_id = c.id AND cc.user_id = ? ORDER BY completed_at DESC LIMIT 1) as last_completed_at,
                        (
                           SELECT MAX(gc.validity_days)
                           FROM group_courses gc
                           JOIN group_users gu ON gc.group_id = gu.group_id
                           WHERE gc.course_id = c.id AND gu.user_id = ?
                        ) as validity_days,
                        (SELECT COUNT(*) FROM course_pages WHERE course_id = c.id) as total_lessons,
                        (SELECT COUNT(*) FROM completed_lessons cl 
                           JOIN course_pages cp ON cl.page_id = cp.id 
                           WHERE cp.course_id = c.id AND cl.user_id = ?) as completed_lessons_count
                        FROM courses c
                        WHERE c.id IN (
                            SELECT course_id FROM user_courses WHERE user_id = ?
                            UNION
                            SELECT gc.course_id FROM group_courses gc 
                            JOIN group_users gu ON gc.group_id = gu.group_id 
                            WHERE gu.user_id = ?
                        )
                        ORDER BY c.display_order ASC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
                $courses = $stmt->fetchAll();

                // Process expiry and reset status
                foreach ($courses as &$course) {
                    $course['status'] = 'todo'; // default

                    // If course thinks it has history, check if it's actually fully completed right now
                    // If it was reset (progress deleted), completed_lessons_count will be 0 (or less than total)
                    $isProgressComplete = ($course['total_lessons'] > 0 && $course['completed_lessons_count'] == $course['total_lessons']);

                    if ($course['last_completed_at']) {
                        // It has been completed before

                        // BUT, if the user reset it (cleared progress), we should treat it as 'todo' 
                        // UNLESS they finished it again? 
                        // Actually, if I reset, I want it in 'todo'.
                        // So if progress is NOT complete, force 'todo' even if history exists.

                        if (!$isProgressComplete && $course['total_lessons'] > 0) {
                            $course['status'] = 'todo';
                        } else {
                            // Progress is complete (or 0 lessons), so check validity
                            $completedAt = new DateTime($course['last_completed_at']);
                            $validityDays = $course['validity_days']; // integer or null

                            if ($validityDays !== null) {
                                $expiresAt = clone $completedAt;
                                $expiresAt->modify("+$validityDays days");
                                $now = new DateTime();
                                if ($now > $expiresAt) {
                                    $course['status'] = 'expired';
                                    $course['expires_at'] = $expiresAt->format('Y-m-d H:i:s');
                                } else {
                                    $course['status'] = 'completed';
                                }
                            } else {
                                $course['status'] = 'completed';
                            }
                        }
                    }

                    // If it was never completed, it remains 'todo'
                }

                return jsonResponse($response, $courses);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Get all lessons for logged-in user's assigned courses
        $group->get('/my-lessons', function (Request $request, Response $response, $args) {
            $user = $request->getAttribute('user');
            $userId = $user->id;

            try {
                $pdo = getDbConnection();
                // Get all pages/lessons from courses assigned to this user
                $sql = "SELECT cp.id, cp.title, cp.type, cp.course_id, cp.display_order, c.title as course_title
                        FROM course_pages cp
                        JOIN courses c ON cp.course_id = c.id
                        WHERE c.id IN (
                            SELECT course_id FROM user_courses WHERE user_id = ?
                            UNION
                            SELECT gc.course_id FROM group_courses gc 
                            JOIN group_users gu ON gc.group_id = gu.group_id 
                            WHERE gu.user_id = ?
                        )
                        ORDER BY c.display_order ASC, cp.display_order ASC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userId, $userId]);
                $lessons = $stmt->fetchAll();

                return jsonResponse($response, $lessons);
            } catch (\Exception $e) {
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


        // Reset course (Student Retake)
        $group->post('/reset/{courseId}', function (Request $request, Response $response, $args) {
            $user = $request->getAttribute('user');
            $userId = $user->id;
            $courseId = $args['courseId'];

            try {
                $pdo = getDbConnection();
                // Delete completed lessons for this user/course to reset progress
                // But keep completed_courses history!

                // 1. Delete completed lessons for this user/course to reset progress (Green ticks)
                $sql = "DELETE cl FROM completed_lessons cl
                        JOIN course_pages cp ON cl.page_id = cp.id
                        WHERE cl.user_id = ? AND cp.course_id = ?";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userId, $courseId]);

                // 2. Archive completed assignments for this user/course
                // Update assessment_submissions setting archived_at = NOW()
                $sqlArchive = "UPDATE assessment_submissions asub
                               JOIN assessments a ON asub.assessment_id = a.id
                               JOIN course_pages cp ON a.page_id = cp.id
                               SET asub.archived_at = NOW()
                               WHERE asub.user_id = ? AND cp.course_id = ? AND asub.archived_at IS NULL";

                $stmtArchive = $pdo->prepare($sqlArchive);
                $stmtArchive->execute([$userId, $courseId]);

                return jsonResponse($response, ['status' => 'success', 'message' => 'Course reset successfully']);
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
                    $stmtCourse = $pdo->prepare("INSERT INTO completed_courses (user_id, course_id) VALUES (?, ?)");
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
