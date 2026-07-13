<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Support;

use Tims\AmazonSpApi\Models\Credentials;
use Tims\AmazonSpApi\SpApiManager;

trait ResolvesSpApiClient
{
    protected function credentials(): ?Credentials
    {
        if (! isset($this->credentialsId) || $this->credentialsId === null) {
            return null;
        }

        return Credentials::query()->findOrFail($this->credentialsId);
    }

    protected function manager(): SpApiManager
    {
        return app(SpApiManager::class);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $apiClass
     * @return T
     */
    protected function api(string $apiClass): object
    {
        return $this->manager()->make($apiClass, $this->credentials());
    }

    /**
     * @return array{connection: string|null, queue: string|null}
     */
    protected function spApiQueue(): array
    {
        return [
            'connection' => config('amazon-spapi.queue.connection'),
            'queue' => config('amazon-spapi.queue.queue'),
        ];
    }
}
