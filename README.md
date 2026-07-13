# laravel-amazon-spapi

Laravel integration for Amazon’s official Selling Partner API PHP SDK (`amzn-spapi/sdk`).

## Why this package?

`amzn-spapi/sdk` is the API client. This package adds the Laravel layer around it:

- Config and environment-based LWA credentials
- Single-seller and multi-seller credential storage
- Access-token and Restricted Data Token (RDT) caching
- Automatic RDT attachment for PII endpoints
- OAuth helpers for Seller Central authorization
- Grantless client helpers (Notifications, client-secret rotation)
- Feed and report document upload/download
- HTTP retries for 429 and transient 5xx responses
- Queue jobs for report/feed create → poll → download
- Test fakes via `SpApiFake`

You continue to call official `SpApi\Api\...` classes; this package configures and supports them in Laravel.

## Requirements

- PHP 8.3+
- Laravel 11 or 12

## Installation

```bash
composer require tims/laravel-amazon-spapi
```

Publish the config:

```bash
php artisan vendor:publish --tag=amazon-spapi-config
```

## Configuration

Add these to your `.env`:

```env
AMAZON_SPAPI_INSTALLATION_TYPE=single

AMAZON_SPAPI_LWA_CLIENT_ID=
AMAZON_SPAPI_LWA_CLIENT_SECRET=
AMAZON_SPAPI_LWA_REFRESH_TOKEN=

# NA, EU, or FE
AMAZON_SPAPI_ENDPOINT_REGION=NA

# Optional
AMAZON_SPAPI_SANDBOX=false
AMAZON_SPAPI_APPLICATION_ID=
AMAZON_SPAPI_REDIRECT_URI=https://your-app.test/amazon/callback

# Automatic RDT (enabled by default)
AMAZON_SPAPI_RDT_AUTO=true
AMAZON_SPAPI_RDT_DATA_ELEMENTS=buyerInfo,shippingAddress

# Retries for 429 / 5xx (enabled by default)
AMAZON_SPAPI_RETRY_ENABLED=true
AMAZON_SPAPI_RETRY_MAX_ATTEMPTS=3
AMAZON_SPAPI_RETRY_BASE_DELAY_MS=500
```

## Single-seller mode

Type-hint `SpApiManager` or use the facade to build official SDK API clients:

```php
use SpApi\Api\sellers\v1\SellersApi;
use Tims\AmazonSpApi\Facades\AmazonSpApi;
use Tims\AmazonSpApi\SpApiManager;

public function index(SpApiManager $spApi)
{
    /** @var SellersApi $api */
    $api = $spApi->make(SellersApi::class);
    $result = $api->getMarketplaceParticipations();

    return response()->json($result);
}

// Or via facade:
$api = AmazonSpApi::make(SellersApi::class);
```

In single-seller mode you can also resolve `\SpApi\Configuration` from the container.

## Multi-seller mode

1. Set `AMAZON_SPAPI_INSTALLATION_TYPE=multi` (or `installation_type` in config).
2. Publish and run migrations:

```bash
php artisan vendor:publish --tag=amazon-spapi-multi-seller
php artisan migrate
```

3. Store sellers and credentials:

```php
use Tims\AmazonSpApi\Models\Credentials;
use Tims\AmazonSpApi\Models\Seller;

$seller = Seller::create(['name' => 'My Seller']);

$credentials = Credentials::create([
    'seller_id' => $seller->id,
    'selling_partner_id' => 'A********',
    'region' => 'NA',
    // Optional when using shared app credentials from .env
    'client_id' => null,
    'client_secret' => null,
    'refresh_token' => 'Atzr|...',
]);
```

4. Build API clients from a credentials row:

```php
use SpApi\Api\sellers\v1\SellersApi;

$api = $credentials->make(SellersApi::class);
$result = $api->getMarketplaceParticipations();
```

`client_id` / `client_secret` fall back to the single-seller LWA env values when left null (shared SP-API application). Refresh tokens and client secrets are stored encrypted.

## OAuth

Build Seller Central authorize URLs and exchange authorization codes for refresh tokens:

```php
use Tims\AmazonSpApi\Enums\Marketplace;
use Tims\AmazonSpApi\OAuth;

$oauth = OAuth::fromConfig();

$authorizeUrl = $oauth->getAuthorizationUri(
    marketplace: Marketplace::US,
    state: $state,
    draftApp: true,
);

// After Amazon redirects back with spapi_oauth_code:
$refreshToken = $oauth->getRefreshToken($request->query('spapi_oauth_code'));
```

## Restricted Data Tokens (RDT)

Configure `dataElements` once. With automatic RDT enabled, restricted calls do not need a manual `restrictedDataToken` argument:

```php
use SpApi\Api\orders\v0\OrdersV0Api;
use Tims\AmazonSpApi\Facades\AmazonSpApi;

$ordersApi = AmazonSpApi::make(OrdersV0Api::class, options: [
    'data_elements' => ['buyerInfo', 'shippingAddress'],
]);

$result = $ordersApi->getOrders(
    ['ATVPDKIKX0DER'],
    '2024-01-01T00:00:00Z',
);
```

Or use `.env` / config defaults:

