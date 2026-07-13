<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use Tims\AmazonSpApi\Enums\GrantlessScope;
use Tims\AmazonSpApi\Enums\Marketplace;
use Tims\AmazonSpApi\Enums\Region;

class EnumTest extends TestCase
{
    public function test_marketplace_maps_regions(): void
    {
        $this->assertSame(Region::NA, Marketplace::US->region());
        $this->assertSame(Region::EU, Marketplace::DE->region());
        $this->assertSame(Region::FE, Marketplace::JP->region());
    }

    public function test_marketplace_from_country_code(): void
    {
        $this->assertSame(Marketplace::US, Marketplace::fromCountryCode('us'));
        $this->assertSame(Marketplace::GB, Marketplace::fromCountryCode('UK'));
        $this->assertSame(Marketplace::GB, Marketplace::fromCountryCode('GB'));
    }

    public function test_marketplace_seller_central_urls(): void
    {
        $this->assertSame('https://sellercentral.amazon.com', Marketplace::US->sellerCentralUrl());
        $this->assertSame('https://sellercentral.amazon.co.uk', Marketplace::GB->sellerCentralUrl());
        $this->assertSame('https://sellercentral.amazon.co.jp', Marketplace::JP->sellerCentralUrl());
    }

    public function test_region_values(): void
    {
        $this->assertSame(['NA', 'EU', 'FE'], Region::values());
    }

    public function test_grantless_scope_values(): void
    {
        $this->assertContains('sellingpartnerapi::notifications', GrantlessScope::values());
        $this->assertContains('sellingpartnerapi::client_credential:rotation', GrantlessScope::values());
        $this->assertSame(
            'sellingpartnerapi::notifications',
            GrantlessScope::Notifications->value
        );
    }
}
