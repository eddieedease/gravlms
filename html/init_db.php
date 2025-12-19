<?php
require __DIR__ . '/vendor/autoload.php';

$host = 'db';
$db = 'my_app_db';
$user = 'admin';
$pass = 'admin';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connected to database successfully.<br>";

    $sqlUsers = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(255) UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'editor', 'viewer', 'monitor') DEFAULT 'viewer',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sqlUsers);
    echo "Table 'users' created or already exists.<br>";

    // Create default admin user if not exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute(['admin']);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    if ($stmt->fetchColumn() == 0) {
        $password = password_hash('password', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@example.com', $password, 'admin']);
        echo "Default admin user created.<br>";
    }

    // Create lti_tools table BEFORE courses (to satisfy foreign key)
    $sqlLtiTools = "CREATE TABLE IF NOT EXISTS lti_tools (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        tool_url VARCHAR(255) NOT NULL,
        lti_version ENUM('1.1', '1.3') DEFAULT '1.3',
        client_id VARCHAR(255), -- LTI 1.3
        public_key TEXT, -- LTI 1.3
        initiate_login_url VARCHAR(255), -- LTI 1.3
        consumer_key VARCHAR(255), -- LTI 1.1
        shared_secret VARCHAR(255), -- LTI 1.1
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sqlLtiTools);
    echo "Table 'lti_tools' created or already exists.<br>";

    // Create courses table (now lti_tools exists)
    $sqlCourses = "CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        display_order INT DEFAULT 0,
        is_lti BOOLEAN DEFAULT FALSE,
        lti_tool_id INT NULL,
        custom_launch_url VARCHAR(500) NULL,
        image_url VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lti_tool_id) REFERENCES lti_tools(id) ON DELETE SET NULL
    )";
    $pdo->exec($sqlCourses);
    echo "Table 'courses' created or already exists.<br>";

    // Create course_pages table (Course Items)
    $sqlPages = "CREATE TABLE IF NOT EXISTS course_pages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        type ENUM('page', 'test') DEFAULT 'page',
        course_id INT NULL,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
    )";
    $pdo->exec($sqlPages);
    echo "Table 'course_pages' created or already exists.<br>";

    // Create user_courses table (Assignments)
    $sqlUserCourses = "CREATE TABLE IF NOT EXISTS user_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        UNIQUE(user_id, course_id)
    )";
    $pdo->exec($sqlUserCourses);
    echo "Table 'user_courses' created or already exists.<br>";

    // Create completed_lessons table
    $sqlCompletedLessons = "CREATE TABLE IF NOT EXISTS completed_lessons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        page_id INT NOT NULL,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (page_id) REFERENCES course_pages(id) ON DELETE CASCADE,
        UNIQUE(user_id, page_id)
    )";
    $pdo->exec($sqlCompletedLessons);
    echo "Table 'completed_lessons' created or already exists.<br>";

    // Create completed_courses table
    $sqlCompletedCourses = "CREATE TABLE IF NOT EXISTS completed_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    )";
    $pdo->exec($sqlCompletedCourses);
    echo "Table 'completed_courses' created or already exists.<br>";

    // Create groups table
    $sqlGroups = "CREATE TABLE IF NOT EXISTS `groups` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    echo "Debug SQL: " . htmlspecialchars($sqlGroups) . "<br>";
    $pdo->exec($sqlGroups);
    echo "Table 'groups' created or already exists.<br>";

    // Create group_users table
    $sqlGroupUsers = "CREATE TABLE IF NOT EXISTS group_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE(group_id, user_id)
    )";
    $pdo->exec($sqlGroupUsers);
    echo "Table 'group_users' created or already exists.<br>";

    // Create group_courses table
    $sqlGroupCourses = "CREATE TABLE IF NOT EXISTS group_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        course_id INT NOT NULL,
        validity_days INT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        UNIQUE(group_id, course_id)
    )";
    $pdo->exec($sqlGroupCourses);
    echo "Table 'group_courses' created or already exists.<br>";

    // Create tests table (Linked to Page ID now)
    $sqlTests = "CREATE TABLE IF NOT EXISTS tests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page_id INT NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (page_id) REFERENCES course_pages(id) ON DELETE CASCADE
    )";
    $pdo->exec($sqlTests);
    echo "Table 'tests' created or already exists.<br>";

    // Create test_questions table
    $sqlTestQuestions = "CREATE TABLE IF NOT EXISTS test_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        test_id INT NOT NULL,
        question_text TEXT NOT NULL,
        type ENUM('multiple_choice') DEFAULT 'multiple_choice',
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
    )";
    $pdo->exec($sqlTestQuestions);
    echo "Table 'test_questions' created or already exists.<br>";

    // Create test_question_options table
    $sqlTestQuestionOptions = "CREATE TABLE IF NOT EXISTS test_question_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_id INT NOT NULL,
        option_text TEXT NOT NULL,
        is_correct BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (question_id) REFERENCES test_questions(id) ON DELETE CASCADE
    )";
    $pdo->exec($sqlTestQuestionOptions);
    echo "Table 'test_question_options' created or already exists.<br>";
    // Create lti_platforms table (For when we are the Tool Provider - LTI 1.3)
    $sqlLtiPlatforms = "CREATE TABLE IF NOT EXISTS lti_platforms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        issuer VARCHAR(255) NOT NULL UNIQUE,
        client_id VARCHAR(255) NOT NULL,
        auth_login_url VARCHAR(255) NOT NULL,
        auth_token_url VARCHAR(255) NOT NULL,
        key_set_url VARCHAR(255) NOT NULL,
        deployment_id VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sqlLtiPlatforms);
    echo "Table 'lti_platforms' created or already exists.<br>";

    // lti_tools table already created above (before courses table)


    // Create lti_nonces table (Replay protection)
    $sqlLtiNonces = "CREATE TABLE IF NOT EXISTS lti_nonces (
        nonce VARCHAR(255) PRIMARY KEY,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sqlLtiNonces);
    echo "Table 'lti_nonces' created or already exists.<br>";

    // Create password_resets table
    $sqlPasswordResets = "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL
    )";
    $pdo->exec($sqlPasswordResets);
    echo "Table 'password_resets' created or already exists.<br>";

    // Create lti_keys table (Our Key Pairs for LTI 1.3)
    $sqlLtiKeys = "CREATE TABLE IF NOT EXISTS lti_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kid VARCHAR(255) NOT NULL UNIQUE,
        private_key TEXT NOT NULL,
        public_key TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sqlLtiKeys);
    echo "Table 'lti_keys' created or already exists.<br>";

    // Create group_monitors table
    $sqlGroupMonitors = "CREATE TABLE IF NOT EXISTS group_monitors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE(group_id, user_id)
    )";
    $pdo->exec($sqlGroupMonitors);
    echo "Table 'group_monitors' created or already exists.<br>";

    // Create test_results table
    $sqlTestResults = "CREATE TABLE IF NOT EXISTS test_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        test_id INT NOT NULL,
        score INT NOT NULL,
        max_score INT NOT NULL,
        passed BOOLEAN DEFAULT FALSE,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
    )";
    $pdo->exec($sqlTestResults);
    echo "Table 'test_results' created or already exists.<br>";

    // Create organization_settings table
    $sqlOrgSettings = "CREATE TABLE IF NOT EXISTS organization_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        org_name VARCHAR(255) DEFAULT 'My Organization',
        org_slogan VARCHAR(255) DEFAULT 'Learning for everyone',
        org_main_color VARCHAR(50) DEFAULT '#3b82f6',
        org_logo_url VARCHAR(255) NULL,
        org_header_image_url VARCHAR(255) NULL,
        org_email VARCHAR(255) DEFAULT '',
        news_message_enabled BOOLEAN DEFAULT FALSE,
        news_message_content TEXT NULL
    )";
    $pdo->exec($sqlOrgSettings);
    echo "Table 'organization_settings' created or already exists.<br>";

    // Insert default row if not exists
    $stmt = $pdo->query("SELECT count(*) FROM organization_settings");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO organization_settings (org_name) VALUES ('My Organization')");
        echo "Inserted default organization settings.<br>";
    }

    // --- Migrations for existing databases ---

    // Ensure 'type' column exists in course_pages
    $stmt = $pdo->query("SHOW COLUMNS FROM course_pages LIKE 'type'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE course_pages ADD COLUMN type ENUM('page', 'test') DEFAULT 'page' AFTER content");
        echo "Migration: Added 'type' column to course_pages.<br>";
    }

    // Ensure 'page_id' column exists in tests
    $stmt = $pdo->query("SHOW COLUMNS FROM tests LIKE 'page_id'");
    if ($stmt->rowCount() == 0) {
        // Add as nullable first to avoid errors with existing data
        $pdo->exec("ALTER TABLE tests ADD COLUMN page_id INT NULL AFTER id");
        echo "Migration: Added 'page_id' column to tests.<br>";
    }

    // Drop legacy columns from tests (course_id, title, display_order)
    $legacyCols = ['course_id', 'title', 'display_order'];
    foreach ($legacyCols as $col) {
        $stmt = $pdo->query("SHOW COLUMNS FROM tests LIKE '$col'");
        if ($stmt->rowCount() > 0) {
            // Drop FK for course_id if exists
            if ($col === 'course_id') {
                $sql = "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                        WHERE TABLE_NAME = 'tests' AND COLUMN_NAME = 'course_id' AND TABLE_SCHEMA = '$db'";
                $stmtFK = $pdo->query($sql);
                $fk = $stmtFK->fetch(PDO::FETCH_ASSOC);
                if ($fk) {
                    $fkName = $fk['CONSTRAINT_NAME'];
                    $pdo->exec("ALTER TABLE tests DROP FOREIGN KEY $fkName");
                }
            }
            $pdo->exec("ALTER TABLE tests DROP COLUMN $col");
            echo "Migration: Dropped column '$col' from tests.<br>";
        }
    }

    // Ensure 'is_lti' column exists in courses
    $stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'is_lti'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE courses ADD COLUMN is_lti BOOLEAN DEFAULT FALSE AFTER display_order");
        echo "Migration: Added 'is_lti' column to courses.<br>";
    }

    // Ensure 'lti_tool_id' column exists in courses
    $stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'lti_tool_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE courses ADD COLUMN lti_tool_id INT NULL AFTER is_lti");
        // Add foreign key constraint
        $pdo->exec("ALTER TABLE courses ADD CONSTRAINT fk_courses_lti_tool FOREIGN KEY (lti_tool_id) REFERENCES lti_tools(id) ON DELETE SET NULL");
        echo "Migration: Added 'lti_tool_id' column to courses.<br>";
    }

    // Ensure 'custom_launch_url' column exists in courses
    $stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'custom_launch_url'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE courses ADD COLUMN custom_launch_url VARCHAR(500) NULL AFTER lti_tool_id");
        echo "Migration: Added 'custom_launch_url' column to courses.<br>";
    }

    // Ensure 'image_url' column exists in courses
    $stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'image_url'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE courses ADD COLUMN image_url VARCHAR(255) NULL DEFAULT NULL AFTER custom_launch_url");
        echo "Migration: Added 'image_url' column to courses.<br>";
    }

    // Ensure 'monitor' role exists in users enum
    // This is tricky with MySQL/MariaDB enums.
    // We can try to modify the column blindly or check if it contains 'monitor'.
    // A safe way is to just modify it to include the superset.
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'editor', 'viewer', 'monitor') DEFAULT 'viewer'");
    echo "Migration: Updated users role enum to include 'monitor'.<br>";

    // Ensure 'validity_days' column exists in group_courses
    $stmt = $pdo->query("SHOW COLUMNS FROM group_courses LIKE 'validity_days'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE group_courses ADD COLUMN validity_days INT NULL AFTER course_id");
        echo "Migration: Added 'validity_days' column to group_courses.<br>";
    }

    // Drop UNIQUE constraint on completed_courses (user_id, course_id) to allow history
    // We need to find the index name first. It is usually 'user_id' or a composite name.
    $sqlIndex = "SHOW INDEX FROM completed_courses WHERE Key_name != 'PRIMARY' AND Non_unique = 0";
    $stmtIndex = $pdo->query($sqlIndex);
    $indexes = $stmtIndex->fetchAll(PDO::FETCH_ASSOC);
    foreach ($indexes as $index) {
        if ($index['Key_name'] === 'user_id') {
            // 1. Create a non-unique index to support the Foreign Key on user_id
            // Check if it already exists to avoid dupes (optional but good)
            $stmtCheck = $pdo->query("SHOW INDEX FROM completed_courses WHERE Key_name = 'idx_user_id_fk'");
            if ($stmtCheck->rowCount() == 0) {
                $pdo->exec("CREATE INDEX idx_user_id_fk ON completed_courses(user_id)");
                echo "Migration: Created index 'idx_user_id_fk' to support FK.<br>";
            }

            // 2. Drop the UNIQUE index 'user_id'
            $pdo->exec("ALTER TABLE completed_courses DROP INDEX user_id");
            echo "Migration: Dropped UNIQUE index 'user_id' from completed_courses.<br>";
            break;
        }

        // Handle case where index might be auto-named differently but covers the unique constraint we want to remove
        if ($index['Key_name'] !== 'PRIMARY' && $index['Column_name'] === 'user_id') {
            try {
                // Same logic: Ensure backup index exists first if we are about to drop the one FK relies on
                $stmtCheck = $pdo->query("SHOW INDEX FROM completed_courses WHERE Key_name = 'idx_user_id_fk'");
                if ($stmtCheck->rowCount() == 0) {
                    $pdo->exec("CREATE INDEX idx_user_id_fk ON completed_courses(user_id)");
                    echo "Migration: Created index 'idx_user_id_fk' to support FK.<br>";
                }

                $pdo->exec("ALTER TABLE completed_courses DROP INDEX " . $index['Key_name']);
                echo "Migration: Dropped UNIQUE index '" . $index['Key_name'] . "' from completed_courses.<br>";
            } catch (Exception $e) {
                // Ignore
            }
        }
    }

} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int) $e->getCode());
}
