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
        $baseDir = file_exists('/var/www/uploads') ? '/var/www/uploads' : __DIR__ . '/../../uploads';
        $relPath = '';

        if ($courseId) {
            // Sanitized course ID to be safe
            $courseId = (int) $courseId;
            if ($type === 'thumbnail') {
                $relPath = "/$courseId/thumbnails";
            } elseif ($type === 'content') {
                $relPath = "/$courseId/content";
            } else {
                $relPath = "/$courseId/misc";
            }
        } else {
            $relPath = ""; // Root or misc, as before. Let's keep root for backward compat if needed, or move to 'misc'
        }

        $targetDir = $baseDir . $relPath;

        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // Generate unique filename
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = uniqid('img_', true) . '.' . $extension;
        $targetPath = $targetDir . '/' . $filename;

        try {
            $uploadedFile->moveTo($targetPath);

            // Return relative URL
            // If path was constructed with subdirs, URL needs to match
            // Front controller for uploads needs to handle slashes in url?
            // Actually, the GET route below expects a {filename}. Slim 3 route args don't gobble slashes by default unless regex used.
            // We should update the GET route to allow paths.

            // Construct the URL to return. 
            // If we used subdirectories, the "filename" passed to GET API needs to include them.
            // e.g. "123/thumbnails/img_abc.jpg"
            // So we return 'url' => '/api/uploads/123/thumbnails/img_abc.jpg'

            $urlKey = $relPath ? ltrim($relPath . '/' . $filename, '/') : $filename;

            return jsonResponse($response, [
                'status' => 'success',
                'filename' => $urlKey,
                'url' => '/api/uploads/' . $urlKey
            ], 201);
        } catch (Exception $e) {
            return jsonResponse($response, ['error' => 'Failed to save file: ' . $e->getMessage()], 500);
        }
    })->add($authMiddleware);

    // Serve uploaded image (public access)
    // Use regex to allow slashes in filename param for subdirectories
    $app->get('/api/uploads/{filename:.*}', function (Request $request, Response $response, $args) {
        $filename = $args['filename'];

        // Use same logic as upload: Docker dev vs production
        $baseDir = file_exists('/var/www/uploads') ? '/var/www/uploads' : __DIR__ . '/../../uploads';
        $filepath = $baseDir . '/' . $filename;

        // Security check: Normalize path and check if it starts with baseDir
        $realBase = realpath($baseDir);
        $realPath = realpath($filepath);

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
