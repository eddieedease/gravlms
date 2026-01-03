<?php
/**
 * Configuration Template for LMS Backend
 * 
 * Copy this file to config.php and update with your actual values.
 * The config.php file is gitignored to prevent committing sensitive data.
 */

return [

    // Master Database (for multi-tenancy)
    'master_database' => [
        'host' => 'localhost',
        'name' => 'deb33813n500_master',      // Default master DB name
        'user' => 'deb33813n500_master',
        'password' => '2EEP5cehDWQUP7NLmN5f',
        'charset' => 'utf8'  // Changed from utf8mb4 for production MySQL compatibility
    ],

    // JWT Configuration
    'jwt' => [
        'secret' => 'change-in-production-againn ',  // IMPORTANT: Change this!
        'algorithm' => 'HS256',
        'expiration' => 3600 * 24 * 30   // Token expiration in seconds (30 days / 1 month)
    ],

    // Application Settings
    'app' => [
        'environment' => 'production',  // 'development' or 'production'
        'debug' => false,               // Set to false in production
        'timezone' => 'UTC',
        'frontend_url' => 'http://localhost:4200', // Base URL for the frontend
        'backend_url' => 'http://localhost:8080',   // Base URL for the backend API
        // Base Path: Uncomment the one matching your environment
        'base_path' => '',                          // Development (Docker/Localhost)
        // 'base_path' => '/backend',               // Production (Shared Hosting / Subdirectory)
    ],

    // Upload Configuration
    'upload' => [
        // Path to uploads folder relative to this config file
        // Development: html/config.php -> ../uploads/ resolves to project root uploads/
        //              But uploads.php will override to use public/uploads/
        // Production: dist/gravlms/browser/backend/config.php -> ../uploads/
        //             resolves to dist/gravlms/browser/uploads/ ✓
        'path' => __DIR__ . '/../uploads/',
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
