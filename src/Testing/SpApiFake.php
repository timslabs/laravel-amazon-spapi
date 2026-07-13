<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Testing;

use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Tims\AmazonSpApi\Enums\GrantlessScope;
use Tims\AmazonSpApi\Models\Credentials;
use Tims\AmazonSpApi\SpApiManager;

/**
 * Swap SpApiManager so make() / makeGrantless() return Mockery doubles.
 *
 * Requires mockery/mockery (pulled in via orchestra/testbench in this package).
 */
class SpApiFake
{
    /** @var array<class-string, MockInterface> */
    private array $apis = [];

    public static function start(?array $config = null): self
    {
        $fake = new self;

        $manager = new class($fake, $config ?? config('amazon-spapi', [])) extends SpApiManager
        {
            public function __construct(
                private readonly SpApiFake $fake,
                array $config,
            ) {
                parent::__construct($config);
            }

            public function make(
                string $apiClass,
                ?Credentials $credentials = null,
                ?ClientInterface $client = null,
                array $options = [],
            ): object {
                return $this->fake->resolve($apiClass);
            }

            public function makeGrantless(
                string $apiClass,
                array|string|GrantlessScope $scopes = GrantlessScope::Notifications,
                ?ClientInterface $client = null,
                ?string $region = null,
            ): object {
                return $this->fake->resolve($apiClass);
            }
        };

        app()->instance(SpApiManager::class, $manager);

        return $fake;
    }

    /**
     * @param  class-string  $apiClass
     * @param  callable(MockInterface): void|null  $configure
     */
    public function mock(string $apiClass, ?callable $configure = null): MockInterface
    {
        $mock = Mockery::mock($apiClass);
        if ($configure) {
            $configure($mock);
        }

        $this->apis[$apiClass] = $mock;

        return $mock;
    }

    /**
     * @param  class-string  $apiClass
     */
    public function resolve(string $apiClass): object
    {
        if (! isset($this->apis[$apiClass])) {
            throw new InvalidArgumentException(
                "No fake registered for [{$apiClass}]. Call SpApiFake::mock({$apiClass}::class) first."
            );
        }

        return $this->apis[$apiClass];
    }

    /**
     * @param  class-string  $apiClass
     */
    public function has(string $apiClass): bool
    {
        return isset($this->apis[$apiClass]);
    }
}
