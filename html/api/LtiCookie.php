<?php

namespace GravLMS\Lti;

use Packback\Lti1p3\Interfaces\ICookie;

/**
 * Simple cookie implementation for LTI library.
 */
class LtiCookie implements ICookie
{
    public function getCookie(string $name): ?string
    {
        return $_COOKIE[$name] ?? null;
    }

    public function setCookie(string $name, string $value, int $exp = 3600, array $options = []): void
    {
        $options = array_merge([
            'expires' => time() + $exp,
            'path' => '/',
            'secure' => false, // Set to true in production with HTTPS
            'httponly' => true,
            'samesite' => 'None', // Required for cross-site LTI launches
        ], $options);

        setcookie($name, $value, $options);
    }
}
