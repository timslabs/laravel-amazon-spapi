<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use Mockery;
use SpApi\Api\notifications\v1\NotificationsApi;
use SpApi\Api\reports\v2021_06_30\ReportsApi;
use SpApi\Model\notifications\v1\GetDestinationsResponse;
use SpApi\Model\reports\v2021_06_30\Report;
use Tims\AmazonSpApi\Facades\AmazonSpApi;
use Tims\AmazonSpApi\Testing\SpApiFake;

class SpApiFakeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fake_returns_registered_mocks(): void
    {
        $report = Mockery::mock(Report::class);

        $fake = SpApiFake::start();
        $fake->mock(ReportsApi::class, function ($api) use ($report): void {
            $api->shouldReceive('getReport')->once()->with('R1')->andReturn($report);
        });

        $api = AmazonSpApi::make(ReportsApi::class);

        $this->assertSame($report, $api->getReport('R1'));
        $this->assertTrue($fake->has(ReportsApi::class));
    }

    public function test_fake_supports_grantless_make(): void
    {
        $response = Mockery::mock(GetDestinationsResponse::class);

        $fake = SpApiFake::start();
        $fake->mock(NotificationsApi::class, function ($api) use ($response): void {
            $api->shouldReceive('getDestinations')->once()->andReturn($response);
        });

        $api = AmazonSpApi::makeGrantless(NotificationsApi::class);

        $this->assertSame($response, $api->getDestinations());
    }
}
