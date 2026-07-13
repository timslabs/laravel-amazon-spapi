<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use SpApi\Configuration;
use Tims\AmazonSpApi\AmazonSpApiServiceProvider;
use Tims\AmazonSpApi\Facades\AmazonSpApi;
use Tims\AmazonSpApi\SpApiManager;

class ServiceProviderTest extends TestCase
{
    public function test_registers_sp_api_manager_as_singleton(): void
    {
        $a = $this->app->make(SpApiManager::class);
        $b = $this->app->make(SpApiManager::class);

        $this->assertSame($a, $b);
        $this->assertSame($a, $this->app->make('amazon-spapi'));
    }

    public function test_binds_configuration_in_single_mode(): void
    {
        $this->assertTrue($this->app->bound(Configuration::class));
        $this->assertInstanceOf(Configuration::class, $this->app->make(Configuration::class));
    }

    public function test_facade_resolves_to_manager(): void
    {
        $this->assertInstanceOf(SpApiManager::class, AmazonSpApi::getFacadeRoot());
        $this->assertSame(
            'https://sellingpartnerapi-na.amazon.com',
            AmazonSpApi::configuration()->getHost()
        );
    }

    public function test_merges_package_config(): void
    {
        $this->assertSame('single', config('amazon-spapi.installation_type'));
        $this->assertTrue(config('amazon-spapi.rdt.auto'));
        $this->assertTrue(config('amazon-spapi.retry.enabled'));
        $this->assertSame('amazon-spapi', config('amazon-spapi.queue.queue'));
    }

    public function test_publishes_config_and_migrations(): void
    {
        $this->artisan('vendor:publish', [
            '--provider' => AmazonSpApiServiceProvider::class,
            '--tag' => 'amazon-spapi-config',
        ])->assertSuccessful();

        $this->assertFileExists(config_path('amazon-spapi.php'));

        $this->artisan('vendor:publish', [
            '--provider' => AmazonSpApiServiceProvider::class,
            '--tag' => 'amazon-spapi-multi-seller',
        ])->assertSuccessful();

        $this->assertFileExists(database_path('migrations/2026_07_14_000001_create_spapi_sellers_table.php'));
        $this->assertFileExists(database_path('migrations/2026_07_14_000002_create_spapi_credentials_table.php'));
    }

    public function test_provider_declares_provides(): void
    {
        $provider = new AmazonSpApiServiceProvider($this->app);

        $this->assertSame([
            SpApiManager::class,
            'amazon-spapi',
            Configuration::class,
        ], $provider->provides());
    }
}
