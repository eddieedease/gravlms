<?php

namespace GravLMS\Lti;

use Packback\Lti1p3\Interfaces\ICache;

/**
 * Simple session-based cache implementation for LTI library.
 */
class LtiCache implements ICache
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function getLaunchData(string $key): ?array
    {
        return $_SESSION['lti_launch_data'][$key] ?? null;
    }

    public function cacheLaunchData(string $key, array $jwtBody): void
    {
        $_SESSION['lti_launch_data'][$key] = $jwtBody;
    }

    public function cacheNonce(string $nonce, string $state): void
    {
        $_SESSION['lti_nonces'][$nonce] = $state;
    }

    public function checkNonceIsValid(string $nonce, string $state): bool
    {
        if (!isset($_SESSION['lti_nonces'][$nonce])) {
            return false;
        }

        $valid = $_SESSION['lti_nonces'][$nonce] === $state;

        // Remove nonce after checking (one-time use)
        unset($_SESSION['lti_nonces'][$nonce]);

        return $valid;
    }

    public function cacheAccessToken(string $key, string $accessToken): void
    {
        $_SESSION['lti_access_tokens'][$key] = $accessToken;
    }

    public function getAccessToken(string $key): ?string
    {
        return $_SESSION['lti_access_tokens'][$key] ?? null;
    }

    public function clearAccessToken(string $key): void
    {
        unset($_SESSION['lti_access_tokens'][$key]);
    }
}
