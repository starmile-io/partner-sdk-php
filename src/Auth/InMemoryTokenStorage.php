<?php

namespace Starmile\PartnerSdk\Auth;

/**
 * The default token storage: holds the token in process memory only. Adequate for
 * a long-running worker or a single request; swap in a shared implementation to
 * reuse one token across processes.
 */
final class InMemoryTokenStorage implements TokenStorageInterface
{
    /** @var AccessToken|null */
    private $token;

    public function get()
    {
        return $this->token;
    }

    public function set(AccessToken $token)
    {
        $this->token = $token;
    }

    public function clear()
    {
        $this->token = null;
    }
}
