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
        // Allow OPTIONS requests to bypass auth (for CORS preflight)
        if ($request->getMethod() === 'OPTIONS') {
            return $handler->handle($request);
        }

        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader) {
            error_log("Auth Debug: Missing Authorization header. URI: " . $request->getUri());
            $response = new \Slim\Psr7\Response();
            return jsonResponse($response, ['error' => 'Authorization header required'], 401);
        }

        // Extract token from "Bearer <token>" format
        $token = null;
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }

        if (!$token) {
            error_log("Auth Debug: Invalid header format. Header: " . $authHeader);
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
            error_log("Auth Debug: Token expired. " . $e->getMessage());
            $response = new \Slim\Psr7\Response();
            return jsonResponse($response, ['error' => 'Token expired'], 401);
        } catch (\Exception $e) {
            error_log("Auth Debug: Invalid token. " . $e->getMessage());
            $response = new \Slim\Psr7\Response();
            return jsonResponse($response, ['error' => 'Invalid token'], 401);
        }
    };
}
