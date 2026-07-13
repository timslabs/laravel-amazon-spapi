<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Support;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use SpApi\AuthAndAuth\LWAAccessTokenCache;
use Tims\AmazonSpApi\Contracts\TokenCache;

/**
 * Laravel-backed LWA access token cache for amzn-spapi/sdk.
 *
 * Extends the official LWAAccessTokenCache so it can be passed into
 * SpApi\Configuration (which type-hints that concrete class).
 */
class LaravelTokenCache extends LWAAccessTokenCache implements TokenCache
{
    private const TAG = 'amazon-spapi-tokens';

    private const EXPIRATION_BUFFER_SECONDS = 60;

    public function __construct(
        private readonly int|string $credentialsId = 'default',
    ) {}

    public function get($key): ?string
    {
        $value = $this->store()->get($this->prefixedKey($key));

        return is_string($value) ? $value : null;
    }

    public function set($key, $value, $ttl = 0): void
    {
        $ttlSeconds = max(0, (int) $ttl - self::EXPIRATION_BUFFER_SECONDS);

        if ($ttlSeconds > 0) {
            $this->store()->put($this->prefixedKey($key), $value, $ttlSeconds);
        } else {
            $this->store()->forever($this->prefixedKey($key), $value);
        }
    }

    public function remove($key): void
    {
        $this->store()->forget($this->prefixedKey($key));
    }

    public function clearForCreds(): void
    {
        if ($this->isTaggable()) {
            // Only flush this credentials tag. Including the shared TAG would
            // invalidate tokens for every credentials set.
            Cache::tags([$this->credentialsTag()])->flush();
        }
    }

    private function prefixedKey(string $key): string
    {
        return 'amazon-spapi:'.$this->credentialsId.':'.hash('sha256', $key);
    }

    private function credentialsTag(): string
    {
        return 'creds'.$this->credentialsId;
    }

    private function store()
    {
        if ($this->isTaggable()) {
            return Cache::tags([self::TAG, $this->credentialsTag()]);
        }

        return Cache::store();
    }

    private function isTaggable(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }
}
