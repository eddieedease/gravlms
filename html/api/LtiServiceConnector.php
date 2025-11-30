<?php


namespace GravLMS\Lti;

use Packback\Lti1p3\Interfaces\ILtiServiceConnector;
use Packback\Lti1p3\Interfaces\ILtiRegistration;
use Packback\Lti1p3\Interfaces\IServiceRequest;
use Packback\Lti1p3\Interfaces\ICache;
use Packback\Lti1p3\LtiServiceConnector as BaseLtiServiceConnector;
use Psr\Http\Message\ResponseInterface;

/**
 * Service connector for making HTTP requests to LTI services.
 * Wraps the library's default implementation.
 */
class LtiServiceConnector implements ILtiServiceConnector
{
    private BaseLtiServiceConnector $connector;

    public function __construct(ICache $cache)
    {
        $this->connector = new BaseLtiServiceConnector($cache);
    }

    public function getAccessToken(ILtiRegistration $registration, array $scopes): string
    {
        return $this->connector->getAccessToken($registration, $scopes);
    }

    public function makeRequest(IServiceRequest $request): ResponseInterface
    {
        return $this->connector->makeRequest($request);
    }

    public function getResponseBody(ResponseInterface $response): ?array
    {
        return $this->connector->getResponseBody($response);
    }

    public function getResponseHeaders(ResponseInterface $response): ?array
    {
        return $this->connector->getResponseHeaders($response);
    }

    public function makeServiceRequest(
        ILtiRegistration $registration,
        array $scopes,
        IServiceRequest $request,
        bool $shouldRetry = true
    ): array {
        return $this->connector->makeServiceRequest($registration, $scopes, $request, $shouldRetry);
    }

    public function getAll(
        ILtiRegistration $registration,
        array $scopes,
        IServiceRequest $request,
        ?string $key
    ): array {
        return $this->connector->getAll($registration, $scopes, $request, $key);
    }

    public function setDebuggingMode(bool $enable): ILtiServiceConnector
    {
        $this->connector->setDebuggingMode($enable);
        return $this;
    }
}
