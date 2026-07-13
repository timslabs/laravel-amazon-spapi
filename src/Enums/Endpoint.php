<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Enums;

enum Endpoint: string
{
    case NA = 'https://sellingpartnerapi-na.amazon.com';
    case NA_SANDBOX = 'https://sandbox.sellingpartnerapi-na.amazon.com';
    case EU = 'https://sellingpartnerapi-eu.amazon.com';
    case EU_SANDBOX = 'https://sandbox.sellingpartnerapi-eu.amazon.com';
    case FE = 'https://sellingpartnerapi-fe.amazon.com';
    case FE_SANDBOX = 'https://sandbox.sellingpartnerapi-fe.amazon.com';

    public function host(): string
    {
        return $this->value;
    }

    public function isSandbox(): bool
    {
        return in_array($this, [
            self::NA_SANDBOX,
            self::EU_SANDBOX,
            self::FE_SANDBOX,
        ], true);
    }

    public function region(): Region
    {
        return match ($this) {
            self::NA, self::NA_SANDBOX => Region::NA,
            self::EU, self::EU_SANDBOX => Region::EU,
            self::FE, self::FE_SANDBOX => Region::FE,
        };
    }

    public static function byRegion(string|Region $region, bool $sandbox = false): self
    {
        if (is_string($region)) {
            $region = Region::from(strtoupper($region));
        }

        return match ($region) {
            Region::NA => $sandbox ? self::NA_SANDBOX : self::NA,
            Region::EU => $sandbox ? self::EU_SANDBOX : self::EU,
            Region::FE => $sandbox ? self::FE_SANDBOX : self::FE,
        };
    }

    public static function byMarketplace(Marketplace $marketplace, bool $sandbox = false): self
    {
        return self::byRegion($marketplace->region(), $sandbox);
    }
}
