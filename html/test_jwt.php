<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

try {
    echo "Testing JWT encoding...\n";

    $secretKey = 'test-secret-key';
    $issuedAt = time();
    $expirationTime = $issuedAt + 3600;
    $payload = [
        'iat' => $issuedAt,
        'exp' => $expirationTime,
        'iss' => 'gravlms',
        'data' => [
            'id' => 1,
            'username' => 'admin',
            'role' => 'admin'
        ]
    ];

    echo "Payload created\n";
    echo "Calling JWT::encode()...\n";

    $jwt = JWT::encode($payload, $secretKey, 'HS256');

    echo "JWT created successfully!\n";
    echo "Token: " . substr($jwt, 0, 50) . "...\n";

    echo "\nTesting JWT decoding...\n";
    $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));
    echo "JWT decoded successfully!\n";
    echo "Username: " . $decoded->data->username . "\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
