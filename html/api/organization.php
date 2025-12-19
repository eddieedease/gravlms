<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

function registerOrganizationRoutes($app, $authMiddleware)
{
    // Get organization settings (Public for Login page)
    $app->get('/api/organization', function (Request $request, Response $response, $args) {
        $pdo = getDbConnection();

        // Lazy create table if not exists (Safety check for 500 error)
        try {
            $check = $pdo->query("SELECT 1 FROM organization_settings LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS organization_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                org_name VARCHAR(255) NOT NULL DEFAULT 'My Organization',
                org_slogan VARCHAR(255) DEFAULT '',
                org_main_color VARCHAR(50) DEFAULT '#3b82f6',
                org_logo_url VARCHAR(255) DEFAULT NULL,
                org_header_image_url VARCHAR(255) DEFAULT NULL,
                org_email VARCHAR(255) DEFAULT '',
                news_message_enabled TINYINT(1) DEFAULT 0,
                news_message_content TEXT
            )");
            $pdo->exec("INSERT INTO organization_settings (org_name) SELECT 'My Organization' WHERE NOT EXISTS (SELECT * FROM organization_settings)");
        }

        $stmt = $pdo->query("SELECT * FROM organization_settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings) {
            $settings = [
                'org_name' => 'My Organization',
                'org_slogan' => 'Learning for everyone',
                'org_main_color' => '#3b82f6',
                'org_logo_url' => null,
                'org_header_image_url' => null,
                'org_email' => '',
                'news_message_enabled' => 0,
                'news_message_content' => ''
            ];
        } else {
            // Cast boolean fields
            $settings['news_message_enabled'] = (bool) $settings['news_message_enabled'];
        }

        return jsonResponse($response, $settings);
    });

    // Update organization settings (Authenticated)
    $app->post('/api/organization/update', function (Request $request, Response $response, $args) {
        $data = json_decode($request->getBody()->getContents(), true);
        $pdo = getDbConnection();

        // Update the single row
        $sql = "UPDATE organization_settings SET 
            org_name = :org_name,
            org_slogan = :org_slogan,
            org_main_color = :org_main_color,
            org_logo_url = :org_logo_url,
            org_header_image_url = :org_header_image_url,
            org_email = :org_email,
            news_message_enabled = :news_message_enabled,
            news_message_content = :news_message_content
            WHERE id = (SELECT id FROM (SELECT id FROM organization_settings LIMIT 1) as t)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'org_name' => $data['org_name'] ?? '',
            'org_slogan' => $data['org_slogan'] ?? '',
            'org_main_color' => $data['org_main_color'] ?? '#3b82f6',
            'org_logo_url' => $data['org_logo_url'] ?? null,
            'org_header_image_url' => $data['org_header_image_url'] ?? null,
            'org_email' => $data['org_email'] ?? '',
            'news_message_enabled' => !empty($data['news_message_enabled']) ? 1 : 0,
            'news_message_content' => $data['news_message_content'] ?? ''
        ]);

        return jsonResponse($response, ['status' => 'success']);
    })->add($authMiddleware);
}
