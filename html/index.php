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

// simple test route
$app->get('/api/test', function (Request $request, Response $response, $args) {
    $data = ['status' => 'success', 'message' => 'Hello from Slim PHP Backend!'];
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

// Include API modules
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/helpers.php';
require_once __DIR__ . '/api/middleware.php';
require_once __DIR__ . '/api/auth.php';
require_once __DIR__ . '/api/users.php';
require_once __DIR__ . '/api/courses.php';
require_once __DIR__ . '/api/pages.php';
require_once __DIR__ . '/api/learning.php';
require_once __DIR__ . '/api/uploads.php';
require_once __DIR__ . '/api/groups.php';
require_once __DIR__ . '/api/tests.php';

// Create JWT middleware instance
$jwtMiddleware = jwtAuthMiddleware();

// Register routes from modules
registerAuthRoutes($app);
registerUserRoutes($app, $jwtMiddleware);
registerCourseRoutes($app, $jwtMiddleware);
registerPageRoutes($app, $jwtMiddleware);
registerLearningRoutes($app, $jwtMiddleware);
registerUploadRoutes($app, $jwtMiddleware);
registerGroupRoutes($app, $jwtMiddleware);
registerTestRoutes($app, $jwtMiddleware);

$app->run();