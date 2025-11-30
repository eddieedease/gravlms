<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Email helper using PHPMailer
 */
class EmailService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Send an email
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string|null $altBody Plain text alternative body
     * @return array ['success' => bool, 'message' => string]
     */
    public function send(string $to, string $subject, string $body, ?string $altBody = null): array
    {
        // Check if email is enabled
        if (!$this->config['enabled']) {
            return ['success' => false, 'message' => 'Email functionality is disabled'];
        }

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->config['smtp_host'];
            $mail->SMTPAuth = $this->config['smtp_auth'];
            $mail->Username = $this->config['smtp_username'];
            $mail->Password = $this->config['smtp_password'];
            $mail->SMTPSecure = $this->config['smtp_secure'];
            $mail->Port = $this->config['smtp_port'];

            // Recipients
            $mail->setFrom($this->config['from_address'], $this->config['from_name']);
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            if ($altBody) {
                $mail->AltBody = $altBody;
            }

            $mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"];
        }
    }

    /**
     * Send a test email
     *
     * @param string $to Recipient email address
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendTestEmail(string $to): array
    {
        $subject = 'Test Email from LMS';
        $body = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #4F46E5; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background-color: #f9f9f9; }
                    .footer { padding: 10px; text-align: center; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>LMS Test Email</h1>
                    </div>
                    <div class="content">
                        <h2>Email Configuration Successful!</h2>
                        <p>This is a test email from your LMS system.</p>
                        <p>If you received this email, your email configuration is working correctly.</p>
                        <p><strong>Sent at:</strong> ' . date('Y-m-d H:i:s') . '</p>
                    </div>
                    <div class="footer">
                        <p>This is an automated message from your LMS system.</p>
                    </div>
                </div>
            </body>
            </html>
        ';
        $altBody = 'This is a test email from your LMS system. If you received this email, your email configuration is working correctly.';

        return $this->send($to, $subject, $body, $altBody);
    }
}
