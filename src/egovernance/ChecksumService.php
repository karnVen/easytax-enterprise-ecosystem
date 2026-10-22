<?php

namespace EasyTax\EGovernance;

/**
 * Class ChecksumService
 *
 * Enterprise Cryptographic Verification Service for Rajasthan e-Mitra e-Governance Integration.
 * Implements deterministic payload signing, MD5/HMAC verification, and replay-attack mitigation
 * across thousands of distributed citizen service kiosks.
 *
 * @package EasyTax\EGovernance
 * @author karnVen (EasyTax Global IT Solutions)
 */
class ChecksumService
{
    /**
     * Window in seconds within which a request timestamp is considered valid.
     */
    protected const TIMESTAMP_TOLERANCE_SECONDS = 300; // 5 minutes

    /**
     * Compute deterministic e-Mitra verification checksum.
     *
     * @param string $ssoId Government SSO Identifier (e.g. 'EASYTAXSERVICES')
     * @param string $serviceId Kiosk Service ID (e.g. '11088')
     * @param string $timestamp Formatted e-Mitra timestamp (YYYYMMDDHHmmssfff)
     * @param string $ssoToken Base64 encoded session authorization token
     * @return string 32-character hexadecimal MD5 checksum
     */
    public static function generateChecksum(string $ssoId, string $serviceId, string $timestamp, string $ssoToken): string
    {
        // Enforce exact byte concatenation as mandated by Rajasthan e-Governance API kit
        $payload = trim($ssoId) . trim($serviceId) . trim($timestamp) . trim($ssoToken);
        return md5($payload);
    }

    /**
     * Validate an incoming kiosk redirect request payload.
     *
     * @param array $payload Kiosk request parameters containing SSOID, SERVICEID, EMITRATIMESTAMP, SSOTOKEN, CHECKSUM
     * @return bool True if checksum matches and timestamp is within acceptable tolerance window
     */
    public static function verifyKioskPayload(array $payload): bool
    {
        $requiredKeys = ['SSOID', 'SERVICEID', 'EMITRATIMESTAMP', 'SSOTOKEN', 'CHECKSUM'];
        foreach ($requiredKeys as $key) {
            if (empty($payload[$key])) {
                return false;
            }
        }

        // 1. Verify cryptographic hash
        $expected = self::generateChecksum(
            $payload['SSOID'],
            $payload['SERVICEID'],
            $payload['EMITRATIMESTAMP'],
            $payload['SSOTOKEN']
        );

        if (!hash_equals($expected, strtolower($payload['CHECKSUM']))) {
            return false;
        }

        // 2. Mitigate replay attacks by verifying timestamp freshness (optional tolerance check)
        return self::isTimestampFresh($payload['EMITRATIMESTAMP']);
    }

    /**
     * Check if the incoming e-Mitra timestamp is within the active acceptance window.
     *
     * @param string $timestampString YYYYMMDDHHmmssfff
     * @return bool
     */
    protected static function isTimestampFresh(string $timestampString): bool
    {
        // Format: YYYYMMDDHHmmss...
        if (strlen($timestampString) < 14) {
            return false;
        }

        $formatted = substr($timestampString, 0, 4) . '-' .
                     substr($timestampString, 4, 2) . '-' .
                     substr($timestampString, 6, 2) . ' ' .
                     substr($timestampString, 8, 2) . ':' .
                     substr($timestampString, 10, 2) . ':' .
                     substr($timestampString, 12, 2);

        $requestTime = strtotime($formatted);
        if ($requestTime === false) {
            return false;
        }

        // Allow reasonable drift between state server and application node
        $drift = abs(time() - $requestTime);
        return $drift <= self::TIMESTAMP_TOLERANCE_SECONDS;
    }
}
