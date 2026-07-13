<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use InvalidArgumentException;
use SpApi\Api\sellers\v1\SellersApi;
use SpApi\Configuration;
use Tims\AmazonSpApi\Facades\AmazonSpApi;
use Tims\AmazonSpApi\SpApiManager;

class SpApiManagerTest extends TestCase
{
    public function test_configuration_uses_single_seller_credentials(): void
    {
        $manager = $this->app->make(SpApiManager::class);
        $configuration = $manager->configuration();

        $this->assertInstanceOf(Configuration::class, $configuration);
        $this->assertSame('https://sellingpartnerapi-na.amazon.com', $configuration->getHost());
    }

    public function test_make_instantiates_official_api_client(): void
    {
        $api = AmazonSpApi::make(SellersApi::class);

        $this->assertInstanceOf(SellersApi::class, $api);
        $this->assertSame(
            'https://sellingpartnerapi-na.amazon.com',
            $api->getConfig()->getHost()
        );
    }

    public function test_sandbox_endpoint_is_applied(): void
    {
        config(['amazon-spapi.single.sandbox' => true]);

        $manager = new SpApiManager(config('amazon-spapi'));
        $configuration = $manager->configuration();

        $this->assertSame(
            'https://sandbox.sellingpartnerapi-na.amazon.com',
            $configuration->getHost()
        );
    }

    public function test_missing_single_seller_credentials_throw(): void
    {
        $manager = new SpApiManager([
            'single' => [
                'lwa' => [
                    'client_id' => null,
                    'client_secret' => null,
                    'refresh_token' => null,
                ],
                'endpoint' => 'NA',
                'sandbox' => false,
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        $manager->configuration();
    }

    public function test_retry_can_be_disabled_per_client(): void
    {
        $api = AmazonSpApi::make(SellersApi::class, options: ['retry' => false]);

        $this->assertInstanceOf(SellersApi::class, $api);
    }
}
