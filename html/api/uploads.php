<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

function registerUploadRoutes($app, $authMiddleware)
{
    // Upload image (authenticated)
    $app->post('/api/uploads', function (Request $request, Response $response, $args) {
        $uploadedFiles = $request->getUploadedFiles();

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

        // Generate unique filename
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = uniqid('img_', true) . '.' . $extension;

        // In development (Docker), uploads go to /var/www/uploads (mounted from public/uploads)
        // In production (built app), uploads go to ../../uploads/ (root level)
        // Check if we're in Docker environment
        $uploadPath = file_exists('/var/www/uploads')
            ? '/var/www/uploads/' . $filename
            : __DIR__ . '/../../uploads/' . $filename;

        try {
            $uploadedFile->moveTo($uploadPath);

            return jsonResponse($response, [
                'status' => 'success',
                'filename' => $filename,
                'url' => '/api/uploads/' . $filename
            ], 201);
        } catch (Exception $e) {
            return jsonResponse($response, ['error' => 'Failed to save file: ' . $e->getMessage()], 500);
        }
    })->add($authMiddleware);

    // Serve uploaded image (public access)
    $app->get('/api/uploads/{filename}', function (Request $request, Response $response, $args) {
        $filename = $args['filename'];

        // Use same logic as upload: Docker dev vs production
        $filepath = file_exists('/var/www/uploads')
            ? '/var/www/uploads/' . $filename
            : __DIR__ . '/../../uploads/' . $filename;

        // Validate filename (prevent directory traversal)
        if (preg_match('/[^a-zA-Z0-9_\-\.]/', $filename) || strpos($filename, '..') !== false) {
            return $response->withStatus(400);
        }

        if (!file_exists($filepath)) {
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
