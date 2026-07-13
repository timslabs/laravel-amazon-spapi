<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Support;

/**
 * Matches SP-API request paths that require Restricted Data Tokens.
 *
 * @phpstan-type MatchResult array{path: string, supportsDataElements: bool}
 */
class RestrictedPathMatcher
{
    /**
     * @var list<array{method: string, pattern: string, supportsDataElements: bool}>
     */
    private const PATTERNS = [
        // Orders API
        ['method' => 'GET', 'pattern' => '#^/orders/v0/orders$#', 'supportsDataElements' => true],
        ['method' => 'GET', 'pattern' => '#^/orders/v0/orders/[^/]+$#', 'supportsDataElements' => true],
        ['method' => 'GET', 'pattern' => '#^/orders/v0/orders/[^/]+/address$#', 'supportsDataElements' => false],
        ['method' => 'GET', 'pattern' => '#^/orders/v0/orders/[^/]+/buyerInfo$#', 'supportsDataElements' => false],
        ['method' => 'GET', 'pattern' => '#^/orders/v0/orders/[^/]+/orderItems$#', 'supportsDataElements' => true],
        ['method' => 'GET', 'pattern' => '#^/orders/v0/orders/[^/]+/orderItems/buyerInfo$#', 'supportsDataElements' => false],
        ['method' => 'GET', 'pattern' => '#^/orders/v0/orders/[^/]+/regulatedInfo$#', 'supportsDataElements' => false],

        // Reports API
        ['method' => 'GET', 'pattern' => '#^/reports/2021-06-30/documents/[^/]+$#', 'supportsDataElements' => false],

        // Merchant Fulfillment API
        ['method' => 'GET', 'pattern' => '#^/mfn/v0/shipments/[^/]+$#', 'supportsDataElements' => false],
        ['method' => 'DELETE', 'pattern' => '#^/mfn/v0/shipments/[^/]+$#', 'supportsDataElements' => false],
        ['method' => 'POST', 'pattern' => '#^/mfn/v0/shipments$#', 'supportsDataElements' => false],

        // Easy Ship
        ['method' => 'POST', 'pattern' => '#^/easyShip/2022-03-23/packages/bulk$#', 'supportsDataElements' => false],

        // Shipment Invoicing
        ['method' => 'GET', 'pattern' => '#^/fba/outbound/brazil/v0/shipments/[^/]+$#', 'supportsDataElements' => false],

        // Vendor Direct Fulfillment Orders
        ['method' => 'GET', 'pattern' => '#^/vendor/directFulfillment/orders/v1/purchaseOrders$#', 'supportsDataElements' => false],
        ['method' => 'GET', 'pattern' => '#^/vendor/directFulfillment/orders/v1/purchaseOrders/[^/]+$#', 'supportsDataElements' => false],

        // Vendor Direct Fulfillment Shipping
        ['method' => 'GET', 'pattern' => '#^/vendor/directFulfillment/shipping/v1/shippingLabels$#', 'supportsDataElements' => false],
        ['method' => 'GET', 'pattern' => '#^/vendor/directFulfillment/shipping/v1/shippingLabels/[^/]+$#', 'supportsDataElements' => false],
        ['method' => 'POST', 'pattern' => '#^/vendor/directFulfillment/shipping/v1/shippingLabels/[^/]+$#', 'supportsDataElements' => false],
        ['method' => 'GET', 'pattern' => '#^/vendor/directFulfillment/shipping/v1/packingSlips$#', 'supportsDataElements' => false],
        ['method' => 'GET', 'pattern' => '#^/vendor/directFulfillment/shipping/v1/packingSlips/[^/]+$#', 'supportsDataElements' => false],
        ['method' => 'GET', 'pattern' => '#^/vendor/directFulfillment/shipping/v1/customerInvoices$#', 'supportsDataElements' => false],
        ['method' => 'GET', 'pattern' => '#^/vendor/directFulfillment/shipping/v1/customerInvoices/[^/]+$#', 'supportsDataElements' => false],
    ];

    /**
     * @return MatchResult|null
     */
    public static function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        $path = '/'.ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');

        if (str_starts_with($path, '/tokens/')) {
            return null;
        }

        foreach (self::PATTERNS as $rule) {
            if ($rule['method'] !== $method) {
                continue;
            }

            if (preg_match($rule['pattern'], $path) === 1) {
                return [
                    'path' => $path,
                    'supportsDataElements' => $rule['supportsDataElements'],
                ];
            }
        }

        return null;
    }
}
