<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Tims\AmazonSpApi\AmazonSpApiServiceProvider;
use Tims\AmazonSpApi\Facades\AmazonSpApi;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AmazonSpApiServiceProvider::class,
        ];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'AmazonSpApi' => AmazonSpApi::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('amazon-spapi.installation_type', 'single');
        $app['config']->set('amazon-spapi.single.lwa.client_id', 'test-client-id');
        $app['config']->set('amazon-spapi.single.lwa.client_secret', 'test-client-secret');
        $app['config']->set('amazon-spapi.single.lwa.refresh_token', 'test-refresh-token');
        $app['config']->set('amazon-spapi.single.endpoint', 'NA');
        $app['config']->set('amazon-spapi.oauth.application_id', 'amzn1.sp.solution.example');
        $app['config']->set('amazon-spapi.oauth.redirect_uri', 'https://example.com/amazon/callback');
        $app['config']->set('cache.default', 'array');
    }
}
