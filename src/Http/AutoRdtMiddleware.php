<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use SpApi\AuthAndAuth\LWAAuthorizationSigner;
use Tims\AmazonSpApi\Models\Credentials;
use Tims\AmazonSpApi\RestrictedDataTokenService;
use Tims\AmazonSpApi\Support\RestrictedPathMatcher;

/**
 * Automatically attaches Restricted Data Tokens for PII-restricted SP-API calls.
 */
class AutoRdtMiddleware
{
    /**
     * @param  array{data_elements?: list<string>|null, target_application?: string|null, credentials?: Credentials|null}  $options
     */
    public function __construct(
        private readonly RestrictedDataTokenService $rdt,
        private readonly array $options = [],
    ) {}

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $path = $request->getUri()->getPath();
            $match = RestrictedPathMatcher::match($request->getMethod(), $path);

            if ($match === null) {
                return $handler($request, $options);
            }

            $dataElements = null;
            if ($match['supportsDataElements']) {
                $dataElements = $this->options['data_elements'] ?? null;
                if (is_array($dataElements) && $dataElements === []) {
                    $dataElements = null;
                }
            }

            // Always use a plain client so Tokens API calls are not re-entered here.
            $token = $this->rdt->forPath(
                path: $match['path'],
                method: strtoupper($request->getMethod()),
                dataElements: $dataElements,
                targetApplication: $this->options['target_application'] ?? null,
                credentials: $this->options['credentials'] ?? null,
                client: new Client,
            );

            if (is_string($token) && $token !== '') {
                $request = $request->withHeader(
                    LWAAuthorizationSigner::SIGNED_ACCESS_TOKEN_HEADER_NAME,
                    $token,
                );
            }

            return $handler($request, $options);
        };
    }
}
