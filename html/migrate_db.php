<?php
require_once __DIR__ . '/api/db.php';

try {
    $pdo = getDbConnection();
    echo "Connected to database successfully.<br>";

    // 1. Add email to users
    // Check if column exists first to avoid errors on re-run
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
    if ($stmt->rowCount() == 0) {
        $sql = "ALTER TABLE users ADD COLUMN email VARCHAR(255) UNIQUE AFTER username";
        $pdo->exec($sql);
        echo "Added 'email' column to 'users'.<br>";

        // Update existing users with a dummy email to satisfy UNIQUE constraint if needed, 
        // but since it's nullable by default or we just added it, we might want to fill it.
        // Actually, let's make it NOT NULL but give a default for existing? 
        // For now, let's leave it nullable or update existing ones.
        // Let's update existing users to have email = username@example.com
        $pdo->exec("UPDATE users SET email = CONCAT(username, '@example.com') WHERE email IS NULL");
        echo "Updated existing users with default emails.<br>";
    } else {
        echo "'email' column already exists in 'users'.<br>";
    }

    // 2. Add updated_at to tables
    $tables = ['users', 'courses', 'groups', 'course_pages'];

    foreach ($tables as $table) {
        // Escape table name for groups
        $tableName = ($table === 'groups') ? "`groups`" : $table;

        $stmt = $pdo->query("SHOW COLUMNS FROM $tableName LIKE 'updated_at'");
        if ($stmt->rowCount() == 0) {
            $sql = "ALTER TABLE $tableName ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
            $pdo->exec($sql);
            echo "Added 'updated_at' column to '$table'.<br>";
        } else {
            echo "'updated_at' column already exists in '$table'.<br>";
        }
    }

    echo "Migration completed successfully.";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage();
}
