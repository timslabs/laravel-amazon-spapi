<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Support;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

/**
 * Laravel cache for Restricted Data Tokens (RDTs).
 *
 * Uses the same credentials tag as {@see LaravelTokenCache} so
 * Credentials::updating() clears both LWA access tokens and RDTs.
 */
class LaravelRdtCache
{
    private const TAG = 'amazon-spapi-rdt';

    private const EXPIRATION_BUFFER_SECONDS = 60;

    public function __construct(
        private readonly int|string $credentialsId = 'default',
    ) {}

    public function get(string $key): ?string
    {
        $value = $this->store()->get($this->prefixedKey($key));

        return is_string($value) ? $value : null;
    }

    public function put(string $key, string $token, int $ttlSeconds): void
    {
        $ttl = max(0, $ttlSeconds - self::EXPIRATION_BUFFER_SECONDS);

        if ($ttl > 0) {
            $this->store()->put($this->prefixedKey($key), $token, $ttl);
        } else {
            $this->store()->forever($this->prefixedKey($key), $token);
        }
    }

    public function forget(string $key): void
    {
        $this->store()->forget($this->prefixedKey($key));
    }

    public function clearForCreds(): void
    {
        if ($this->isTaggable()) {
            Cache::tags([$this->credentialsTag()])->flush();
        }
    }

    private function prefixedKey(string $key): string
    {
        return 'amazon-spapi-rdt:'.$this->credentialsId.':'.hash('sha256', $key);
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
