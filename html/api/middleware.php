<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * JWT Authentication Middleware
 * Validates JWT token from Authorization header and attaches user data to request
 */
function jwtAuthMiddleware()
{
    return function (Request $request, $handler) {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader) {
            $response = new \Slim\Psr7\Response();
            return jsonResponse($response, ['error' => 'Authorization header required'], 401);
        }

        // Extract token from "Bearer <token>" format
        $token = null;
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }

        if (!$token) {
            $response = new \Slim\Psr7\Response();
            return jsonResponse($response, ['error' => 'Invalid authorization header format'], 401);
        }

        try {
            $secretKey = getenv('JWT_SECRET') ?: 'your-secret-key-change-in-production';
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

            // Attach user data to request attributes for use in route handlers
            $request = $request->withAttribute('user', $decoded->data);

            return $handler->handle($request);
        } catch (\Firebase\JWT\ExpiredException $e) {
            $response = new \Slim\Psr7\Response();
            return jsonResponse($response, ['error' => 'Token expired'], 401);
        } catch (\Exception $e) {
            $response = new \Slim\Psr7\Response();
            return jsonResponse($response, ['error' => 'Invalid token'], 401);
        }
    };
}
