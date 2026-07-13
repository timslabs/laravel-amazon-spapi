<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use Tims\AmazonSpApi\Contracts\TokenCache;
use Tims\AmazonSpApi\Support\LaravelTokenCache;

class LaravelTokenCacheTest extends TestCase
{
    public function test_stores_and_retrieves_tokens(): void
    {
        $cache = new LaravelTokenCache('1');

        $cache->set('token-key', 'access-token-value', 3600);

        $this->assertSame('access-token-value', $cache->get('token-key'));
    }

    public function test_remove_forgets_token(): void
    {
        $cache = new LaravelTokenCache('1');
        $cache->set('token-key', 'access-token-value', 3600);
        $cache->remove('token-key');

        $this->assertNull($cache->get('token-key'));
    }

    public function test_clear_for_creds_flushes_tagged_tokens(): void
    {
        $cache = new LaravelTokenCache('42');
        $cache->set('token-key', 'value', 3600);

        $this->assertSame('value', $cache->get('token-key'));

        $cache->clearForCreds();

        $this->assertNull($cache->get('token-key'));
    }

    public function test_credentials_are_isolated_by_id(): void
    {
        $cacheA = new LaravelTokenCache('a');
        $cacheB = new LaravelTokenCache('b');

        $cacheA->set('shared-key', 'from-a', 3600);
        $cacheB->set('shared-key', 'from-b', 3600);

        $this->assertSame('from-a', $cacheA->get('shared-key'));
        $this->assertSame('from-b', $cacheB->get('shared-key'));

        $cacheA->clearForCreds();

        $this->assertNull($cacheA->get('shared-key'));
        $this->assertSame('from-b', $cacheB->get('shared-key'));
    }

    public function test_token_cache_implements_contract(): void
    {
        $cache = new LaravelTokenCache('1');

        $this->assertInstanceOf(TokenCache::class, $cache);
    }
}
