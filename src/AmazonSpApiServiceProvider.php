<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi;

use Illuminate\Support\ServiceProvider;
use SpApi\Configuration;

class AmazonSpApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/amazon-spapi.php', 'amazon-spapi');

        $this->app->singleton(SpApiManager::class, function ($app) {
            return new SpApiManager(
                config: $app['config']->get('amazon-spapi', []),
            );
        });

        $this->app->alias(SpApiManager::class, 'amazon-spapi');
    }

    public function boot(): void
    {
        // Bound in boot so application config (and Testbench defineEnvironment) is final.
        if ($this->app['config']->get('amazon-spapi.installation_type') === 'single') {
            $this->app->bind(Configuration::class, function ($app) {
                return $app->make(SpApiManager::class)->configuration();
            });
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/amazon-spapi.php' => config_path('amazon-spapi.php'),
            ], 'amazon-spapi-config');

            $migrationsDir = __DIR__.'/../database/migrations';
            $sellersMigration = '2026_07_14_000001_create_spapi_sellers_table.php';
            $credentialsMigration = '2026_07_14_000002_create_spapi_credentials_table.php';

            $this->publishes([
                "{$migrationsDir}/{$sellersMigration}" => database_path("migrations/{$sellersMigration}"),
                "{$migrationsDir}/{$credentialsMigration}" => database_path("migrations/{$credentialsMigration}"),
            ], 'amazon-spapi-multi-seller');
        }
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [
            SpApiManager::class,
            'amazon-spapi',
            Configuration::class,
        ];
    }
}
