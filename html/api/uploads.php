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
        // Store in project root 'public/uploads' directory
        // Docker volume maps ./public/uploads to /var/www/uploads
        $baseDir = file_exists('/var/www/uploads') ? '/var/www/uploads' : __DIR__ . '/../../public/uploads';
        $relPath = '';

        if ($courseId) {
            // Sanitized course ID to be safe
            $courseId = (int) $courseId;
            if ($type === 'thumbnail') {
                $relPath = "/$courseId/thumbnails";
            } elseif ($type === 'content') {
                $relPath = "/$courseId/content";
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
                        $relPath = "/$groupId";
                    } else {
                        // Fallback if no group found (shouldn't happen if properly assigned)
                        $relPath = "/$courseId/assignments/$userId";
                    }
                } catch (Exception $e) {
                    // Fallback
                    $relPath = "/$courseId/assignments/error";
                }
            } else {
                $relPath = "/$courseId/misc";
            }
        } elseif ($type === 'organization') {
            $relPath = "/organization";
        } else {
            $relPath = "";
        }

        $targetDir = $baseDir . $relPath;

        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
            chmod($targetDir, 0777); // Ensure explicit 777
        }

        // Generate unique filename
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = uniqid('img_', true) . '.' . $extension;
        $targetPath = $targetDir . '/' . $filename;

        try {
            $uploadedFile->moveTo($targetPath);
            chmod($targetPath, 0666); // Ensure readable/writable by host user

            // Return relative URL
            // Since we store in public/uploads, and public is web root for Angular, URL is /uploads/...
            $urlKey = $relPath ? ltrim($relPath . '/' . $filename, '/') : $filename;

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

        // Use same logic as upload: Docker dev vs production
        $baseDir = file_exists('/var/www/uploads') ? '/var/www/uploads' : __DIR__ . '/../../public/uploads';
        $filepath = $baseDir . '/' . $filename;

        // Security check: Normalize path and check if it starts with baseDir
        $realBase = realpath($baseDir);
        $realPath = realpath($filepath);

        // Fallback for legacy uploads in html/uploads
        if (!file_exists($filepath)) {
            $legacyBase = file_exists('/var/www/html/uploads') ? '/var/www/html/uploads' : __DIR__ . '/../uploads';
            $legacyPath = $legacyBase . '/' . $filename;
            if (file_exists($legacyPath)) {
                $baseDir = $legacyBase;
                $filepath = $legacyPath;
                $realBase = realpath($baseDir);
                $realPath = realpath($filepath);
            }
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
