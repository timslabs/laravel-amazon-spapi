<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tims\AmazonSpApi\Enums\Marketplace;
use Tims\AmazonSpApi\Exceptions\OAuthException;
use Tims\AmazonSpApi\OAuth;

class OAuthTest extends TestCase
{
    public function test_authorization_uri_includes_required_query_params(): void
    {
        $oauth = OAuth::fromConfig();

        $uri = $oauth->getAuthorizationUri(Marketplace::US, 'abc123', draftApp: true);

        $this->assertStringStartsWith('https://sellercentral.amazon.com/apps/authorize/consent?', $uri);
        $this->assertStringContainsString('application_id=amzn1.sp.solution.example', $uri);
        $this->assertStringContainsString('redirect_uri='.urlencode('https://example.com/amazon/callback'), $uri);
        $this->assertStringContainsString('state=abc123', $uri);
        $this->assertStringContainsString('version=beta', $uri);
    }

    public function test_authorization_uri_omits_beta_when_not_draft(): void
    {
        $oauth = OAuth::fromConfig();

        $uri = $oauth->getAuthorizationUri(Marketplace::DE, 'state', draftApp: false);

        $this->assertStringStartsWith('https://sellercentral.amazon.de/apps/authorize/consent?', $uri);
        $this->assertStringNotContainsString('version=beta', $uri);
    }

    public function test_get_refresh_token_exchanges_authorization_code(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'access_token' => 'Atza|access',
                'refresh_token' => 'Atzr|refresh',
                'expires_in' => 3600,
                'token_type' => 'bearer',
            ])),
        ]);

        $oauth = new OAuth(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            redirectUri: 'https://example.com/amazon/callback',
            applicationId: 'amzn1.sp.solution.example',
            httpClient: new Client(['handler' => HandlerStack::create($mock)]),
        );

        $this->assertSame('Atzr|refresh', $oauth->getRefreshToken('auth-code'));
    }

    public function test_redirect_uri_must_be_https(): void
    {
        $this->expectException(OAuthException::class);

        new OAuth(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            redirectUri: 'http://example.com/callback',
            applicationId: 'amzn1.sp.solution.example',
        );
    }
}
