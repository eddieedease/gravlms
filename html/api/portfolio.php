<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

function registerPortfolioRoutes($app, $authMiddleware)
{
    $app->group('/api/portfolio', function ($group) {

        // Get User Portfolio (Completed Courses & Marks)
        $group->get('', function (Request $request, Response $response, $args) {
            $user = $request->getAttribute('user');
            $pdo = getDbConnection();

            try {
                // 1. Get Completed Courses
                $sqlCourses = "SELECT c.id, c.title, c.description, c.image_url, cc.completed_at
                               FROM completed_courses cc
                               JOIN courses c ON cc.course_id = c.id
                               WHERE cc.user_id = ?
                               ORDER BY cc.completed_at DESC";

                $stmt = $pdo->prepare($sqlCourses);
                $stmt->execute([$user->id]);
                $completedCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // 2. Get Test Results (Marks)
                // We want the BEST score for each test? Or the latest? 
                // Usually for a portfolio you show the best passed result.
                // Let's get the highest score per test for now.
                $sqlMarks = "SELECT t.id as test_id, cp.title as test_title, c.title as course_title, c.id as course_id, 
                                    MAX(tr.score) as score, MAX(tr.max_score) as max_score, tr.passed, MAX(tr.completed_at) as completed_at
                             FROM test_results tr
                             JOIN tests t ON tr.test_id = t.id
                             JOIN course_pages cp ON t.page_id = cp.id
                             LEFT JOIN courses c ON cp.course_id = c.id
                             WHERE tr.user_id = ? AND tr.passed = 1
                             GROUP BY t.id, cp.title, c.title, tr.passed
                             ORDER BY completed_at DESC";

                $stmtMark = $pdo->prepare($sqlMarks);
                $stmtMark->execute([$user->id]);
                $testResults = $stmtMark->fetchAll(PDO::FETCH_ASSOC);

                // 3. Get Assignment History (Archived Submissions + Active ones?)
                // User wants to see "original" too. So let's fetch all passed/graded assignments that are archived OR active?
                // Or just a separate list of "Assignment History".
                $sqlAssignments = "SELECT asub.id, asub.submission_text, asub.file_url, asub.submitted_at, asub.graded_at, asub.feedback, asub.status, asub.archived_at,
                                          cp.title as lesson_title, c.title as course_title
                                   FROM assessment_submissions asub
                                   JOIN assessments a ON asub.assessment_id = a.id
                                   JOIN course_pages cp ON a.page_id = cp.id
                                   JOIN courses c ON cp.course_id = c.id
                                   WHERE asub.user_id = ? AND (asub.archived_at IS NOT NULL OR asub.status != 'pending')
                                   ORDER BY asub.submitted_at DESC";

                $stmtAss = $pdo->prepare($sqlAssignments);
                $stmtAss->execute([$user->id]);
                $assignmentHistory = $stmtAss->fetchAll(PDO::FETCH_ASSOC);

                // Combine data
                $portfolio = [
                    'user' => [
                        'username' => $user->username,
                        'email' => $user->email
                    ],
                    'completed_courses' => $completedCourses,
                    'test_results' => $testResults,
                    'assignment_history' => $assignmentHistory
                ];

                return jsonResponse($response, $portfolio);

            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

    })->add($authMiddleware);
}
