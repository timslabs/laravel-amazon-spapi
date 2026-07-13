<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use SpApi\Configuration;
use Tims\AmazonSpApi\SpApiManager;

class MultiInstallationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('amazon-spapi.installation_type', 'multi');
    }

    public function test_does_not_bind_default_configuration_in_multi_mode(): void
    {
        $this->assertSame('multi', config('amazon-spapi.installation_type'));
        $this->assertFalse($this->app->bound(Configuration::class));
        $this->assertTrue($this->app->bound(SpApiManager::class));
    }
}
