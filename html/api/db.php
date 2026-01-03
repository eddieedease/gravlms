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
    // 1. Try to get tenant slug from argument or headers
    if (!$tenantSlug) {
        // Try $_SERVER (standard for many setups)
        $tenantSlug = $_SERVER['HTTP_X_TENANT_ID'] ?? null;

        // Try getallheaders (for Apache/FPM where $_SERVER might miss custom headers)
        if (!$tenantSlug && function_exists('getallheaders')) {
            $headers = getallheaders();
            // Headers can be case-insensitive
            $tenantSlug = $headers['X-Tenant-ID'] ?? $headers['x-tenant-id'] ?? null;
        }
    }

    // 2. If valid slug is present, MUST resolve or fail
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
            } else {
                // Tenant ID was provided but not found in Master DB
                // Do NOT fallback to default DB, as this is a security risk and confusing
                throw new Exception("Tenant not found: " . htmlspecialchars($tenantSlug));
            }
        } catch (Exception $e) {
            // Log the error
            error_log("Tenant Resolution Failed: " . $e->getMessage());
            // Re-throw so the API returns an error instead of wrong data
            throw new Exception("Tenant connection failed: " . $e->getMessage());
        }
    }

    // 3. Fallback to default configuration (Only if NO tenant ID was explicitly sent)
    $config = getConfig();
    $dbConfig = $config['database'] ?? $config['master_database']; // Fallback to master if database not set

    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']}";
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
