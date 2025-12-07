<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

function registerResultsRoutes($app, $authMiddleware)
{
    $app->group('/api/results', function ($group) {

        // Get Results (Searchable, Filterable)
        $group->get('', function (Request $request, Response $response, $args) {
            $user = $request->getAttribute('user');
            $queryParams = $request->getQueryParams();

            $groupId = $queryParams['group_id'] ?? null;
            $search = $queryParams['search'] ?? null;
            $courseId = $queryParams['course_id'] ?? null;
            $view = $queryParams['view'] ?? 'recent'; // 'recent' or 'course_progress'
            $statusFilter = $queryParams['status'] ?? 'all'; // 'all', 'completed', 'busy', 'not_started'

            $pdo = getDbConnection();

            // Permissions Check
            $allowedUserIds = []; 
            $isAdmin = ($user->role === 'admin');

            // Check if user is a monitor for specific groups
            $monitorGroupIds = [];
            if (!$isAdmin) {
                $stmt = $pdo->prepare("SELECT group_id FROM group_monitors WHERE user_id = ?");
                $stmt->execute([$user->id]);
                $monitorGroupIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (empty($monitorGroupIds)) {
                    return jsonResponse($response, ['error' => 'Access denied'], 403);
                }
            }

            if ($view === 'course_progress') {
                if (!$courseId) {
                    return jsonResponse($response, ['error' => 'Course ID is required for course progress view'], 400);
                }

                // 1. Get Total Pages for the course
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM course_pages WHERE course_id = ?");
                $stmt->execute([$courseId]);
                $totalPages = $stmt->fetchColumn();

                // 2. Build Query for Assigned Users
                // Users can be assigned directly OR via groups
                $sql = "SELECT DISTINCT u.id, u.username, u.email,
                               (SELECT GROUP_CONCAT(g.name SEPARATOR ', ') 
                                FROM `groups` g 
                                JOIN group_users gu2 ON g.id = gu2.group_id 
                                WHERE gu2.user_id = u.id) as group_names,
                               (SELECT COUNT(*) FROM completed_lessons cl 
                                JOIN course_pages cp ON cl.page_id = cp.id 
                                WHERE cl.user_id = u.id AND cp.course_id = ?) as completed_count
                        FROM users u
                        LEFT JOIN group_users gu ON u.id = gu.user_id
                        WHERE (
                            u.id IN (SELECT user_id FROM user_courses WHERE course_id = ?)
                            OR
                            gu.group_id IN (SELECT group_id FROM group_courses WHERE course_id = ?)
                        )";
                
                $params = [$courseId, $courseId, $courseId];

                // Access Control
                if (!$isAdmin) {
                    $placeholders = implode(',', array_fill(0, count($monitorGroupIds), '?'));
                    $sql .= " AND gu.group_id IN ($placeholders)";
                    $params = array_merge($params, $monitorGroupIds);
                }

                // Filters
                if ($groupId) {
                    if (!$isAdmin && !in_array($groupId, $monitorGroupIds)) {
                        return jsonResponse($response, ['error' => 'Access denied for this group'], 403);
                    }
                    $sql .= " AND gu.group_id = ?";
                    $params[] = $groupId;
                }

                if ($search) {
                    $sql .= " AND (u.username LIKE ? OR u.email LIKE ?)";
                    $term = "%$search%";
                    $params[] = $term;
                    $params[] = $term;
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Deduping users
                $uniqueUsers = [];
                foreach ($rows as $r) {
                    // Check status filter in PHP to keep SQL simpler (logic is easier here)
                    $completed = (int)$r['completed_count'];
                    $total = (int)$totalPages;
                    
                    $status = 'not_started';
                    if ($completed >= $total && $total > 0) {
                        $status = 'completed';
                    } elseif ($completed > 0) {
                        $status = 'busy';
                    }

                    if ($statusFilter !== 'all' && $status !== $statusFilter) {
                        continue;
                    }

                    $r['total_pages'] = $total;
                    $r['status'] = $status;
                    $r['progress_percent'] = ($total > 0) ? round(($completed / $total) * 100) : 0;
                    
                    $uniqueUsers[$r['id']] = $r;
                }

                return jsonResponse($response, array_values($uniqueUsers));

            } else {
                // DEFAULT VIEW: Recent Completions
                // Build Query
                $sql = "SELECT cc.id,
                               u.username, u.email, 
                               c.title as course_title,
                               g.name as group_name,
                               cc.completed_at
                        FROM completed_courses cc
                        JOIN users u ON cc.user_id = u.id
                        JOIN courses c ON cc.course_id = c.id
                        LEFT JOIN group_users gu ON u.id = gu.user_id 
                        LEFT JOIN `groups` g ON gu.group_id = g.id
                        WHERE 1=1";

                $params = [];

                // Access Control Filter
                if (!$isAdmin) {
                    // Monitor can only see results for users in their monitored groups
                    $placeholders = implode(',', array_fill(0, count($monitorGroupIds), '?'));
                    $sql .= " AND gu.group_id IN ($placeholders)";
                    $params = array_merge($params, $monitorGroupIds);
                }

                // Apply Filters
                if ($groupId) {
                    if (!$isAdmin && !in_array($groupId, $monitorGroupIds)) {
                        return jsonResponse($response, ['error' => 'Access denied for this group'], 403);
                    }
                    $sql .= " AND gu.group_id = ?";
                    $params[] = $groupId;
                }

                if ($courseId) {
                    $sql .= " AND c.id = ?";
                    $params[] = $courseId;
                }

                if ($search) {
                    $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR c.title LIKE ?)";
                    $term = "%$search%";
                    $params[] = $term;
                    $params[] = $term;
                    $params[] = $term;
                }

                // Order by latest
                $sql .= " ORDER BY cc.completed_at DESC LIMIT 500";

                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Deduping - same logic as before, user might appear multiple times if in multiple groups
                    $uniqueResults = [];
                    foreach ($results as $r) {
                        $uniqueResults[$r['id']] = $r;
                    }

                    return jsonResponse($response, array_values($uniqueResults));

                } catch (PDOException $e) {
                    return jsonResponse($response, ['error' => $e->getMessage()], 500);
                }
            }
        });

        // Export CSV
        $group->get('/export', function (Request $request, Response $response, $args) {
            $user = $request->getAttribute('user');
            $queryParams = $request->getQueryParams();

            // Re-use logic or abstract it? 
            // For MVP copypasta logic for permissions/query build is safest to avoid side effects refactoring `get`

            $groupId = $queryParams['group_id'] ?? null;
            $search = $queryParams['search'] ?? null;
            $pdo = getDbConnection();

            $isAdmin = ($user->role === 'admin');
            $monitorGroupIds = [];
            if (!$isAdmin) {
                $stmt = $pdo->prepare("SELECT group_id FROM group_monitors WHERE user_id = ?");
                $stmt->execute([$user->id]);
                $monitorGroupIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (empty($monitorGroupIds)) {
                    return jsonResponse($response, ['error' => 'Access denied'], 403);
                }
            }

            $sql = "SELECT u.username, u.email, 
                           c.title as course_title,
                           cc.completed_at,
                           g.name as group_name
                    FROM completed_courses cc
                    JOIN users u ON cc.user_id = u.id
                    JOIN courses c ON cc.course_id = c.id
                    LEFT JOIN group_users gu ON u.id = gu.user_id
                    LEFT JOIN `groups` g ON gu.group_id = g.id
                    WHERE 1=1";

            $params = [];

            if (!$isAdmin) {
                $placeholders = implode(',', array_fill(0, count($monitorGroupIds), '?'));
                $sql .= " AND gu.group_id IN ($placeholders)";
                $params = array_merge($params, $monitorGroupIds);
            }

            if ($groupId) {
                if (!$isAdmin && !in_array($groupId, $monitorGroupIds)) {
                    return jsonResponse($response, ['error' => 'Access denied for this group'], 403);
                }
                $sql .= " AND gu.group_id = ?";
                $params[] = $groupId;
            }

            if ($search) {
                $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR c.title LIKE ?)";
                $term = "%$search%";
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
            }

            $sql .= " ORDER BY cc.completed_at DESC";

            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stream = fopen('php://memory', 'w+');
                fputcsv($stream, ['Username', 'Email', 'Course', 'Completed At', 'Group']);

                $seenIds = []; // Handle duplicates from joins
                foreach ($results as $row) {
                    // Unique key
                    $key = $row['username'] . $row['course_title'] . $row['completed_at'];
                    if (isset($seenIds[$key]))
                        continue;
                    $seenIds[$key] = true;

                    fputcsv($stream, $row);
                }

                rewind($stream);
                $csv = stream_get_contents($stream);
                fclose($stream);

                $response->getBody()->write($csv);
                return $response
                    ->withHeader('Content-Type', 'text/csv')
                    ->withHeader('Content-Disposition', 'attachment; filename="results.csv"');

            } catch (PDOException $e) {
                return jsonResponse($response, ['error' => $e->getMessage()], 500);
            }
        });

    })->add($authMiddleware);
}
