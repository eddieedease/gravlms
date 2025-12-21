<?php
require __DIR__ . '/vendor/autoload.php';
$host = 'db';
$db = 'my_app_db';
$user = 'admin';
$pass = 'admin';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$pdo = new PDO($dsn, $user, $pass);

echo "Checking row 10 of course_pages:\n";
$stmt = $pdo->query("SELECT * FROM course_pages LIMIT 15");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "ID: " . $row['id'] . ", Type: " . ($row['type'] ?? 'NULL') . "\n";
}
