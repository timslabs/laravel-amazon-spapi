<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use Mockery;
use SpApi\Api\tokens\v2021_03_01\TokensApi;
use SpApi\Model\tokens\v2021_03_01\CreateRestrictedDataTokenResponse;
use SpApi\Model\tokens\v2021_03_01\RestrictedResource;
use Tims\AmazonSpApi\Exceptions\RestrictedDataTokenException;
use Tims\AmazonSpApi\RestrictedDataTokenService;
use Tims\AmazonSpApi\SpApiManager;

class RestrictedDataTokenServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_normalize_resources_from_arrays(): void
    {
        $service = $this->service();

        $resources = $service->normalizeResources([
            [
                'path' => '/orders/v0/orders',
                'dataElements' => ['buyerInfo', 'shippingAddress'],
            ],
        ]);

        $this->assertCount(1, $resources);
        $this->assertSame('GET', $resources[0]->getMethod());
        $this->assertSame('/orders/v0/orders', $resources[0]->getPath());
        $this->assertSame(['buyerInfo', 'shippingAddress'], $resources[0]->getDataElements());
    }

    public function test_normalize_resources_requires_path(): void
    {
        $this->expectException(RestrictedDataTokenException::class);

        $this->service()->normalizeResources([['method' => 'GET']]);
    }

    public function test_is_restricted_operation_delegates_to_sdk(): void
    {
        $service = $this->service();

        $this->assertTrue($service->isRestrictedOperation('OrdersV0Api-getOrder'));
        $this->assertTrue($service->isRestrictedOperation('ReportsApi-getReportDocument'));
        $this->assertFalse($service->isRestrictedOperation('SellersApi-getMarketplaceParticipations'));
    }

    public function test_sandbox_skips_rdt_creation(): void
    {
        config([
            'amazon-spapi.single.sandbox' => true,
            'amazon-spapi.rdt.skip_in_sandbox' => true,
        ]);

        $service = new RestrictedDataTokenService(
            $this->app->make(SpApiManager::class),
            config('amazon-spapi'),
        );

        $this->assertNull($service->forPath('/orders/v0/orders'));
    }

    public function test_create_fetches_and_caches_token(): void
    {
        $response = Mockery::mock(CreateRestrictedDataTokenResponse::class);
        $response->shouldReceive('getRestrictedDataToken')->andReturn('Atza|rdt-token');
        $response->shouldReceive('getExpiresIn')->andReturn(3600);

        $tokensApi = Mockery::mock(TokensApi::class);
        $tokensApi->shouldReceive('createRestrictedDataToken')
            ->once()
            ->andReturn($response);

        $service = new RestrictedDataTokenService(
            $this->app->make(SpApiManager::class),
            config('amazon-spapi'),
            fn () => $tokensApi,
        );

        $first = $service->forPath(
            '/orders/v0/orders',
            dataElements: ['buyerInfo', 'shippingAddress'],
        );
        $second = $service->forPath(
            '/orders/v0/orders',
            dataElements: ['buyerInfo', 'shippingAddress'],
        );

        $this->assertSame('Atza|rdt-token', $first);
        $this->assertSame('Atza|rdt-token', $second);
    }

    public function test_manager_restricted_data_token_helper(): void
    {
        $this->assertTrue(
            $this->app->make(SpApiManager::class)->isRestrictedOperation('OrdersV0Api-getOrders')
        );
    }

    public function test_accepts_restricted_resource_instances(): void
    {
        $resource = new RestrictedResource;
        $resource->setMethod('GET');
        $resource->setPath('/orders/v0/orders/123-1234567-1234567');
        $resource->setDataElements(['buyerInfo']);

        $resources = $this->service()->normalizeResources([$resource]);

        $this->assertSame($resource, $resources[0]);
    }

    private function service(): RestrictedDataTokenService
    {
        return new RestrictedDataTokenService(
            $this->app->make(SpApiManager::class),
            config('amazon-spapi'),
        );
    }
}
