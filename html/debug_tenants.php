<?php
require_once __DIR__ . '/api/db.php';

try {
    $pdo = getMasterDbConnection();
    $stmt = $pdo->query("SELECT * FROM tenants");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h1>Registered Tenants (Master DB)</h1>";
    if (empty($tenants)) {
        echo "<p>No tenants found in the 'tenants' table.</p>";
    } else {
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr style='background: #f3f4f6;'>
                <th>ID</th>
                <th>Name</th>
                <th>Slug (Use in URL)</th>
                <th>DB Host</th>
                <th>DB Name</th>
                <th>DB User</th>
                <th>Status</th>
              </tr>";

        foreach ($tenants as $t) {
            // Test connection to this tenant
            $status = "<span style='color:green'>OK</span>";
            try {
                $dsn = "mysql:host={$t['db_host']};dbname={$t['db_name']};charset=utf8mb4";
                $tPdo = new PDO($dsn, $t['db_user'], $t['db_password']);
            } catch (Exception $e) {
                $status = "<span style='color:red'>Connection Failed: " . $e->getMessage() . "</span>";
            }

            echo "<tr>";
            echo "<td>{$t['id']}</td>";
            echo "<td>" . htmlspecialchars($t['name']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($t['slug']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($t['db_host']) . "</td>";
            echo "<td>" . htmlspecialchars($t['db_name']) . "</td>";
            echo "<td>" . htmlspecialchars($t['db_user']) . "</td>";
            echo "<td>{$status}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "<h3>Diagnostic Info</h3>";
    echo "<p><strong>Current Master DB Config:</strong> " . getConfig()['master_database']['name'] . "</p>";
    echo "<p>To add a tenant, you must insert a row into this table via SQL.</p>";

} catch (Exception $e) {
    echo "<h2>Error connecting to Master Database</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
