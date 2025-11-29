<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

function registerTestRoutes($app, $jwtMiddleware)
{
    $app->group('/api', function ($group) {

        // Get test details by page_id (or test_id? Let's use page_id for convenience in frontend if possible, but standard is test_id)
        // Actually, we need to fetch test details given a page_id often.
        // Let's keep /tests/{id} as fetching by TEST ID.
        // And maybe /pages/{id}/test to get test by PAGE ID?
        // Or just use /tests/{id} and frontend manages the mapping.
        // Let's stick to /tests/{id} gets by TEST ID.
        // But we need a way to find the test ID for a page.

        // Get test by Page ID
        $group->get('/pages/{id}/test', function (Request $request, Response $response, $args) {
            $pageId = $args['id'];
            $pdo = getDbConnection();

            $stmt = $pdo->prepare("SELECT * FROM tests WHERE page_id = ?");
            $stmt->execute([$pageId]);
            $test = $stmt->fetch();

            if (!$test) {
                // If it's a test page but no test record exists yet, return empty or 404?
                // Better to return 404 or null
                return jsonResponse($response, ['error' => 'Test not found'], 404);
            }

            // Get questions
            $stmt = $pdo->prepare("SELECT * FROM test_questions WHERE test_id = ? ORDER BY display_order ASC");
            $stmt->execute([$test['id']]);
            $questions = $stmt->fetchAll();

            // Get options
            foreach ($questions as &$question) {
                $stmt = $pdo->prepare("SELECT * FROM test_question_options WHERE question_id = ?");
                $stmt->execute([$question['id']]);
                $question['options'] = $stmt->fetchAll();
            }

            $test['questions'] = $questions;

            return jsonResponse($response, $test);
        });

        // Create/Update test for a page
        // We can use POST /tests to create/update based on page_id
        $group->post('/tests', function (Request $request, Response $response, $args) {
            $data = json_decode($request->getBody()->getContents(), true);
            $pdo = getDbConnection();

            $pageId = $data['page_id'];
            if (!$pageId) {
                return jsonResponse($response, ['error' => 'Page ID is required'], 400);
            }

            try {
                $pdo->beginTransaction();

                // Check if test exists for this page
                $stmt = $pdo->prepare("SELECT id FROM tests WHERE page_id = ?");
                $stmt->execute([$pageId]);
                $existingTest = $stmt->fetch();

                if ($existingTest) {
                    $testId = $existingTest['id'];
                    $stmt = $pdo->prepare("UPDATE tests SET description = ? WHERE id = ?");
                    $stmt->execute([$data['description'] ?? '', $testId]);

                    // Replace questions (simplistic approach)
                    $stmt = $pdo->prepare("DELETE FROM test_questions WHERE test_id = ?");
                    $stmt->execute([$testId]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO tests (page_id, description) VALUES (?, ?)");
                    $stmt->execute([$pageId, $data['description'] ?? '']);
                    $testId = $pdo->lastInsertId();
                }

                // Add questions
                if (isset($data['questions']) && is_array($data['questions'])) {
                    foreach ($data['questions'] as $qIndex => $q) {
                        $stmt = $pdo->prepare("INSERT INTO test_questions (test_id, question_text, type, display_order) VALUES (?, ?, ?, ?)");
                        $stmt->execute([
                            $testId,
                            $q['question_text'],
                            $q['type'] ?? 'multiple_choice',
                            $q['display_order'] ?? $qIndex
                        ]);
                        $questionId = $pdo->lastInsertId();

                        if (isset($q['options']) && is_array($q['options'])) {
                            foreach ($q['options'] as $opt) {
                                $stmt = $pdo->prepare("INSERT INTO test_question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                                $stmt->execute([
                                    $questionId,
                                    $opt['option_text'],
                                    $opt['is_correct'] ? 1 : 0
                                ]);
                            }
                        }
                    }
                }

                $pdo->commit();
                return jsonResponse($response, ['status' => 'success', 'id' => $testId]);

            } catch (Exception $e) {
                $pdo->rollBack();
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

        // Submit Test
        $group->post('/tests/{id}/submit', function (Request $request, Response $response, $args) {
            $testId = $args['id'];
            $data = json_decode($request->getBody()->getContents(), true);
            $userAnswers = $data['answers'] ?? []; // question_id -> [option_ids]
            $userId = $request->getAttribute('user_id'); // From JWT

            $pdo = getDbConnection();

            // Calculate score
            $stmt = $pdo->prepare("SELECT * FROM test_questions WHERE test_id = ?");
            $stmt->execute([$testId]);
            $questions = $stmt->fetchAll();

            $correctCount = 0;
            $totalQuestions = count($questions);

            foreach ($questions as $q) {
                $stmt = $pdo->prepare("SELECT id FROM test_question_options WHERE question_id = ? AND is_correct = 1");
                $stmt->execute([$q['id']]);
                $correctOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $userSelected = $userAnswers[$q['id']] ?? [];

                // Check if arrays match
                sort($correctOptions);
                sort($userSelected);

                if ($correctOptions == $userSelected) {
                    $correctCount++;
                }
            }

            $passed = $correctCount == $totalQuestions; // Strict passing for now

            if ($passed) {
                // Mark page as completed
                // First get page_id
                $stmt = $pdo->prepare("SELECT page_id FROM tests WHERE id = ?");
                $stmt->execute([$testId]);
                $test = $stmt->fetch();

                if ($test) {
                    $pageId = $test['page_id'];
                    // Insert into completed_lessons
                    $stmt = $pdo->prepare("INSERT IGNORE INTO completed_lessons (user_id, page_id) VALUES (?, ?)");
                    $stmt->execute([$userId, $pageId]);
                }
            }

            return jsonResponse($response, [
                'passed' => $passed,
                'score' => $correctCount,
                'total' => $totalQuestions
            ]);
        });

    })->add($jwtMiddleware);
}
