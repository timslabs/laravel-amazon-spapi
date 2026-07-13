<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use SpApi\Api\notifications\v1\NotificationsApi;
use Tims\AmazonSpApi\Enums\GrantlessScope;
use Tims\AmazonSpApi\Facades\AmazonSpApi;
use Tims\AmazonSpApi\SpApiManager;

class GrantlessTest extends TestCase
{
    public function test_grantless_configuration_builds_without_refresh_token(): void
    {
        $manager = $this->app->make(SpApiManager::class);
        $configuration = $manager->grantlessConfiguration(GrantlessScope::Notifications);

        $this->assertSame('https://sellingpartnerapi-na.amazon.com', $configuration->getHost());
    }

    public function test_make_grantless_instantiates_notifications_api(): void
    {
        $api = AmazonSpApi::makeGrantless(
            NotificationsApi::class,
            GrantlessScope::Notifications,
        );

        $this->assertInstanceOf(NotificationsApi::class, $api);
    }

    public function test_grantless_accepts_scope_strings(): void
    {
        $manager = new SpApiManager(config('amazon-spapi'));
        $configuration = $manager->grantlessConfiguration([
            'sellingpartnerapi::notifications',
            GrantlessScope::ClientCredentialRotation,
        ]);

        $this->assertSame('https://sellingpartnerapi-na.amazon.com', $configuration->getHost());
    }
}
