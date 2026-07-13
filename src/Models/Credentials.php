<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Models;

use GuzzleHttp\ClientInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SpApi\Configuration;
use SpApi\Model\tokens\v2021_03_01\RestrictedResource;
use Tims\AmazonSpApi\SpApiManager;
use Tims\AmazonSpApi\Support\LaravelRdtCache;
use Tims\AmazonSpApi\Support\LaravelTokenCache;

class Credentials extends Model
{
    protected $table = 'spapi_credentials';

    protected $fillable = [
        'selling_partner_id',
        'region',
        'client_id',
        'client_secret',
        'refresh_token',
        'seller_id',
    ];

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'refresh_token' => 'encrypted',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function configuration(): Configuration
    {
        return $this->manager()->configuration($this);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $apiClass
     * @param  array{auto_rdt?: bool, data_elements?: list<string>|null, target_application?: string|null}  $options
     * @return T
     */
    public function make(string $apiClass, ?ClientInterface $client = null, array $options = []): object
    {
        return $this->manager()->make($apiClass, $this, $client, $options);
    }

    /**
     * @param  list<RestrictedResource|array{method?: string, path: string, dataElements?: list<string>|null}>  $resources
     */
    public function createRestrictedDataToken(
        array $resources,
        ?string $targetApplication = null,
        ?ClientInterface $client = null,
    ): ?string {
        return $this->manager()->createRestrictedDataToken(
            $resources,
            $targetApplication,
            $this,
            $client,
        );
    }

    /**
     * @param  list<string>|null  $dataElements
     */
    public function restrictedDataToken(
        string $path,
        string $method = 'GET',
        ?array $dataElements = null,
        ?string $targetApplication = null,
        ?ClientInterface $client = null,
    ): ?string {
        return $this->manager()->restrictedDataToken(
            $path,
            $method,
            $dataElements,
            $targetApplication,
            $this,
            $client,
        );
    }

    protected static function booted(): void
    {
        static::updating(function (self $credentials): void {
            $id = $credentials->getKey();
            (new LaravelTokenCache($id))->clearForCreds();
            (new LaravelRdtCache($id))->clearForCreds();
        });
    }

    private function manager(): SpApiManager
    {
        return app(SpApiManager::class);
    }
}
