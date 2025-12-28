<?php
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/schema.php';

function migrateAllTenants($pdoMaster = null)
{
    echo "Starting Multi-Tenant Migration...<br>";

    try {
        // 1. Connect to Master DB
        if (!$pdoMaster) {
            $pdoMaster = getMasterDbConnection();
            echo "Connected to Master Database (from config).<br>";
        } else {
            echo "Connected to Master Database (from input).<br>";
        }

        // 2. Get all tenants
        $stmt = $pdoMaster->query("SELECT * FROM tenants");
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($tenants)) {
            echo "No tenants found in master database.<br>";
            return;
        }

        echo "Found " . count($tenants) . " tenants.<br><hr>";

        foreach ($tenants as $tenant) {
            echo "<strong>Processing Tenant: " . htmlspecialchars($tenant['name']) . " (" . htmlspecialchars($tenant['slug']) . ")</strong><br>";

            try {
                // 3. Connect to Tenant DB
                $dsn = "mysql:host={$tenant['db_host']};dbname={$tenant['db_name']};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ];
                $pdoTenant = new PDO($dsn, $tenant['db_user'], $tenant['db_password'], $options);

                // 4. Run Migration
                initializeTenantSchema($pdoTenant);
                echo "<span style='color:green'>Success: Tenant schema updated.</span><br>";

            } catch (Exception $e) {
                echo "<span style='color:red'>Error processing tenant: " . $e->getMessage() . "</span><br>";
            }
            echo "<hr>";
        }

        echo "<strong>Multi-Tenant Migration Completed.</strong>";

    } catch (Exception $e) {
        echo "<span style='color:red'>Critical Error: " . $e->getMessage() . "</span>";
    }
}
