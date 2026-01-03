<?php

// include debug logging helper if not already included
require_once __DIR__ . '/lti_debug.php';

// Helper function to build OAuth base string
function buildOAuthBaseString($method, $url, $params)
{
    ksort($params);
    $pairs = [];
    foreach ($params as $key => $value) {
        if ($key !== 'oauth_signature') {
            $pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
    }
    $paramString = implode('&', $pairs);
    return strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
}

// Helper function to handle LTI 1.1 Basic Outcomes (XML-based)
function handleLti11Outcome($request, $response)
{
    ltiDebugLog(">>> Inside handleLti11Outcome function");

    $body = $request->getBody()->getContents();
    ltiDebugLog("Full XML body: " . $body);
    error_log("LTI 1.1 Outcome Request Body: " . $body);

    // Parse XML
    $xml = simplexml_load_string($body);
    if (!$xml) {
        ltiDebugLog("ERROR: Failed to parse XML");
        error_log("Failed to parse LTI 1.1 Outcome XML");
        return createLti11ErrorResponse($response, 'failure', 'Invalid XML');
    }

    ltiDebugLog("XML parsed successfully");

    // Extract data from XML
    $messageIdentifier = (string) $xml->imsx_POXHeader->imsx_POXRequestHeaderInfo->imsx_messageIdentifier;
    $sourcedid = (string) $xml->imsx_POXBody->replaceResultRequest->resultRecord->sourcedGUID->sourcedId;
    $score = null;

    if (isset($xml->imsx_POXBody->replaceResultRequest->resultRecord->result->resultScore->textString)) {
        $score = (float) $xml->imsx_POXBody->replaceResultRequest->resultRecord->result->resultScore->textString;
    }

    ltiDebugLog("Extracted - MessageID: $messageIdentifier");
    ltiDebugLog("Extracted - Sourcedid: $sourcedid");
    ltiDebugLog("Extracted - Score: " . ($score ?? 'null'));
    error_log("LTI 1.1 Outcome - Sourcedid: $sourcedid, Score: " . ($score ?? 'null'));

    // Decode sourcedid (format: user_id:course_id:tool_id:timestamp)
    $decoded = base64_decode($sourcedid);
    $parts = explode(':', $decoded);

    ltiDebugLog("Decoded sourcedid: $decoded");
    ltiDebugLog("Parts count: " . count($parts));

    if (count($parts) < 4) {
        ltiDebugLog("ERROR: Invalid sourcedid format (expected tenant:user:course:tool:timestamp)");
        error_log("Invalid sourcedid format: $decoded");
        return createLti11ErrorResponse($response, 'failure', 'Invalid sourcedid');
    }

    $tenantSlug = $parts[0];
    $userId = $parts[1];
    $courseId = $parts[2];
    $toolId = $parts[3];

    ltiDebugLog("Tenant: $tenantSlug");

    ltiDebugLog("User ID: $userId, Course ID: $courseId, Tool ID: $toolId");
    error_log("Extracted - User ID: $userId, Course ID: $courseId, Tool ID: $toolId");

    // Mark course as completed and store score
    try {
        ltiDebugLog("Attempting to mark course as completed...");
        ltiDebugLog("Connecting to tenant database: $tenantSlug");
        $pdo = getDbConnection($tenantSlug === 'default' ? null : $tenantSlug);
        ltiDebugLog("Database connection established for tenant: $tenantSlug");

        // Check if already completed
        // Check for existing **ACTIVE** completion (ignore archived ones)
        $stmt = $pdo->prepare("SELECT * FROM completed_courses WHERE user_id = ? AND course_id = ? AND archived_at IS NULL");
        $stmt->execute([$userId, $courseId]);
        $existing = $stmt->fetch();

        ltiDebugLog("Checked for existing ACTIVE completion: " . ($existing ? "FOUND" : "NOT FOUND"));

        if (!$existing) {
            // Mark course as completed
            ltiDebugLog("Inserting new completion record...");
            $stmt = $pdo->prepare("INSERT INTO completed_courses (user_id, course_id, completed_at) VALUES (?, ?, NOW())");
            $stmt->execute([$userId, $courseId]);

            // Auto-complete all lessons so the course appears 100% done in dashboard
            $stmtPages = $pdo->prepare("SELECT id FROM course_pages WHERE course_id = ?");
            $stmtPages->execute([$courseId]);
            $pageIds = $stmtPages->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($pageIds)) {
                $stmtLesson = $pdo->prepare("INSERT IGNORE INTO completed_lessons (user_id, page_id, course_id, completed_at) VALUES (?, ?, ?, NOW())");
                foreach ($pageIds as $pId) {
                    $stmtLesson->execute([$userId, $pId, $courseId]);
                }
                ltiDebugLog("Auto-completed " . count($pageIds) . " lessons for course $courseId");
            }
            ltiDebugLog("✅ SUCCESS: Course $courseId marked as completed for user $userId");
            error_log("Marked course $courseId as completed for user $userId with score " . ($score ?? 'null'));
        } else {
            ltiDebugLog("Course already completed, skipping insert");
            error_log("Course $courseId already completed for user $userId, score: " . ($score ?? 'null'));
        }

        ltiDebugLog("Returning success response to external tool");
        return createLti11SuccessResponse($response, $messageIdentifier);
    } catch (\Exception $e) {
        ltiDebugLog("❌ ERROR in database operation: " . $e->getMessage());
        ltiDebugLog("Stack trace: " . $e->getTraceAsString());
        error_log("Error processing LTI 1.1 outcome: " . $e->getMessage());
        return createLti11ErrorResponse($response, 'failure', $e->getMessage());
    }
}

