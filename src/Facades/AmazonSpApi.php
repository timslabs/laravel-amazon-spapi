<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Facades;

use Illuminate\Support\Facades\Facade;
use Tims\AmazonSpApi\SpApiManager;

/**
 * @method static \SpApi\Configuration configuration(?\Tims\AmazonSpApi\Models\Credentials $credentials = null)
 * @method static \SpApi\Configuration grantlessConfiguration(array|string|\Tims\AmazonSpApi\Enums\GrantlessScope $scopes = \Tims\AmazonSpApi\Enums\GrantlessScope::Notifications, ?string $region = null)
 * @method static object make(string $apiClass, ?\Tims\AmazonSpApi\Models\Credentials $credentials = null, ?\GuzzleHttp\ClientInterface $client = null, array $options = [])
 * @method static object makeGrantless(string $apiClass, array|string|\Tims\AmazonSpApi\Enums\GrantlessScope $scopes = \Tims\AmazonSpApi\Enums\GrantlessScope::Notifications, ?\GuzzleHttp\ClientInterface $client = null, ?string $region = null)
 * @method static \GuzzleHttp\Client createHttpClient(?\Tims\AmazonSpApi\Models\Credentials $credentials = null, array $options = [])
 * @method static string|null createRestrictedDataToken(array $resources, ?string $targetApplication = null, ?\Tims\AmazonSpApi\Models\Credentials $credentials = null, ?\GuzzleHttp\ClientInterface $client = null)
 * @method static string|null restrictedDataToken(string $path, string $method = 'GET', ?array $dataElements = null, ?string $targetApplication = null, ?\Tims\AmazonSpApi\Models\Credentials $credentials = null, ?\GuzzleHttp\ClientInterface $client = null)
 * @method static bool isRestrictedOperation(string $operationName)
 * @method static \Tims\AmazonSpApi\RestrictedDataTokenService rdt()
 *
 * @see SpApiManager
 */
class AmazonSpApi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SpApiManager::class;
    }
}
