<?php
/**
 * Configuration Template for LMS Backend
 * 
 * Copy this file to config.php and update with your actual values.
 * The config.php file is gitignored to prevent committing sensitive data.
 */

return [
    // Database Configuration
    // Database Configuration
    'database' => [
        'host' => 'db',              // Database host (use 'db' for Docker, or IP/domain for production)
        'name' => 'my_app_db',       // Database name
        'user' => 'root',           // Database username
        'password' => 'root',       // Database password
        'charset' => 'utf8mb4'       // Character set
    ],
    // Master Database (for multi-tenancy)
    'master_database' => [
        'host' => 'db',
        'name' => 'master',      // Default master DB name
        'user' => 'root',
        'password' => 'root',
        'charset' => 'utf8mb4'
    ],

    // JWT Configuration
    'jwt' => [
        'secret' => 'your-secret-key-here-change-this-in-production',  // IMPORTANT: Change this!
        'algorithm' => 'HS256',
        'expiration' => 3600 * 24 * 30   // Token expiration in seconds (30 days / 1 month)
    ],

    // Application Settings
    'app' => [
        'environment' => 'production',  // 'development' or 'production'
        'debug' => false,               // Set to false in production
        'timezone' => 'UTC'
    ],

    // Upload Configuration
    'upload' => [
        'path' => __DIR__ . '/../uploads/',  // Path to uploads folder (one level up from backend)
        'max_size' => 5 * 1024 * 1024,       // Max file size in bytes (5MB)
        'allowed_types' => ['image/jpeg', 'image/jpg', 'image/png']
    ],

    // CORS Configuration
    'cors' => [
        'allowed_origins' => ['http://localhost:4200', 'https://yourdomain.com'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization']
    ],



    // Email Configuration
    'email' => [
        'enabled' => true,                    // Enable/disable email functionality
        'from_address' => 'noreply@yourdomain.com',  // From email address
        'from_name' => 'LMS System',          // From name
        'smtp_host' => 'smtp.gmail.com',      // SMTP server (e.g., smtp.gmail.com, smtp.office365.com)
        'smtp_port' => 587,                   // SMTP port (587 for TLS, 465 for SSL)
        'smtp_secure' => 'tls',               // Encryption: 'tls' or 'ssl'
        'smtp_auth' => true,                  // Enable SMTP authentication
        'smtp_username' => 'your-email@gmail.com',  // SMTP username
        'smtp_password' => 'your-app-password'      // SMTP password or app password
    ]
];
