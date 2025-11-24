<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();

// Add Error Middleware
$app->addErrorMiddleware(true, true, true);

// Database Connection
function getDbConnection()
{
    $host = 'db';
    $db = 'my_app_db';
    $user = 'admin';
    $pass = 'admin';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    return new PDO($dsn, $user, $pass, $options);
}

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

// Auth Middleware
$authMiddleware = function (Request $request, $handler) {
    $authHeader = $request->getHeaderLine('Authorization');
    if (!$authHeader) {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }
    return $handler->handle($request);
};

$app->get('/api/test', function (Request $request, Response $response, $args) {
    $data = ['status' => 'success', 'message' => 'Hello from Slim PHP Backend!'];
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

// Login API
$app->post('/api/login', function (Request $request, Response $response, $args) {
    $data = json_decode($request->getBody()->getContents(), true);
    $username = $data['username'] ?? null;
    $password = $data['password'] ?? null;

    if (!$username || !$password) {
        $response->getBody()->write(json_encode(['error' => 'Username and password are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Generate a simple token (in a real app, use JWT)
            $token = base64_encode(random_bytes(32));
            // Ideally store token or use JWT. For now just return it.

            $response->getBody()->write(json_encode([
                'status' => 'success',
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } else {
            $response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// User CRUD
$app->group('/api/users', function ($group) use ($app) {
    // Get all users
    $group->get('', function (Request $request, Response $response, $args) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->query("SELECT id, username, role, created_at FROM users");
            $users = $stmt->fetchAll();
            $response->getBody()->write(json_encode($users));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Create user
    $group->post('', function (Request $request, Response $response, $args) {
        $data = json_decode($request->getBody()->getContents(), true);
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;
        $role = $data['role'] ?? 'editor';

        if (!$username || !$password) {
            $response->getBody()->write(json_encode(['error' => 'Username and password are required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt->execute([$username, $hashedPassword, $role]);

            $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'User created']));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Update user
    $group->put('/{id}', function (Request $request, Response $response, $args) {
        $id = $args['id'];
        $data = json_decode($request->getBody()->getContents(), true);
        $username = $data['username'] ?? null;
        $role = $data['role'] ?? null;

        try {
            $pdo = getDbConnection();
            $fields = [];
            $values = [];
            if ($username) {
                $fields[] = "username = ?";
                $values[] = $username;
            }
            if ($role) {
                $fields[] = "role = ?";
                $values[] = $role;
            }

            if (empty($fields)) {
                $response->getBody()->write(json_encode(['message' => 'No changes provided']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $values[] = $id;
            $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);

            $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'User updated']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Delete user
    $group->delete('/{id}', function (Request $request, Response $response, $args) {
        $id = $args['id'];
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'User deleted']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });
})->add($authMiddleware);

// Courses CRUD
$app->group('/api/courses', function ($group) use ($app) {
    // Get all courses
    $group->get('', function (Request $request, Response $response, $args) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->query("SELECT * FROM courses ORDER BY display_order ASC, created_at DESC");
            $courses = $stmt->fetchAll();
            $response->getBody()->write(json_encode($courses));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Create course
    $group->post('', function (Request $request, Response $response, $args) {
        $data = json_decode($request->getBody()->getContents(), true);
        $title = $data['title'] ?? null;
        $description = $data['description'] ?? '';

        if (!$title) {
            $response->getBody()->write(json_encode(['error' => 'Title is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("INSERT INTO courses (title, description) VALUES (?, ?)");
            $stmt->execute([$title, $description]);

            $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Course created']));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Update course
    $group->put('/{id}', function (Request $request, Response $response, $args) {
        $id = $args['id'];
        $data = json_decode($request->getBody()->getContents(), true);
        $title = $data['title'] ?? null;
        $description = $data['description'] ?? null;
        $display_order = $data['display_order'] ?? null;

        try {
            $pdo = getDbConnection();
            $fields = [];
            $values = [];
            if ($title) {
                $fields[] = "title = ?";
                $values[] = $title;
            }
            if ($description !== null) {
                $fields[] = "description = ?";
                $values[] = $description;
            }
            if ($display_order !== null) {
                $fields[] = "display_order = ?";
                $values[] = $display_order;
            }

            if (empty($fields)) {
                $response->getBody()->write(json_encode(['message' => 'No changes provided']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $values[] = $id;
            $sql = "UPDATE courses SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);

            $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Course updated']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Delete course
    $group->delete('/{id}', function (Request $request, Response $response, $args) {
        $id = $args['id'];
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->execute([$id]);
            $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Course deleted']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });
})->add($authMiddleware);

// Course Pages CRUD
$app->group('/api/pages', function ($group) use ($app) {
    // Get all pages
    $group->get('', function (Request $request, Response $response, $args) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->query("SELECT id, title, content, course_id, display_order, created_at FROM course_pages ORDER BY display_order ASC, created_at ASC");
            $pages = $stmt->fetchAll();
            $response->getBody()->write(json_encode($pages));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Create page
    $group->post('', function (Request $request, Response $response, $args) {
        $data = json_decode($request->getBody()->getContents(), true);
        $title = $data['title'] ?? null;
        $content = $data['content'] ?? '';
        $course_id = $data['course_id'] ?? null;

        if (!$title) {
            $response->getBody()->write(json_encode(['error' => 'Title is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("INSERT INTO course_pages (title, content, course_id) VALUES (?, ?, ?)");
            $stmt->execute([$title, $content, $course_id]);

            $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Page created']));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Update page
    $group->put('/{id}', function (Request $request, Response $response, $args) {
        $id = $args['id'];
        $data = json_decode($request->getBody()->getContents(), true);
        $title = $data['title'] ?? null;
        $content = $data['content'] ?? null;
        $course_id = $data['course_id'] ?? null;
        $display_order = $data['display_order'] ?? null;

        try {
            $pdo = getDbConnection();
            $fields = [];
            $values = [];
            if ($title) {
                $fields[] = "title = ?";
                $values[] = $title;
            }
            if ($content !== null) {
                $fields[] = "content = ?";
                $values[] = $content;
            }
            if ($course_id !== null) {
                $fields[] = "course_id = ?";
                $values[] = $course_id;
            }
            if ($display_order !== null) {
                $fields[] = "display_order = ?";
                $values[] = $display_order;
            }

            if (empty($fields)) {
                $response->getBody()->write(json_encode(['message' => 'No changes provided']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $values[] = $id;
            $sql = "UPDATE course_pages SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);

            $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Page updated']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });

    // Delete page
    $group->delete('/{id}', function (Request $request, Response $response, $args) {
        $id = $args['id'];
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("DELETE FROM course_pages WHERE id = ?");
            $stmt->execute([$id]);
            $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Page deleted']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    });
})->add($authMiddleware);

$app->run();