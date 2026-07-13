<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use Tims\AmazonSpApi\Support\LaravelRdtCache;

class LaravelRdtCacheTest extends TestCase
{
    public function test_stores_and_retrieves_tokens(): void
    {
        $cache = new LaravelRdtCache('1');

        $cache->put('resource-key', 'rdt-value', 3600);

        $this->assertSame('rdt-value', $cache->get('resource-key'));
    }

    public function test_forget_removes_token(): void
    {
        $cache = new LaravelRdtCache('1');
        $cache->put('resource-key', 'rdt-value', 3600);
        $cache->forget('resource-key');

        $this->assertNull($cache->get('resource-key'));
    }

    public function test_clear_for_creds_flushes_tokens(): void
    {
        $cache = new LaravelRdtCache('42');
        $cache->put('resource-key', 'value', 3600);

        $this->assertSame('value', $cache->get('resource-key'));

        $cache->clearForCreds();

        $this->assertNull($cache->get('resource-key'));
    }

    public function test_credentials_are_isolated_by_id(): void
    {
        $cacheA = new LaravelRdtCache('a');
        $cacheB = new LaravelRdtCache('b');

        $cacheA->put('shared-key', 'from-a', 3600);
        $cacheB->put('shared-key', 'from-b', 3600);

        $this->assertSame('from-a', $cacheA->get('shared-key'));
        $this->assertSame('from-b', $cacheB->get('shared-key'));

        $cacheA->clearForCreds();

        $this->assertNull($cacheA->get('shared-key'));
        $this->assertSame('from-b', $cacheB->get('shared-key'));
    }
}
