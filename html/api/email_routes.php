<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/email.php';

function registerEmailRoutes($app, $authMiddleware)
{
    // Send test email (admin only)
    $app->post('/api/admin/test-email', function (Request $request, Response $response, $args) {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return jsonResponse($response, ['error' => 'Valid email address is required'], 400);
        }

        // Get email configuration
        $config = getConfig();
        $emailConfig = $config['email'] ?? [];

        if (empty($emailConfig)) {
            return jsonResponse($response, ['error' => 'Email configuration not found'], 500);
        }

        // Create email service and send test email
        $emailService = new EmailService($emailConfig);
        $result = $emailService->sendTestEmail($email);

        if ($result['success']) {
            return jsonResponse($response, [
                'status' => 'success',
                'message' => $result['message']
            ], 200);
        } else {
            return jsonResponse($response, [
                'status' => 'error',
                'message' => $result['message']
            ], 500);
        }
    })->add($authMiddleware);
}
