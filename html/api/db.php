<?php
// Database connection helper for the backend
function getDbConnection()
{
    // Load configuration
    $config = require __DIR__ . '/../config.php';
    $dbConfig = $config['database'];

    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    return new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $options);
}

// Get configuration
function getConfig()
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config;
}
