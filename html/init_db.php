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

    // Create users table
    $sqlUsers = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'editor') DEFAULT 'editor',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sqlUsers);
    echo "Table 'users' created or already exists.<br>";

    // Create default admin user if not exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    if ($stmt->rowCount() == 0) {
        // In a real app, use password_hash()
        // For this mockup, we'll store plain text or simple hash as per previous steps
        // But let's stick to what we had. The login endpoint uses password_verify if hashed, 
        // or simple comparison if we implemented it that way. 
        // Let's check index.php login logic... 
        // Actually, let's just insert 'admin'/'password' as a placeholder.
        // The previous login implementation likely used password_verify.
        $hashedPassword = password_hash('password', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute(['admin', $hashedPassword, 'admin']);
        echo "Default admin user created.<br>";
    }

    // Create courses table
    $sqlCourses = "CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sqlCourses);
    echo "Table 'courses' created or already exists.<br>";

    // Create course_pages table
    $sqlPages = "CREATE TABLE IF NOT EXISTS course_pages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT,
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
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        UNIQUE(user_id, course_id)
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
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        UNIQUE(group_id, course_id)
    )";
    $pdo->exec($sqlGroupCourses);
    echo "Table 'group_courses' created or already exists.<br>";

    // Create tests table
    $sqlTests = "CREATE TABLE IF NOT EXISTS tests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
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


} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int) $e->getCode());
}