// Helper function to handle LTI 1.3 Assignment and Grade Services (JSON-based)
function handleLti13Outcome($request, $response)
{
    $body = $request->getBody()->getContents();
    $data = json_decode($body, true);

    error_log("LTI 1.3 Outcome Request: " . json_encode($data));

    // Extract score and user info from the request
    $score = $data['scoreGiven'] ?? $data['score'] ?? null;
    $scoreMaximum = $data['scoreMaximum'] ?? 1.0;
    $userId = $data['userId'] ?? null;
    $activityProgress = $data['activityProgress'] ?? null;

    // Normalize score to 0-1 range
    if ($score !== null && $scoreMaximum > 0) {
        $normalizedScore = $score / $scoreMaximum;
    } else {
        $normalizedScore = null;
    }

    error_log("LTI 1.3 Outcome - User: $userId, Score: " . ($normalizedScore ?? 'null') . ", Progress: $activityProgress");

    // Return success response
    $response->getBody()->write(json_encode([
        'status' => 'success',
        'message' => 'Grade received'
    ]));
    return $response->withHeader('Content-Type', 'application/json');
}

// Helper to create LTI 1.1 success response (XML)
function createLti11SuccessResponse($response, $messageIdentifier)
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<imsx_POXEnvelopeResponse xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
    <imsx_POXHeader>
        <imsx_POXResponseHeaderInfo>
            <imsx_version>V1.0</imsx_version>
            <imsx_messageIdentifier>' . htmlspecialchars($messageIdentifier) . '</imsx_messageIdentifier>
            <imsx_statusInfo>
                <imsx_codeMajor>success</imsx_codeMajor>
                <imsx_severity>status</imsx_severity>
                <imsx_description>Score processed successfully</imsx_description>
            </imsx_statusInfo>
        </imsx_POXResponseHeaderInfo>
    </imsx_POXHeader>
    <imsx_POXBody>
        <replaceResultResponse/>
    </imsx_POXBody>
</imsx_POXEnvelopeResponse>';

    $response->getBody()->write($xml);
    return $response->withHeader('Content-Type', 'application/xml');
}

// Helper to create LTI 1.1 error response (XML)
function createLti11ErrorResponse($response, $codeMajor, $description)
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<imsx_POXEnvelopeResponse xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
    <imsx_POXHeader>
        <imsx_POXResponseHeaderInfo>
            <imsx_version>V1.0</imsx_version>
            <imsx_messageIdentifier>unknown</imsx_messageIdentifier>
            <imsx_statusInfo>
                <imsx_codeMajor>' . htmlspecialchars($codeMajor) . '</imsx_codeMajor>
                <imsx_severity>error</imsx_severity>
                <imsx_description>' . htmlspecialchars($description) . '</imsx_description>
            </imsx_statusInfo>
        </imsx_POXResponseHeaderInfo>
    </imsx_POXHeader>
    <imsx_POXBody/>
</imsx_POXEnvelopeResponse>';

    $response->getBody()->write($xml);
    return $response->withStatus(400)->withHeader('Content-Type', 'application/xml');
}

// Helper function to send grade back to external LMS (Provider Mode)
function sendGradeToExternalLms($userId, $courseId, $score = 1.0)
{
    try {
        $pdo = getDbConnection();

        // Get LTI launch context and Consumer Key by joining tables
        $stmt = $pdo->prepare("
            SELECT ctx.*, c.consumer_key 
            FROM lti_launch_context ctx 
            JOIN lti_consumers c ON ctx.consumer_id = c.id 
            WHERE ctx.user_id = ? AND ctx.course_id = ?
        ");
        $stmt->execute([$userId, $courseId]);
        $context = $stmt->fetch();

        if (!$context) {
            error_log("No LTI launch context found for user $userId, course $courseId");
            return false;
        }

        $outcomeServiceUrl = $context['outcome_service_url'];
        $sourcedid = $context['result_sourcedid'];
        $consumerSecret = $context['consumer_secret'];
        $consumerKey = $context['consumer_key'];

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
            'oauth_consumer_key' => $consumerKey,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_version' => '1.0'
        ];

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
