<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

function registerUploadRoutes($app, $authMiddleware)
{
    // Upload image (authenticated)
    $app->post('/api/uploads', function (Request $request, Response $response, $args) {
        $uploadedFiles = $request->getUploadedFiles();
        $params = $request->getParsedBody();

        $courseId = $params['course_id'] ?? null;
        $type = $params['type'] ?? 'misc'; // 'thumbnail', 'content', 'misc'

        if (!isset($uploadedFiles['image'])) {
            return jsonResponse($response, ['error' => 'No image file provided'], 400);
        }

        $uploadedFile = $uploadedFiles['image'];

        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return jsonResponse($response, ['error' => 'Upload failed'], 500);
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        $fileType = $uploadedFile->getClientMediaType();

        if (!in_array($fileType, $allowedTypes)) {
            return jsonResponse($response, ['error' => 'Only JPG and PNG images are allowed'], 400);
        }

        // Validate file size (max 5MB)
        $maxSize = 5 * 1024 * 1024;
        if ($uploadedFile->getSize() > $maxSize) {
            return jsonResponse($response, ['error' => 'File size must not exceed 5MB'], 400);
        }

        // Determine upload directory
        // Load config to get the upload path setting
        $config = require __DIR__ . '/../config.php';

        // Check if we're running from production (NOT in development html/ folder)
        // Production can be: dist/gravlms/browser/backend/ OR public_html/backend/ (FTP hosting)
        $isProduction = strpos(__DIR__, '/html/') === false && strpos(__DIR__, '\\html\\') === false;

        if ($isProduction) {
            // Production: uploads.php is in backend/api/
            // We want uploads to go to uploads/ (sibling of backend/)
            // Go up from api/ to backend/, then up to parent, then into uploads/
            $baseDir = dirname(dirname(__DIR__)) . '/uploads';
        } else {
            // Development: Override to use public/uploads for Angular dev server compatibility
            // Docker volume maps ./public/uploads to /var/www/uploads
            $baseDir = file_exists('/var/www/uploads') ? '/var/www/uploads' : __DIR__ . '/../../public/uploads';
        }

        // Debug logging (remove after testing)
        error_log("Upload Debug - __DIR__: " . __DIR__);
        error_log("Upload Debug - isProduction: " . ($isProduction ? 'true' : 'false'));
        error_log("Upload Debug - baseDir: " . $baseDir);

        // Multi-tenancy Buffer: Prepend Tenant Slug (default to 'main')
        $tenantSlug = $request->getHeaderLine('X-Tenant-ID') ?: 'main';
        // Sanitize slug just to be safe (alphanumeric only)
        $tenantSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $tenantSlug);

        $relPath = '/' . $tenantSlug;

        if ($courseId) {
            // Sanitized course ID to be safe
            $courseId = (int) $courseId;
            if ($type === 'thumbnail') {
                $relPath .= "/$courseId/thumbnails";
            } elseif ($type === 'content') {
                $relPath .= "/$courseId/content";
            } elseif ($type === 'assignment') {
                // Determine group ID for this user and course
                // Requires database connection
                try {
                    require_once __DIR__ . '/db.php'; // Ensure db is loaded
                    $pdo = getDbConnection();
                    $user = $request->getAttribute('user');
                    $userId = $user->id;

                    // Find group that links this user and course
                    $stmt = $pdo->prepare("
                        SELECT gu.group_id 
                        FROM group_users gu 
                        JOIN group_courses gc ON gu.group_id = gc.group_id 
                        WHERE gu.user_id = ? AND gc.course_id = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$userId, $courseId]);
                    $groupId = $stmt->fetchColumn();

                    if ($groupId) {
                        $relPath .= "/$groupId";
                    } else {
                        // Fallback if no group found (shouldn't happen if properly assigned)
                        $relPath .= "/$courseId/assignments/$userId";
                    }
                } catch (Exception $e) {
                    // Fallback
                    $relPath .= "/$courseId/assignments/error";
                }
            } else {
                $relPath .= "/$courseId/misc";
            }
        } elseif ($type === 'organization') {
            $relPath .= "/organization";
        } else {
            $relPath .= "";
        }

        $targetDir = $baseDir . $relPath;

        // Debug logging (remove after testing)
        error_log("Upload Debug - relPath: " . $relPath);
        error_log("Upload Debug - targetDir: " . $targetDir);

        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
            chmod($targetDir, 0777); // Ensure explicit 777
        }

        // Generate unique filename
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = uniqid('img_', true) . '.' . $extension;
        $targetPath = $targetDir . '/' . $filename;

        // Debug logging (remove after testing)
        error_log("Upload Debug - targetPath: " . $targetPath);

        try {
            $uploadedFile->moveTo($targetPath);
            chmod($targetPath, 0666); // Ensure readable/writable by host user

            // Debug logging (remove after testing)
            error_log("Upload Debug - File saved successfully to: " . $targetPath);

            // Return relative URL
            // Since we store in public/uploads, and public is web root for Angular, URL is /uploads/...
            // Path is usually /uploads/{tenant}/{folder}/{file}
            $urlKey = ltrim($relPath . '/' . $filename, '/');

            return jsonResponse($response, [
                'status' => 'success',
                'filename' => $urlKey,
                'url' => '/uploads/' . $urlKey
            ], 201);
        } catch (Exception $e) {
            return jsonResponse($response, ['error' => 'Failed to save file: ' . $e->getMessage()], 500);
        }
    })->add($authMiddleware);

    // Serve uploaded image (public access) - Not strictly needed if Angular serves public/, but good for backend logic/production
    $app->get('/api/uploads/{filename:.*}', function (Request $request, Response $response, $args) {
        $filename = $args['filename'];

        // Use same logic as upload: detect environment
        $isProduction = strpos(__DIR__, '/html/') === false && strpos(__DIR__, '\\html\\') === false;

        if ($isProduction) {
            $baseDir = dirname(dirname(__DIR__)) . '/uploads';
        } else {
            $baseDir = file_exists('/var/www/uploads') ? '/var/www/uploads' : __DIR__ . '/../../public/uploads';
        }

        $filepath = $baseDir . '/' . $filename;

        // Security check: Normalize path and check if it starts with baseDir
        $realBase = realpath($baseDir);
        $realPath = realpath($filepath);

        // Fallback for legacy uploads in html/uploads
        if (!file_exists($filepath)) {
            // Legacy fallbacks removed
        }

        if ($realPath === false || strpos($realPath, $realBase) !== 0) {
            // Invalid path or directory traversal attempt
            return $response->withStatus(404);
        }

        if (!file_exists($filepath) || !is_file($filepath)) {
            return $response->withStatus(404);
        }

        // Determine content type
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $contentType = 'application/octet-stream';

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $contentType = 'image/jpeg';
                break;
            case 'png':
                $contentType = 'image/png';
                break;
        }

        $response->getBody()->write(file_get_contents($filepath));
        return $response
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Content-Length', filesize($filepath));
    });
}
