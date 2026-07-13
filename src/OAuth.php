<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Tims\AmazonSpApi\Enums\Marketplace;
use Tims\AmazonSpApi\Exceptions\OAuthException;

class OAuth
{
    private const DEFAULT_TOKEN_URL = 'https://api.amazon.com/auth/o2/token';

    private ClientInterface $httpClient;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
        private readonly string $applicationId,
        ?ClientInterface $httpClient = null,
        private readonly string $tokenEndpoint = self::DEFAULT_TOKEN_URL,
    ) {
        if (! str_starts_with($this->redirectUri, 'https://')) {
            throw new OAuthException('Redirect URI must use SSL (https://).');
        }

        $this->httpClient = $httpClient ?? new Client;
    }

    public static function fromConfig(?ClientInterface $httpClient = null): self
    {
        $oauth = config('amazon-spapi.oauth', []);
        $lwa = config('amazon-spapi.single.lwa', []);

        return new self(
            clientId: (string) ($lwa['client_id'] ?? ''),
            clientSecret: (string) ($lwa['client_secret'] ?? ''),
            redirectUri: (string) ($oauth['redirect_uri'] ?? ''),
            applicationId: (string) ($oauth['application_id'] ?? ''),
            httpClient: $httpClient,
            tokenEndpoint: (string) ($oauth['lwa_token_endpoint'] ?? self::DEFAULT_TOKEN_URL),
        );
    }

    public function getAuthorizationUri(
        Marketplace $marketplace,
        string $state,
        bool $draftApp = true,
    ): string {
        $query = [
            'application_id' => $this->applicationId,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
        ];

        if ($draftApp) {
            $query['version'] = 'beta';
        }

        return $marketplace->sellerCentralUrl().'/apps/authorize/consent?'.http_build_query($query);
    }

    public function getRefreshToken(string $authorizationCode): string
    {
        $response = $this->httpClient->request('POST', $this->tokenEndpoint, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
            ],
            'form_params' => [
                'grant_type' => 'authorization_code',
                'code' => $authorizationCode,
                'redirect_uri' => $this->redirectUri,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);

        if (! is_array($data) || empty($data['refresh_token'])) {
            throw new OAuthException('Authorization code exchange did not return a refresh token.');
        }

        return $data['refresh_token'];
    }
}
