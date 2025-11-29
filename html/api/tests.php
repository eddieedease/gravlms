<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

function registerTestRoutes($app, $jwtMiddleware)
{
    $app->group('/api', function ($group) {

        // Get tests for a course
        $group->get('/courses/{id}/tests', function (Request $request, Response $response, $args) {
            $courseId = $args['id'];
            $pdo = getDbConnection();

            $stmt = $pdo->prepare("SELECT * FROM tests WHERE course_id = ? ORDER BY display_order ASC");
            $stmt->execute([$courseId]);
            $tests = $stmt->fetchAll();

            $response->getBody()->write(json_encode($tests));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Get a specific test with questions and options
        $group->get('/tests/{id}', function (Request $request, Response $response, $args) {
            $testId = $args['id'];
            $pdo = getDbConnection();

            // Get test details
            $stmt = $pdo->prepare("SELECT * FROM tests WHERE id = ?");
            $stmt->execute([$testId]);
            $test = $stmt->fetch();

            if (!$test) {
                $response->getBody()->write(json_encode(['error' => 'Test not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Get questions
            $stmt = $pdo->prepare("SELECT * FROM test_questions WHERE test_id = ? ORDER BY display_order ASC");
            $stmt->execute([$testId]);
            $questions = $stmt->fetchAll();

            // Get options for each question
            foreach ($questions as &$question) {
                $stmt = $pdo->prepare("SELECT * FROM test_question_options WHERE question_id = ?");
                $stmt->execute([$question['id']]);
                $question['options'] = $stmt->fetchAll();
            }

            $test['questions'] = $questions;

            $response->getBody()->write(json_encode($test));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Create a new test
        $group->post('/tests', function (Request $request, Response $response, $args) {
            $data = json_decode($request->getBody()->getContents(), true);
            $pdo = getDbConnection();

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO tests (course_id, title, description, display_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $data['course_id'],
                    $data['title'],
                    $data['description'] ?? '',
                    $data['display_order'] ?? 0
                ]);
                $testId = $pdo->lastInsertId();

                // Add questions if provided
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

                $response->getBody()->write(json_encode(['status' => 'success', 'id' => $testId]));
                return $response->withHeader('Content-Type', 'application/json');

            } catch (Exception $e) {
                $pdo->rollBack();
                $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        });

        // Update a test
        $group->put('/tests/{id}', function (Request $request, Response $response, $args) {
            $testId = $args['id'];
            $data = json_decode($request->getBody()->getContents(), true);
            $pdo = getDbConnection();

            try {
                $pdo->beginTransaction();

                // Update test details
                $stmt = $pdo->prepare("UPDATE tests SET title = ?, description = ?, display_order = ? WHERE id = ?");
                $stmt->execute([
                    $data['title'],
                    $data['description'] ?? '',
                    $data['display_order'] ?? 0,
                    $testId
                ]);

                // For simplicity, we'll delete existing questions and re-add them
                // In a real app, you might want to be smarter about this to preserve IDs/stats

                // Delete existing questions (cascade will handle options)
                // Actually, we need to be careful. If we just delete, we lose history if we had it.
                // But for this MVP, full replacement is easier.

                $stmt = $pdo->prepare("DELETE FROM test_questions WHERE test_id = ?");
                $stmt->execute([$testId]);

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

                $response->getBody()->write(json_encode(['status' => 'success']));
                return $response->withHeader('Content-Type', 'application/json');

            } catch (Exception $e) {
                $pdo->rollBack();
                $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        });

        // Delete a test
        $group->delete('/tests/{id}', function (Request $request, Response $response, $args) {
            $testId = $args['id'];
            $pdo = getDbConnection();

            $stmt = $pdo->prepare("DELETE FROM tests WHERE id = ?");
            $stmt->execute([$testId]);

            $response->getBody()->write(json_encode(['status' => 'success']));
            return $response->withHeader('Content-Type', 'application/json');
        });

    })->add($jwtMiddleware);
}
