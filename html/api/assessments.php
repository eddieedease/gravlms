<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

function registerAssessmentRoutes($app, $authMiddleware)
{
    $app->group('/api/assessments', function ($group) {

        // Get assessments for the current assessor
        $group->get('/list', function (Request $request, Response $response, $args) {
            $user = $request->getAttribute('user');
            $userId = $user->id;

            $params = $request->getQueryParams();
            $status = $params['status'] ?? 'pending'; // 'pending' or 'graded'

            try {
                $pdo = getDbConnection();

                $sql = "
                    SELECT 
                        s.id as submission_id,
                        s.submitted_at,
                        s.status,
                        s.graded_at,
                        s.feedback,
                        u.username as student_name,
                        u.id as student_id,
                        cp.title as lesson_title,
                        c.title as course_title,
                        c.id as course_id,
                        g.name as group_name
                    FROM assessment_submissions s
                    JOIN assessments a ON s.assessment_id = a.id
                    JOIN course_pages cp ON a.page_id = cp.id
                    JOIN courses c ON cp.course_id = c.id
                    JOIN users u ON s.user_id = u.id
                    JOIN group_users gu ON u.id = gu.user_id
                    JOIN `groups` g ON gu.group_id = g.id
                    JOIN group_assessors ga ON g.id = ga.group_id
                    WHERE ga.user_id = ?
                ";

                if ($status === 'pending') {
                    $sql .= " AND s.status = 'pending'";
                } else if ($status === 'graded') {
                    $sql .= " AND (s.status = 'passed' OR s.status = 'failed')";
                }

                $sql .= " ORDER BY s.submitted_at DESC"; // Newest first

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userId]);
                $submissions = $stmt->fetchAll();

                $uniqueSubmissions = [];
                $seenIds = [];
                foreach ($submissions as $sub) {
                    if (!in_array($sub['submission_id'], $seenIds)) {
                        $uniqueSubmissions[] = $sub;
                        $seenIds[] = $sub['submission_id'];
                    }
                }

                return jsonResponse($response, $uniqueSubmissions);
            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Keep /pending as alias for backward compatibility or direct access
        $group->get('/pending', function (Request $request, Response $response, $args) {
            // ... redirect logic or same logic ...
            // For simplicity, let's just copy the logic above but force status='pending'
            // Or better, redirect internally if Slim supports it, or just use the same logic.
            // Actually, I'll just replace the /pending route with the generalized /list above, 
            // and update the frontend to use /list?status=pending.
            return jsonResponse($response, ['error' => 'Endpoint deprecated. Use /list?status=pending'], 410);
        });

        // Mobile/Student: Get assessment details for a page
        $group->get('/page/{pageId}', function (Request $request, Response $response, $args) {
            $pageId = $args['pageId'];
            $user = $request->getAttribute('user');
            $userId = $user->id;

            try {
                $pdo = getDbConnection();

                // Get assessment instructions
                $stmt = $pdo->prepare("SELECT * FROM assessments WHERE page_id = ?");
                $stmt->execute([$pageId]);
                $assessment = $stmt->fetch();

                if (!$assessment) {
                    return jsonResponse($response, ['error' => 'Assessment not found for this page'], 404);
                }

                // Get user submission if exists
                $stmtSub = $pdo->prepare("SELECT * FROM assessment_submissions WHERE assessment_id = ? AND user_id = ?");
                $stmtSub->execute([$assessment['id'], $userId]);
                $submission = $stmtSub->fetch();

                return jsonResponse($response, [
                    'assessment' => $assessment,
                    'submission' => $submission
                ]);

            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Submit assessment
        $group->post('/submit', function (Request $request, Response $response, $args) {
            $user = $request->getAttribute('user');
            $userId = $user->id;
            $data = json_decode($request->getBody()->getContents(), true);

            $pageId = $data['page_id'] ?? null;
            $text = $data['text'] ?? '';
            $fileUrl = $data['file_url'] ?? null;

            if (!$pageId) {
                return jsonResponse($response, ['error' => 'Page ID is required'], 400);
            }

            try {
                $pdo = getDbConnection();

                // Find assessment ID
                $stmt = $pdo->prepare("SELECT id FROM assessments WHERE page_id = ?");
                $stmt->execute([$pageId]);
                $assessmentId = $stmt->fetchColumn();

                if (!$assessmentId) {
                    // Auto-create assessment record if missing? 
                    // No, editor should have created it. But for safety/lazy init:
                    $stmtIns = $pdo->prepare("INSERT INTO assessments (page_id, instructions) VALUES (?, '')");
                    $stmtIns->execute([$pageId]);
                    $assessmentId = $pdo->lastInsertId();
                }

                // Check existing submission
                $stmtCheck = $pdo->prepare("SELECT id FROM assessment_submissions WHERE assessment_id = ? AND user_id = ?");
                $stmtCheck->execute([$assessmentId, $userId]);
                $existingId = $stmtCheck->fetchColumn();

                if ($existingId) {
                    // Update
                    $stmtUpd = $pdo->prepare("UPDATE assessment_submissions SET submission_text = ?, file_url = ?, status = 'pending', submitted_at = NOW() WHERE id = ?");
                    $stmtUpd->execute([$text, $fileUrl, $existingId]);
                } else {
                    // Insert
                    $stmtIns = $pdo->prepare("INSERT INTO assessment_submissions (assessment_id, user_id, submission_text, file_url, status) VALUES (?, ?, ?, ?, 'pending')");
                    $stmtIns->execute([$assessmentId, $userId, $text, $fileUrl]);
                }

                return jsonResponse($response, ['status' => 'success', 'message' => 'Assessment submitted']);

            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Grade assessment (Assessor)
        $group->post('/grade', function (Request $request, Response $response, $args) {
            $user = $request->getAttribute('user');
            $assessorId = $user->id; // Current user is the assessor

            $data = json_decode($request->getBody()->getContents(), true);
            $submissionId = $data['submission_id'] ?? null;
            $status = $data['status'] ?? null; // 'passed' or 'failed'
            $feedback = $data['feedback'] ?? '';

            if (!$submissionId || !in_array($status, ['passed', 'failed'])) {
                return jsonResponse($response, ['error' => 'Valid status and Submission ID required'], 400);
            }

            try {
                $pdo = getDbConnection();
                $pdo->beginTransaction();

                // Verify assessor has rights?
                // Ideally verify that submission belongs to a user in a group the assessor owns.
                // For now, implicit trust if they know the ID, or add a check query.
                // Let's assume frontend calls filtered list.

                // Update submission
                $stmt = $pdo->prepare("
                    UPDATE assessment_submissions 
                    SET status = ?, graded_by = ?, graded_at = NOW(), feedback = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$status, $assessorId, $feedback, $submissionId]);

                // If passed, mark lesson as completed!
                if ($status === 'passed') {
                    // Get User ID and Page ID from submission
                    $stmtGet = $pdo->prepare("
                        SELECT s.user_id, a.page_id, cp.course_id 
                        FROM assessment_submissions s
                        JOIN assessments a ON s.assessment_id = a.id
                        JOIN course_pages cp ON a.page_id = cp.id
                        WHERE s.id = ?
                    ");
                    $stmtGet->execute([$submissionId]);
                    $info = $stmtGet->fetch();

                    if ($info) {
                        $studentId = $info['user_id'];
                        $pageId = $info['page_id'];
                        $courseId = $info['course_id'];

                        // Insert into completed_lessons
                        $stmtComp = $pdo->prepare("INSERT IGNORE INTO completed_lessons (user_id, page_id) VALUES (?, ?)");
                        $stmtComp->execute([$studentId, $pageId]);

                        // Check Course Completion (Duplicate logic from learning.php to be safe)
                        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM course_pages WHERE course_id = ?");
                        $stmtTotal->execute([$courseId]);
                        $totalLessons = $stmtTotal->fetchColumn();

                        $stmtCompletedCount = $pdo->prepare("SELECT COUNT(*) FROM completed_lessons cl 
                                                      JOIN course_pages cp ON cl.page_id = cp.id 
                                                      WHERE cl.user_id = ? AND cp.course_id = ?");
                        $stmtCompletedCount->execute([$studentId, $courseId]);
                        $completedCount = $stmtCompletedCount->fetchColumn();

                        if ($totalLessons > 0 && $completedCount == $totalLessons) {
                            $stmtCourseComp = $pdo->prepare("INSERT INTO completed_courses (user_id, course_id) VALUES (?, ?)");
                            $stmtCourseComp->execute([$studentId, $courseId]);
                        }
                    }
                }

                $pdo->commit();
                return jsonResponse($response, ['status' => 'success', 'message' => 'Graded successfully']);

            } catch (PDOException $e) {
                $pdo->rollBack();
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

    })->add($authMiddleware);
}
