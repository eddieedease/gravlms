<?php

namespace GravLMS\Lti;

use Packback\Lti1p3\Interfaces\IDatabase;
use Packback\Lti1p3\Interfaces\ILtiRegistration;
use Packback\Lti1p3\Interfaces\ILtiDeployment;
use Packback\Lti1p3\LtiRegistration;
use Packback\Lti1p3\LtiDeployment;
use PDO;

/**
 * Database adapter for the Packback LTI 1.3 library.
 * Maps library methods to our MySQL tables.
 */
class LtiDatabase implements IDatabase
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Find an LTI registration (platform) by issuer and optional client ID.
     *
     * @param string $iss The issuer URL
     * @param string|null $clientId Optional client ID
     * @return ILtiRegistration|null
     */
    public function findRegistrationByIssuer(string $iss, ?string $clientId = null): ?ILtiRegistration
    {
        $sql = "SELECT * FROM lti_platforms WHERE issuer = ?";
        $params = [$iss];

        if ($clientId !== null) {
            $sql .= " AND client_id = ?";
            $params[] = $clientId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $platform = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$platform) {
            return null;
        }

        // Get the key set for this platform
        $keyStmt = $this->pdo->prepare("SELECT * FROM lti_keys LIMIT 1");
        $keyStmt->execute();
        $key = $keyStmt->fetch(PDO::FETCH_ASSOC);

        // Create LtiRegistration object
        return LtiRegistration::new()
            ->setAuthLoginUrl($platform['auth_login_url'])
            ->setAuthTokenUrl($platform['auth_token_url'])
            ->setClientId($platform['client_id'])
            ->setKeySetUrl($platform['key_set_url'])
            ->setKid($key['kid'] ?? 'default-kid')
            ->setIssuer($platform['issuer'])
            ->setToolPrivateKey($key['private_key'] ?? '');
    }

    /**
     * Find an LTI deployment by issuer and deployment ID.
     *
     * @param string $iss The issuer URL
     * @param string $deploymentId The deployment ID
     * @param string|null $clientId Optional client ID
     * @return ILtiDeployment|null
     */
    public function findDeployment(string $iss, string $deploymentId, ?string $clientId = null): ?ILtiDeployment
    {
        $sql = "SELECT * FROM lti_platforms WHERE issuer = ?";
        $params = [$iss];

        if ($clientId !== null) {
            $sql .= " AND client_id = ?";
            $params[] = $clientId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $platform = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$platform) {
            return null;
        }

        // Check if deployment_id matches (if stored)
        if ($platform['deployment_id'] && $platform['deployment_id'] !== $deploymentId) {
            return null;
        }

        // Create LtiDeployment object
        return LtiDeployment::new($deploymentId);
    }
}
