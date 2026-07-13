<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tims\AmazonSpApi\Http\RetryMiddleware;

class RetryMiddlewareTest extends TestCase
{
    public function test_retries_on_429_then_succeeds(): void
    {
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '0'], 'slow down'),
            new Response(200, [], 'ok'),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(RetryMiddleware::create([
            'max_attempts' => 3,
            'base_delay_ms' => 1,
        ]));

        $client = new Client(['handler' => $stack, 'http_errors' => false]);
        $response = $client->send(new Request('GET', 'https://sellingpartnerapi-na.amazon.com/orders/v0/orders'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', (string) $response->getBody());
        $this->assertSame(0, $mock->count());
    }

    public function test_stops_after_max_attempts(): void
    {
        $mock = new MockHandler([
            new Response(503, [], 'unavailable'),
            new Response(503, [], 'unavailable'),
            new Response(503, [], 'unavailable'),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(RetryMiddleware::create([
            'max_attempts' => 2,
            'base_delay_ms' => 1,
        ]));

        $client = new Client(['handler' => $stack, 'http_errors' => false]);
        $response = $client->send(new Request('GET', 'https://example.com/test'));

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(1, $mock->count()); // one leftover unused response
    }

    public function test_does_not_retry_client_errors(): void
    {
        $mock = new MockHandler([
            new Response(400, [], 'bad request'),
            new Response(200, [], 'should not be used'),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(RetryMiddleware::create([
            'max_attempts' => 3,
            'base_delay_ms' => 1,
        ]));

        $client = new Client(['handler' => $stack, 'http_errors' => false]);
        $response = $client->send(new Request('GET', 'https://example.com/test'));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(1, $mock->count());
    }
}
