<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Contracts;

/**
 * Access-token cache used with the official SP-API SDK's LWA client.
 */
interface TokenCache
{
    public function get($key): ?string;

    public function set($key, $value, $ttl = 0): void;

    public function remove($key): void;

    public function clearForCreds(): void;
}
