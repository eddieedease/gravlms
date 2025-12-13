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

                // Combine data
                $portfolio = [
                    'user' => [
                        'username' => $user->username,
                        'email' => $user->email
                    ],
                    'completed_courses' => $completedCourses,
                    'test_results' => $testResults
                ];

                return jsonResponse($response, $portfolio);

            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

    })->add($authMiddleware);
}
