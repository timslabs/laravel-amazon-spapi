<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use SpApi\Api\sellers\v1\SellersApi;
use SpApi\Configuration;
use Tims\AmazonSpApi\Models\Credentials;
use Tims\AmazonSpApi\Models\Seller;
use Tims\AmazonSpApi\Support\LaravelRdtCache;
use Tims\AmazonSpApi\Support\LaravelTokenCache;

class MultiSellerTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function test_credentials_can_build_api_clients(): void
    {
        $seller = Seller::create(['name' => 'Acme']);

        $credentials = Credentials::create([
            'seller_id' => $seller->id,
            'selling_partner_id' => 'A1EXAMPLE',
            'region' => 'EU',
            'refresh_token' => 'Atzr|seller-refresh',
        ]);

        $api = $credentials->make(SellersApi::class);

        $this->assertInstanceOf(SellersApi::class, $api);
        $this->assertSame(
            'https://sellingpartnerapi-eu.amazon.com',
            $api->getConfig()->getHost()
        );
    }

    public function test_seller_credentials_relationship(): void
    {
        $seller = Seller::create(['name' => 'Acme']);

        Credentials::create([
            'seller_id' => $seller->id,
            'selling_partner_id' => 'A1EXAMPLE',
            'region' => 'NA',
            'refresh_token' => 'Atzr|seller-refresh',
        ]);

        $this->assertCount(1, $seller->credentials);
        $this->assertTrue($seller->credentials->first()->seller->is($seller));
    }

    public function test_refresh_token_is_encrypted_at_rest(): void
    {
        $seller = Seller::create(['name' => 'Acme']);

        $credentials = Credentials::create([
            'seller_id' => $seller->id,
            'selling_partner_id' => 'A1EXAMPLE',
            'region' => 'NA',
            'refresh_token' => 'Atzr|plain-refresh',
        ]);

        $raw = $this->app['db']->table('spapi_credentials')
            ->where('id', $credentials->id)
            ->value('refresh_token');

        $this->assertNotSame('Atzr|plain-refresh', $raw);
        $this->assertSame('Atzr|plain-refresh', $credentials->fresh()->refresh_token);
    }

    public function test_per_seller_lwa_client_id_override(): void
    {
        $seller = Seller::create(['name' => 'Acme']);

        $credentials = Credentials::create([
            'seller_id' => $seller->id,
            'selling_partner_id' => 'A1EXAMPLE',
            'region' => 'NA',
            'client_id' => 'seller-client-id',
            'client_secret' => 'seller-client-secret',
            'refresh_token' => 'Atzr|seller-refresh',
        ]);

        $configuration = $credentials->configuration();

        $this->assertInstanceOf(Configuration::class, $configuration);
        $this->assertSame(
            'https://sellingpartnerapi-na.amazon.com',
            $configuration->getHost()
        );
    }

    public function test_updating_credentials_clears_cached_tokens(): void
    {
        $seller = Seller::create(['name' => 'Acme']);

        $credentials = Credentials::create([
            'seller_id' => $seller->id,
            'selling_partner_id' => 'A1EXAMPLE',
            'region' => 'NA',
            'refresh_token' => 'Atzr|seller-refresh',
        ]);

        $tokenCache = new LaravelTokenCache($credentials->id);
        $rdtCache = new LaravelRdtCache($credentials->id);
        $tokenCache->set('lwa', 'access-token', 3600);
        $rdtCache->put('rdt', 'restricted-token', 3600);

        $credentials->update(['refresh_token' => 'Atzr|rotated']);

        $this->assertNull($tokenCache->get('lwa'));
        $this->assertNull($rdtCache->get('rdt'));
    }
}
