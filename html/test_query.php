<?php
require_once __DIR__ . '/api/db.php';

try {
    $pdo = getDbConnection();
    $userId = 1; // As verify step 270 showed, Admin is ID 1 and Assessor for Group 1
    $status = 'pending';

    $sql = "
        SELECT 
            s.id as submission_id,
            s.status,
            u.username,
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
        AND s.status = 'pending'
        AND s.archived_at IS NULL
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($res) . " submissions.\n";
    print_r($res);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
