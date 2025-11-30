<?php
/**
 * Configuration Template for LMS Backend
 * 
 * Copy this file to config.php and update with your actual values.
 * The config.php file is gitignored to prevent committing sensitive data.
 */

return [
    // Database Configuration
    'database' => [
        'host' => 'db',              // Database host (use 'db' for Docker, or IP/domain for production)
        'name' => 'my_app_db',       // Database name
        'user' => 'admin',           // Database username
        'password' => 'admin',       // Database password
        'charset' => 'utf8mb4'       // Character set
    ],

    // JWT Configuration
    'jwt' => [
        'secret' => 'your-secret-key-here-change-this-in-production',  // IMPORTANT: Change this!
        'algorithm' => 'HS256',
        'expiration' => 3600 * 24    // Token expiration in seconds (24 hours)
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

    // LTI Configuration (if needed)
    'lti' => [
        'consumer_key' => 'your-lti-consumer-key',
        'consumer_secret' => 'your-lti-consumer-secret'
    ]
];
