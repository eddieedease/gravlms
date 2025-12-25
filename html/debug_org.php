<?php
require_once __DIR__ . '/api/db.php';

// Allow CLI or Browser
$tenantSlug = $_GET['tenant'] ?? $argv[1] ?? 'main';

echo "<h1>Debug Organization Settings for Tenant: $tenantSlug</h1>";

try {
    // Manually force the tenant context
    $_SERVER['HTTP_X_TENANT_ID'] = $tenantSlug;

    $pdo = getDbConnection($tenantSlug);

    // Check connection info (mask password)
    echo "Connected to DB...<br>";

    $stmt = $pdo->query("SELECT * FROM organization_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    print_r($settings);
    echo "</pre>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
