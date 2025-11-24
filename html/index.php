<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();

// Add Error Middleware
$app->addErrorMiddleware(true, true, true);

// CORS Middleware
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:4200')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// Auth Middleware (kept in index to keep simple)
$authMiddleware = function (Request $request, $handler) {
    $authHeader = $request->getHeaderLine('Authorization');
    if (!$authHeader) {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }
    return $handler->handle($request);
};

// simple test route
$app->get('/api/test', function (Request $request, Response $response, $args) {
    $data = ['status' => 'success', 'message' => 'Hello from Slim PHP Backend!'];
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

// Include API modules
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/helpers.php';
require_once __DIR__ . '/api/auth.php';
require_once __DIR__ . '/api/users.php';
require_once __DIR__ . '/api/courses.php';
require_once __DIR__ . '/api/pages.php';

// Register routes from modules
registerAuthRoutes($app);
registerUserRoutes($app, $authMiddleware);
registerCourseRoutes($app, $authMiddleware);
registerPageRoutes($app, $authMiddleware);

$app->run();