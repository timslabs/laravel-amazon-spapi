<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use SpApi\Api\tokens\v2021_03_01\TokensApi;
use SpApi\AuthAndAuth\RestrictedDataTokenSigner;
use SpApi\Configuration;
use SpApi\Model\tokens\v2021_03_01\CreateRestrictedDataTokenRequest;
use SpApi\Model\tokens\v2021_03_01\RestrictedResource;
use Tims\AmazonSpApi\Exceptions\RestrictedDataTokenException;
use Tims\AmazonSpApi\Models\Credentials;
use Tims\AmazonSpApi\Support\LaravelRdtCache;

class RestrictedDataTokenService
{
    /**
     * @param  array<string, mixed>  $config
     * @param  (\Closure(Configuration, ?ClientInterface): TokensApi)|null  $tokensApiFactory
     */
    public function __construct(
        private readonly SpApiManager $manager,
        private readonly array $config = [],
        private readonly ?\Closure $tokensApiFactory = null,
    ) {}

    /**
     * Create (or return a cached) Restricted Data Token for one or more resources.
     *
     * @param  list<RestrictedResource|array{method?: string, path: string, dataElements?: list<string>|null}>  $resources
     */
    public function create(
        array $resources,
        ?string $targetApplication = null,
        ?Credentials $credentials = null,
        ?ClientInterface $client = null,
    ): ?string {
        if ($this->shouldSkipForSandbox()) {
            return null;
        }

        $normalized = $this->normalizeResources($resources);
        $targetApplication ??= $this->config['rdt']['target_application'] ?? null;
        $credentialsId = $credentials?->getKey() ?? 'single';
        $cache = new LaravelRdtCache($credentialsId);
        $cacheKey = $this->cacheKey($normalized, $targetApplication);

        if ($cached = $cache->get($cacheKey)) {
            return $cached;
        }

        $request = new CreateRestrictedDataTokenRequest;
        $request->setRestrictedResources($normalized);

        if ($targetApplication) {
            $request->setTargetApplication($targetApplication);
        }

        $tokensApi = $this->makeTokensApi($credentials, $client);

        $response = $tokensApi->createRestrictedDataToken($request);
        $token = $response->getRestrictedDataToken();

        if (! is_string($token) || $token === '') {
            throw new RestrictedDataTokenException('Tokens API did not return a Restricted Data Token.');
        }

        $expiresIn = $response->getExpiresIn() ?? 3600;
        $cache->put($cacheKey, $token, (int) $expiresIn);

        return $token;
    }

    /**
     * Convenience helper for a single restricted path.
     *
     * @param  list<string>|null  $dataElements
     */
    public function forPath(
        string $path,
        string $method = RestrictedResource::METHOD_GET,
        ?array $dataElements = null,
        ?string $targetApplication = null,
        ?Credentials $credentials = null,
        ?ClientInterface $client = null,
    ): ?string {
        $dataElements ??= $this->config['rdt']['data_elements'] ?? null;

        return $this->create(
            resources: [[
                'method' => $method,
                'path' => $path,
                'dataElements' => $dataElements,
            ]],
            targetApplication: $targetApplication,
            credentials: $credentials,
            client: $client,
        );
    }

    /**
     * Whether an official SDK operation name requires an RDT.
     *
     * Operation names use the SDK format, e.g. "OrdersV0Api-getOrder".
     */
    public function isRestrictedOperation(string $operationName): bool
    {
        return RestrictedDataTokenSigner::isRestrictedOperation($operationName);
    }

    /**
     * @param  list<RestrictedResource|array{method?: string, path: string, dataElements?: list<string>|null}>  $resources
     * @return list<RestrictedResource>
     */
    public function normalizeResources(array $resources): array
    {
        if ($resources === []) {
            throw new RestrictedDataTokenException('At least one restricted resource is required.');
        }

        $normalized = [];

        foreach ($resources as $resource) {
            if ($resource instanceof RestrictedResource) {
                $normalized[] = $resource;

                continue;
            }

            if (! is_array($resource) || empty($resource['path'])) {
                throw new RestrictedDataTokenException(
                    'Each restricted resource must be a RestrictedResource or an array with a path.'
                );
            }

            $item = new RestrictedResource;
            $item->setMethod(strtoupper($resource['method'] ?? RestrictedResource::METHOD_GET));
            $item->setPath($resource['path']);

            $dataElements = $resource['dataElements'] ?? $resource['data_elements'] ?? null;
            if (is_array($dataElements) && $dataElements !== []) {
                $item->setDataElements(array_values($dataElements));
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    private function shouldSkipForSandbox(): bool
    {
        return (bool) ($this->config['single']['sandbox'] ?? false)
            && (bool) ($this->config['rdt']['skip_in_sandbox'] ?? true);
    }

    private function makeTokensApi(?Credentials $credentials, ?ClientInterface $client): TokensApi
    {
        $configuration = $this->manager->configuration($credentials);

        if ($this->tokensApiFactory) {
            return ($this->tokensApiFactory)($configuration, $client);
        }

        return new TokensApi($configuration, $client ?? new Client);
    }

    /**
     * @param  list<RestrictedResource>  $resources
     */
    private function cacheKey(array $resources, ?string $targetApplication): string
    {
        $payload = array_map(static function (RestrictedResource $resource): array {
            return [
                'method' => $resource->getMethod(),
                'path' => $resource->getPath(),
                'dataElements' => $resource->getDataElements(),
            ];
        }, $resources);

        return hash('sha256', json_encode([
            'resources' => $payload,
            'targetApplication' => $targetApplication,
        ], JSON_THROW_ON_ERROR));
    }
}
