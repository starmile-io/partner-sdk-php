<?php

namespace Starmile\PartnerSdk\Auth;

/**
 * Persists the current access token between requests. The default
 * {@see InMemoryTokenStorage} keeps it for the life of the process; implement this
 * to share one token across processes (APCu, Redis, a PSR-16 cache, a file) and
 * avoid re-hitting the token endpoint on every cold start.
 */
interface TokenStorageInterface
{
    /**
     * @return AccessToken|null
     */
    public function get();

    /**
     * @return void
     */
    public function set(AccessToken $token);

    /**
     * @return void
     */
    public function clear();
}
