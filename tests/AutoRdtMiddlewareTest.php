<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use SpApi\AuthAndAuth\LWAAuthorizationSigner;
use Tims\AmazonSpApi\Http\AutoRdtMiddleware;
use Tims\AmazonSpApi\RestrictedDataTokenService;
use Tims\AmazonSpApi\SpApiManager;

class AutoRdtMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_attaches_rdt_header_for_restricted_paths(): void
    {
        $rdt = Mockery::mock(RestrictedDataTokenService::class);
        $rdt->shouldReceive('forPath')
            ->once()
            ->withArgs(function (string $path, string $method, ?array $dataElements): bool {
                return $path === '/orders/v0/orders'
                    && $method === 'GET'
                    && $dataElements === ['buyerInfo', 'shippingAddress'];
            })
            ->andReturn('Atza|auto-rdt');

        $middleware = new AutoRdtMiddleware($rdt, [
            'data_elements' => ['buyerInfo', 'shippingAddress'],
        ]);

        $handler = $middleware(function ($request, $options) {
            return Create::promiseFor(
                new Response(200, [], (string) $request->getHeaderLine(
                    LWAAuthorizationSigner::SIGNED_ACCESS_TOKEN_HEADER_NAME
                ))
            );
        });

        $response = $handler(
            new Request('GET', 'https://sellingpartnerapi-na.amazon.com/orders/v0/orders'),
            []
        )->wait();

        $this->assertSame('Atza|auto-rdt', (string) $response->getBody());
    }

    public function test_skips_unrestricted_paths(): void
    {
        $rdt = Mockery::mock(RestrictedDataTokenService::class);
        $rdt->shouldReceive('forPath')->never();

        $middleware = new AutoRdtMiddleware($rdt);

        $called = false;
        $handler = $middleware(function ($request, $options) use (&$called) {
            $called = true;

            return Create::promiseFor(new Response(200));
        });

        $handler(
            new Request('GET', 'https://sellingpartnerapi-na.amazon.com/sellers/v1/marketplaceParticipations'),
            []
        )->wait();

        $this->assertTrue($called);
    }

    public function test_make_enables_auto_rdt_client_by_default(): void
    {
        $manager = $this->app->make(SpApiManager::class);
        $client = $manager->createHttpClient();

        $this->assertInstanceOf(Client::class, $client);
    }

    public function test_make_can_disable_auto_rdt(): void
    {
        $manager = $this->app->make(SpApiManager::class);
        $client = $manager->createHttpClient(options: ['auto_rdt' => false]);

        $this->assertInstanceOf(Client::class, $client);
    }
}
