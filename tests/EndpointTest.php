<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use Tims\AmazonSpApi\Enums\Endpoint;
use Tims\AmazonSpApi\Enums\Marketplace;
use Tims\AmazonSpApi\Enums\Region;

class EndpointTest extends TestCase
{
    public function test_by_region_returns_production_hosts(): void
    {
        $this->assertSame(
            'https://sellingpartnerapi-na.amazon.com',
            Endpoint::byRegion('NA')->host()
        );
        $this->assertSame(
            'https://sellingpartnerapi-eu.amazon.com',
            Endpoint::byRegion(Region::EU)->host()
        );
        $this->assertSame(
            'https://sellingpartnerapi-fe.amazon.com',
            Endpoint::byRegion(Region::FE)->host()
        );
    }

    public function test_by_region_returns_sandbox_hosts(): void
    {
        $endpoint = Endpoint::byRegion(Region::NA, sandbox: true);

        $this->assertTrue($endpoint->isSandbox());
        $this->assertSame(
            'https://sandbox.sellingpartnerapi-na.amazon.com',
            $endpoint->host()
        );
    }

    public function test_by_marketplace_maps_to_region(): void
    {
        $this->assertSame(Region::NA, Endpoint::byMarketplace(Marketplace::US)->region());
        $this->assertSame(Region::EU, Endpoint::byMarketplace(Marketplace::DE)->region());
        $this->assertSame(Region::FE, Endpoint::byMarketplace(Marketplace::JP)->region());
    }
}
