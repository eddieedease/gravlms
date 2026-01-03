<?php

// Helper function to send grade back to external LMS (Provider Mode)
function sendGradeToExternalLms($userId, $courseId, $score = 1.0)
{
    try {
        $pdo = getDbConnection();

        // Get LTI launch context
        $stmt = $pdo->prepare("SELECT * FROM lti_launch_context WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$userId, $courseId]);
        $context = $stmt->fetch();

        if (!$context) {
            error_log("No LTI launch context found for user $userId, course $courseId");
            return false;
        }

        $outcomeServiceUrl = $context['outcome_service_url'];
        $sourcedid = $context['result_sourcedid'];
        $consumerSecret = $context['consumer_secret'];

        // Build LTI 1.1 Basic Outcomes XML request
        $messageId = 'gravlms_' . uniqid();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<imsx_POXEnvelopeRequest xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
    <imsx_POXHeader>
        <imsx_POXRequestHeaderInfo>
            <imsx_version>V1.0</imsx_version>
            <imsx_messageIdentifier>' . $messageId . '</imsx_messageIdentifier>
        </imsx_POXRequestHeaderInfo>
    </imsx_POXHeader>
    <imsx_POXBody>
        <replaceResultRequest>
            <resultRecord>
                <sourcedGUID>
                    <sourcedId>' . htmlspecialchars($sourcedid) . '</sourcedId>
                </sourcedGUID>
                <result>
                    <resultScore>
                        <language>en</language>
                        <textString>' . number_format($score, 2) . '</textString>
                    </resultScore>
                </result>
            </resultRecord>
        </replaceResultRequest>
    </imsx_POXBody>
</imsx_POXEnvelopeRequest>';

        // Generate OAuth signature for the request
        $oauthParams = [
            'oauth_body_hash' => base64_encode(sha1($xml, true)),
            'oauth_consumer_key' => $context['consumer_id'], // This should be the consumer key, not ID
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_version' => '1.0'
        ];

        // Use buildOAuthBaseString if available
        if (!function_exists('buildOAuthBaseString')) {
            require_once __DIR__ . '/lti_routes.php';
        }

        $baseString = buildOAuthBaseString('POST', $outcomeServiceUrl, $oauthParams);
        $signature = base64_encode(hash_hmac('sha1', $baseString, $consumerSecret . '&', true));
        $oauthParams['oauth_signature'] = $signature;

        // Build OAuth Authorization header
        $authHeader = 'OAuth ';
        $authParts = [];
        foreach ($oauthParams as $key => $value) {
            $authParts[] = $key . '="' . rawurlencode($value) . '"';
        }
        $authHeader .= implode(', ', $authParts);

        // Send HTTP POST request to external LMS
        $ch = curl_init($outcomeServiceUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/xml',
            'Authorization: ' . $authHeader
        ]);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log("Grade passback to external LMS - HTTP $httpCode - Response: $responseBody");

        return $httpCode >= 200 && $httpCode < 300;

    } catch (\Exception $e) {
        error_log("Error sending grade to external LMS: " . $e->getMessage());
        return false;
    }
}
