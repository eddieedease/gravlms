<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/api/db.php';

try {
    echo "Testing database connection...\n";
    $pdo = getDbConnection();
    echo "Database connected!\n";

    echo "\nTesting user query...\n";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $user = $stmt->fetch();

    if ($user) {
        echo "User found: " . $user['username'] . "\n";
        echo "Testing password...\n";
        $result = password_verify('password', $user['password']);
        echo "Password verify result: " . ($result ? 'TRUE' : 'FALSE') . "\n";
    } else {
        echo "User NOT found!\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