```env
AMAZON_SPAPI_RDT_AUTO=true
AMAZON_SPAPI_RDT_DATA_ELEMENTS=buyerInfo,shippingAddress
# AMAZON_SPAPI_RDT_TARGET_APPLICATION=
AMAZON_SPAPI_RDT_SKIP_IN_SANDBOX=true
```

```php
$ordersApi = AmazonSpApi::make(OrdersV0Api::class);
$ordersApi->getOrder($orderId);
```

Disable automatic RDT when you want to pass tokens yourself:

```php
AmazonSpApi::make(OrdersV0Api::class, options: ['auto_rdt' => false]);
```

### Manual RDT

```php
$rdt = AmazonSpApi::restrictedDataToken(
    path: '/orders/v0/orders',
    dataElements: ['buyerInfo', 'shippingAddress'],
);

$ordersApi = AmazonSpApi::make(OrdersV0Api::class, options: ['auto_rdt' => false]);
$result = $ordersApi->getOrders(
    ['ATVPDKIKX0DER'],
    '2024-01-01T00:00:00Z',
    restrictedDataToken: $rdt,
);
```

`AmazonSpApi::isRestrictedOperation('OrdersV0Api-getOrder')` uses the official SDK restricted-operations list.

In sandbox mode, automatic RDT is skipped (`skip_in_sandbox`). Restricted sandbox calls work without an RDT.

## Grantless operations

Some Notifications and Application Management calls do not use a seller refresh token. They use app client id/secret plus an LWA scope:

```php
use SpApi\Api\notifications\v1\NotificationsApi;
use Tims\AmazonSpApi\Enums\GrantlessScope;
use Tims\AmazonSpApi\Facades\AmazonSpApi;

$api = AmazonSpApi::makeGrantless(
    NotificationsApi::class,
    GrantlessScope::Notifications, // or ClientCredentialRotation
);

$destinations = $api->getDestinations();
```

## Feed / report documents

After `createFeedDocument` / `getReportDocument` / `getFeedDocument`, use `Document` to upload or download (including GZIP):

```php
use SpApi\Model\feeds\v2021_06_30\CreateFeedDocumentSpecification;
use Tims\AmazonSpApi\Document;

// Report download
$reportDoc = $reportsApi->getReportDocument($documentId);
$contents = Document::fromReportDocument($reportDoc)->download();
$rows = Document::fromReportDocument($reportDoc)->downloadParsed('tsv');

// Feed upload
$contentType = Document::contentTypeForFeed('POST_PRODUCT_PRICING_DATA');
$created = $feedsApi->createFeedDocument(new CreateFeedDocumentSpecification(['content_type' => $contentType]));
$doc = Document::fromCreateFeedDocumentResponse($created);
$doc->upload($xml, $contentType);
```

## Retries

SP-API applies rate limits. Clients from `make()` / `makeGrantless()` retry `429`, selected `5xx` responses, and connection errors by default, with exponential backoff and support for `Retry-After`.

```env
AMAZON_SPAPI_RETRY_ENABLED=true
AMAZON_SPAPI_RETRY_MAX_ATTEMPTS=3
AMAZON_SPAPI_RETRY_BASE_DELAY_MS=500
```

Disable per client:

```php
AmazonSpApi::make(OrdersV0Api::class, options: ['retry' => false]);
```

## Queued reports & feeds

Report and feed flows: create → poll → download → event.

```php
use Tims\AmazonSpApi\Events\ReportDocumentReady;
use Tims\AmazonSpApi\Jobs\RequestReportJob;

RequestReportJob::dispatch(
    reportType: 'GET_MERCHANT_LISTINGS_DATA',
    marketplaceIds: ['ATVPDKIKX0DER'],
    // credentialsId: $credentials->id, // multi-seller
);

Event::listen(ReportDocumentReady::class, function (ReportDocumentReady $event) {
    Storage::put("reports/{$event->reportId}.tsv", $event->contents);
});
```

Feeds:

```php
use Tims\AmazonSpApi\Jobs\SubmitFeedJob;

SubmitFeedJob::dispatch(
    feedType: 'POST_PRODUCT_PRICING_DATA',
    marketplaceIds: ['ATVPDKIKX0DER'],
    contents: $xml,           // or contentsPath: storage_path('feeds/price.xml')
);
```

Events: `ReportDocumentReady`, `ReportFailed`, `FeedResultReady`, `FeedFailed`.

Poll settings: `AMAZON_SPAPI_REPORT_POLL_SECONDS`, `AMAZON_SPAPI_FEED_POLL_SECONDS`, `AMAZON_SPAPI_MAX_POLL_ATTEMPTS`.

## Testing fakes

In tests, swap `SpApiManager` so `make()` returns Mockery doubles:

```php
use SpApi\Api\reports\v2021_06_30\ReportsApi;
use Tims\AmazonSpApi\Testing\SpApiFake;

$fake = SpApiFake::start();
$fake->mock(ReportsApi::class, function ($api) {
    $api->shouldReceive('getReport')->andReturn($report);
});

$api = AmazonSpApi::make(ReportsApi::class);
```

## Access token caching

LWA access tokens and RDTs are stored in Laravel’s cache. Updating a `Credentials` row clears that seller’s cached tokens when the cache driver supports tags (for example Redis).

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

Copyright (c) 2026 TIMS.
