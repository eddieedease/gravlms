<?php
require_once __DIR__ . '/api/schema.php';

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST["action"];
    $host = $_POST["host"];
    $name = $_POST["name"];
    $user = $_POST["user"];
    $pass = $_POST["pass"];

    try {
        $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        if ($action === "master") {
            initializeMasterSchema($pdo);
            $message = "Master database initialized successfully!";
            $messageType = "success";
        } elseif ($action === "tenant") {
            initializeTenantSchema($pdo);
            $message = "Tenant database initialized successfully!";
            $messageType = "success";
        } elseif ($action === "migrate_all") {
            // New Multi-Tenant Migration
            require_once __DIR__ . '/migrate_tenants.php';
            // Capture output buffer to show in message? Or just run it and let it echo?
            // Since migrateAllTenants echoes directly, we might want to capture it or just let it print below.
            // But the UI expects $message. Let's capture output.
            ob_start();
            migrateAllTenants();
            $message = ob_get_clean();
            $messageType = "info"; // Status info
        }
    } catch (PDOException $e) {
        $message = "Connection failed: " . $e->getMessage();
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GravLMS Database Installer</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        h1 {
            margin-top: 0;
            font-size: 1.5rem;
            text-align: center;
            color: #1f2937;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
            font-size: 0.875rem;
        }

        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1rem;
        }

        button {
            width: 100%;
            padding: 0.75rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.2s;
        }

        button:hover {
            background: #2563eb;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .tabs {
            display: flex;
            margin-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .tab {
            flex: 1;
            text-align: center;
            padding: 0.75rem;
            cursor: pointer;
            color: #6b7280;
            font-weight: 500;
        }

        .tab.active {
            color: #3b82f6;
            border-bottom: 2px solid #3b82f6;
        }
    </style>
</head>

<body>

    <div class="container">
        <h1>GravLMS Installer</h1>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="action">Initialization Mode</label>
                <select name="action" id="action">
                    <option value="tenant">Tenant Database (Default)</option>
                    <option value="master">Master Database (Global)</option>
                    <option value="migrate_all">Update All Tenants (Multi-Tenant)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="host">Database Host</label>
                <input type="text" name="host" id="host" value="db" required>
            </div>

            <div class="form-group">
                <label for="name">Database Name</label>
                <input type="text" name="name" id="name" value="lms_tenant" required>
            </div>

            <div class="form-group">
                <label for="user">Database User</label>
                <input type="text" name="user" id="user" value="root" required>
            </div>

            <div class="form-group">
                <label for="pass">Database Password</label>
                <input type="password" name="pass" id="pass" value="root" required>
            </div>

            <button type="submit">Initialize Database</button>
        </form>
    </div>

</body>

</html>