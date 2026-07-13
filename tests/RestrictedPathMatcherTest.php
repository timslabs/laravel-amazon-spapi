<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use Tims\AmazonSpApi\Support\RestrictedPathMatcher;

class RestrictedPathMatcherTest extends TestCase
{
    public function test_matches_orders_paths_that_support_data_elements(): void
    {
        $list = RestrictedPathMatcher::match('GET', '/orders/v0/orders');
        $this->assertNotNull($list);
        $this->assertTrue($list['supportsDataElements']);

        $order = RestrictedPathMatcher::match('GET', '/orders/v0/orders/123-1234567-1234567');
        $this->assertNotNull($order);
        $this->assertTrue($order['supportsDataElements']);
    }

    public function test_matches_order_subresources_without_data_elements(): void
    {
        $address = RestrictedPathMatcher::match('GET', '/orders/v0/orders/123/address');
        $this->assertNotNull($address);
        $this->assertFalse($address['supportsDataElements']);
    }

    public function test_ignores_tokens_api_and_unrestricted_paths(): void
    {
        $this->assertNull(RestrictedPathMatcher::match('POST', '/tokens/2021-03-01/restrictedDataToken'));
        $this->assertNull(RestrictedPathMatcher::match('GET', '/sellers/v1/marketplaceParticipations'));
    }

    public function test_matches_report_document_path(): void
    {
        $match = RestrictedPathMatcher::match('GET', '/reports/2021-06-30/documents/amzn1.spdoc.example');
        $this->assertNotNull($match);
        $this->assertFalse($match['supportsDataElements']);
    }
}
