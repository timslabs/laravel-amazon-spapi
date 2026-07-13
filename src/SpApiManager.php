<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use InvalidArgumentException;
use SpApi\AuthAndAuth\LWAAuthorizationCredentials;
use SpApi\Configuration;
use SpApi\Model\tokens\v2021_03_01\RestrictedResource;
use Tims\AmazonSpApi\Enums\Endpoint;
use Tims\AmazonSpApi\Enums\GrantlessScope;
use Tims\AmazonSpApi\Http\AutoRdtMiddleware;
use Tims\AmazonSpApi\Http\RetryMiddleware;
use Tims\AmazonSpApi\Models\Credentials;
use Tims\AmazonSpApi\Support\LaravelTokenCache;

class SpApiManager
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config = [],
    ) {}

    public function configuration(?Credentials $credentials = null): Configuration
    {
        [$clientId, $clientSecret, $refreshToken, $endpoint, $credentialsId] = $this->resolveCredentials($credentials);

        $lwaCredentials = new LWAAuthorizationCredentials([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'refreshToken' => $refreshToken,
            'endpoint' => $this->config['oauth']['lwa_token_endpoint']
                ?? 'https://api.amazon.com/auth/o2/token',
        ]);

        $tokenCache = new LaravelTokenCache($credentialsId);

        $configuration = new Configuration(
            [],
            $lwaCredentials,
            false,
            $tokenCache,
        );

        $configuration->setHost($endpoint->host());

        if (! empty($this->config['debug'])) {
            $configuration->setDebug(true);

            if (! empty($this->config['debug_file'])) {
                $configuration->setDebugFile($this->config['debug_file']);
            }
        }

        return $configuration;
    }

    /**
     * Build Configuration for grantless operations (client_credentials + scopes, no refresh token).
     *
     * @param  list<string|GrantlessScope>|string|GrantlessScope  $scopes
     */
    public function grantlessConfiguration(
        array|string|GrantlessScope $scopes = GrantlessScope::Notifications,
        ?string $region = null,
    ): Configuration {
        $clientId = $this->config['single']['lwa']['client_id'] ?? null;
        $clientSecret = $this->config['single']['lwa']['client_secret'] ?? null;

        if (! $clientId || ! $clientSecret) {
            throw new InvalidArgumentException(
                'Grantless mode requires AMAZON_SPAPI_LWA_CLIENT_ID and AMAZON_SPAPI_LWA_CLIENT_SECRET.'
            );
        }

        $normalizedScopes = $this->normalizeScopes($scopes);

        $lwaCredentials = new LWAAuthorizationCredentials([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'scopes' => $normalizedScopes,
            'endpoint' => $this->config['oauth']['lwa_token_endpoint']
                ?? 'https://api.amazon.com/auth/o2/token',
        ]);

        $tokenCache = new LaravelTokenCache('grantless:'.hash('sha256', implode(' ', $normalizedScopes)));

        $configuration = new Configuration(
            [],
            $lwaCredentials,
            false,
            $tokenCache,
        );

        $sandbox = (bool) ($this->config['single']['sandbox'] ?? false);
        $region ??= $this->config['single']['endpoint'] ?? 'NA';
        $configuration->setHost(Endpoint::byRegion($region, $sandbox)->host());

        if (! empty($this->config['debug'])) {
            $configuration->setDebug(true);

            if (! empty($this->config['debug_file'])) {
                $configuration->setDebugFile($this->config['debug_file']);
            }
        }

        return $configuration;
    }

    /**
     * Instantiate an official SDK API client using grantless LWA credentials.
     *
     * @template T of object
     *
     * @param  class-string<T>  $apiClass
     * @param  list<string|GrantlessScope>|string|GrantlessScope  $scopes
     * @return T
     */
    public function makeGrantless(
        string $apiClass,
        array|string|GrantlessScope $scopes = GrantlessScope::Notifications,
        ?ClientInterface $client = null,
        ?string $region = null,
    ): object {
        if (! class_exists($apiClass)) {
            throw new InvalidArgumentException("API class [{$apiClass}] does not exist.");
        }

        return new $apiClass(
            $this->grantlessConfiguration($scopes, $region),
            $client ?? $this->createHttpClient(null, ['auto_rdt' => false]),
        );
    }

    /**
     * Instantiate an official SDK API client class.
     *
     * By default the HTTP client includes auto-RDT middleware:
     * set data_elements once, then call restricted operations without passing
     * restrictedDataToken on every method.
     *
     * @template T of object
     *
     * @param  class-string<T>  $apiClass
     * @param  array{auto_rdt?: bool, data_elements?: list<string>|null, target_application?: string|null}  $options
     * @return T
     */
    public function make(
        string $apiClass,
        ?Credentials $credentials = null,
        ?ClientInterface $client = null,
        array $options = [],
    ): object {
        if (! class_exists($apiClass)) {
            throw new InvalidArgumentException("API class [{$apiClass}] does not exist.");
        }

        return new $apiClass(
            $this->configuration($credentials),
            $client ?? $this->createHttpClient($credentials, $options),
        );
    }

    /**
     * Build a Guzzle client with optional auto-RDT and retry middleware.
     *
     * @param  array{auto_rdt?: bool, retry?: bool, data_elements?: list<string>|null, target_application?: string|null}  $options
     */
    public function createHttpClient(?Credentials $credentials = null, array $options = []): Client
    {
        $autoRdt = $options['auto_rdt'] ?? $this->config['rdt']['auto'] ?? true;
        $retry = $options['retry'] ?? $this->config['retry']['enabled'] ?? true;

        if (! $autoRdt && ! $retry) {
            return new Client;
        }

        $stack = HandlerStack::create();

        if ($autoRdt) {
            $stack->push(new AutoRdtMiddleware(
                $this->rdt(),
                [
                    'data_elements' => $options['data_elements']
                        ?? $this->config['rdt']['data_elements']
                        ?? null,
                    'target_application' => $options['target_application']
                        ?? $this->config['rdt']['target_application']
                        ?? null,
                    'credentials' => $credentials,
                ],
            ));
        }

        if ($retry) {
            // Pushed last so it runs outermost and can retry the full request chain.
            $stack->push(RetryMiddleware::create($this->config['retry'] ?? []));
        }

        return new Client(['handler' => $stack]);
    }

    /**
     * Create (or reuse a cached) Restricted Data Token.
     *
     * @param  list<RestrictedResource|array{method?: string, path: string, dataElements?: list<string>|null}>  $resources
     */
    public function createRestrictedDataToken(
        array $resources,
        ?string $targetApplication = null,
        ?Credentials $credentials = null,
        ?ClientInterface $client = null,
    ): ?string {
        return $this->rdt()->create($resources, $targetApplication, $credentials, $client);
    }

    /**
     * Create an RDT for a single restricted path.
     *
     * @param  list<string>|null  $dataElements
     */
    public function restrictedDataToken(
        string $path,
        string $method = 'GET',
        ?array $dataElements = null,
        ?string $targetApplication = null,
        ?Credentials $credentials = null,
        ?ClientInterface $client = null,
    ): ?string {
        return $this->rdt()->forPath(
            $path,
            $method,
            $dataElements,
            $targetApplication,
            $credentials,
            $client,
        );
    }

    public function isRestrictedOperation(string $operationName): bool
    {
        return $this->rdt()->isRestrictedOperation($operationName);
    }

    public function rdt(): RestrictedDataTokenService
    {
        return new RestrictedDataTokenService($this, $this->config);
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: Endpoint, 4: int|string}
     */
    private function resolveCredentials(?Credentials $credentials): array
    {
        if ($credentials !== null) {
            $clientId = $credentials->client_id
                ?? $this->config['single']['lwa']['client_id']
                ?? null;
            $clientSecret = $credentials->client_secret
                ?? $this->config['single']['lwa']['client_secret']
                ?? null;
            $refreshToken = $credentials->refresh_token;

            if (! $clientId || ! $clientSecret || ! $refreshToken) {
                throw new InvalidArgumentException(
                    'Credentials are missing client_id, client_secret, or refresh_token.'
                );
            }

            $sandbox = (bool) ($this->config['single']['sandbox'] ?? false);

            return [
                $clientId,
                $clientSecret,
                $refreshToken,
                Endpoint::byRegion($credentials->region, $sandbox),
                $credentials->getKey() ?? 'multi',
            ];
        }

        $clientId = $this->config['single']['lwa']['client_id'] ?? null;
        $clientSecret = $this->config['single']['lwa']['client_secret'] ?? null;
        $refreshToken = $this->config['single']['lwa']['refresh_token'] ?? null;
        $region = $this->config['single']['endpoint'] ?? 'NA';
        $sandbox = (bool) ($this->config['single']['sandbox'] ?? false);

        if (! $clientId || ! $clientSecret || ! $refreshToken) {
            throw new InvalidArgumentException(
                'Single-seller mode requires AMAZON_SPAPI_LWA_CLIENT_ID, AMAZON_SPAPI_LWA_CLIENT_SECRET, and AMAZON_SPAPI_LWA_REFRESH_TOKEN.'
            );
        }

        return [
            $clientId,
            $clientSecret,
            $refreshToken,
            Endpoint::byRegion($region, $sandbox),
            'single',
        ];
    }

    /**
     * @param  list<string|GrantlessScope>|string|GrantlessScope  $scopes
     * @return list<string>
     */
    private function normalizeScopes(array|string|GrantlessScope $scopes): array
    {
        if ($scopes instanceof GrantlessScope) {
            return [$scopes->value];
        }

        if (is_string($scopes)) {
            return [$scopes];
        }

        if ($scopes === []) {
            throw new InvalidArgumentException('At least one grantless scope is required.');
        }

        return array_values(array_map(
            static fn ($scope): string => $scope instanceof GrantlessScope
                ? $scope->value
                : (string) $scope,
            $scopes,
        ));
    }
}
