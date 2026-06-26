<?php

namespace Starmile\PartnerSdk\Auth;

/**
 * A short-lived bearer token plus the absolute Unix time it expires. Treated as
 * expired a few seconds early to avoid using a token that dies mid-flight.
 */
final class AccessToken
{
    /** Seconds of safety margin subtracted from the real expiry. */
    const EXPIRY_SKEW = 30;

    /** @var string */
    private $token;

    /** @var int Absolute Unix timestamp. */
    private $expiresAt;

    public function __construct($token, $expiresAt)
    {
        $this->token = (string) $token;
        $this->expiresAt = (int) $expiresAt;
    }

    /**
     * Build from the token endpoint's response (a relative `expires_in` in seconds).
     *
     * @param array<string, mixed> $payload
     * @return AccessToken
     */
    public static function fromTokenResponse(array $payload, $now = null)
    {
        $now = $now === null ? time() : (int) $now;
        $expiresIn = isset($payload['expires_in']) ? (int) $payload['expires_in'] : 3600;
        $token = isset($payload['access_token']) ? (string) $payload['access_token'] : '';

        return new self($token, $now + $expiresIn);
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return int
     */
    public function getExpiresAt()
    {
        return $this->expiresAt;
    }

    /**
     * @return bool
     */
    public function isExpired($now = null)
    {
        $now = $now === null ? time() : (int) $now;

        return $now >= ($this->expiresAt - self::EXPIRY_SKEW);
    }

    /**
     * @return bool
     */
    public function isValid($now = null)
    {
        return $this->token !== '' && !$this->isExpired($now);
    }
}
