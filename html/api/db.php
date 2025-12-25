<?php
// Database connection helper for the backend
function getMasterDbConnection()
{
    $config = getConfig();
    $dbConfig = $config['master_database'] ?? $config['database']; // Fallback if not set

    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    return new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $options);
}

function getDbConnection($tenantSlug = null)
{
    // 1. Try to get tenant slug from argument or header
    if (!$tenantSlug) {
        // $_SERVER is more reliable for global access than getallheaders() in some environments
        $tenantSlug = $_SERVER['HTTP_X_TENANT_ID'] ?? null;
    }

    // 2. If valid slug, try to resolve from Master DB
    if ($tenantSlug) {
        try {
            $pdoMaster = getMasterDbConnection();
            $stmt = $pdoMaster->prepare("SELECT * FROM tenants WHERE slug = ?");
            $stmt->execute([$tenantSlug]);
            $tenant = $stmt->fetch();

            if ($tenant) {
                // Connect to Tenant DB
                $dsn = "mysql:host={$tenant['db_host']};dbname={$tenant['db_name']};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                return new PDO($dsn, $tenant['db_user'], $tenant['db_password'], $options);
            }
        } catch (Exception $e) {
            // If master DB fails or tenant not found, fall through to default
            // Log error? error_log("Tenant resolution failed: " . $e->getMessage());
        }
    }

    // 3. Fallback to default (legacy) configuration
    $config = getConfig();
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
