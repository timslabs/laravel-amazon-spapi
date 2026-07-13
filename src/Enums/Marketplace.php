<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Enums;

enum Marketplace: string
{
    // North America
    case CA = 'A2EUQ1WTGCTBG2';
    case US = 'ATVPDKIKX0DER';
    case MX = 'A1AM78C64UM0Y8';
    case BR = 'A2Q3Y263D00KWC';

    // Europe
    case IE = 'A28R8C7NBKEWEA';
    case ES = 'A1RKKUPIHCS9HS';
    case GB = 'A1F83G8C2ARO7P';
    case FR = 'A13V1IB3VIYZZH';
    case BE = 'AMEN7PMS3EDWL';
    case NL = 'A1805IZSGTT6HS';
    case DE = 'A1PA6795UKMFR9';
    case IT = 'APJ6JRAAMBUM9';
    case SE = 'A2NODRKZP88ZB9';
    case ZA = 'AE08WJ6YKNBMC';
    case PL = 'A1C3SOZRARQ6R3';
    case EG = 'ARBP9OOSHTCHU';
    case TR = 'A33AVAJ2PDY3EV';
    case SA = 'A17E79C6D8DWNP';
    case AE = 'A2VIGQ35RCS4UG';
    case IN = 'A21TJRUUN4KGV';

    // Far East
    case SG = 'A19VAU5U5O7RUS';
    case AU = 'A39IBJ37TRP1C6';
    case JP = 'A1VC38T7YXB528';

    public function region(): Region
    {
        return match ($this) {
            self::CA, self::US, self::MX, self::BR => Region::NA,
            self::SG, self::AU, self::JP => Region::FE,
            default => Region::EU,
        };
    }

    public function sellerCentralUrl(): string
    {
        return match ($this) {
            self::BR => 'https://sellercentral.amazon.com.br',
            self::CA => 'https://sellercentral.amazon.ca',
            self::MX => 'https://sellercentral.amazon.com.mx',
            self::US => 'https://sellercentral.amazon.com',

            self::AE => 'https://sellercentral.amazon.ae',
            self::BE => 'https://sellercentral.amazon.com.be',
            self::DE => 'https://sellercentral.amazon.de',
            self::EG => 'https://sellercentral.amazon.eg',
            self::ES => 'https://sellercentral.amazon.es',
            self::FR => 'https://sellercentral.amazon.fr',
            self::GB => 'https://sellercentral.amazon.co.uk',
            self::IN => 'https://sellercentral.amazon.in',
            self::IT => 'https://sellercentral.amazon.it',
            self::NL => 'https://sellercentral.amazon.nl',
            self::PL => 'https://sellercentral.amazon.pl',
            self::SA => 'https://sellercentral.amazon.sa',
            self::SE => 'https://sellercentral.amazon.se',
            self::TR => 'https://sellercentral.amazon.com.tr',
            self::ZA => 'https://sellercentral.amazon.co.za',
            self::IE => 'https://sellercentral.amazon.ie',

            self::AU => 'https://sellercentral.amazon.com.au',
            self::JP => 'https://sellercentral.amazon.co.jp',
            self::SG => 'https://sellercentral.amazon.sg',
        };
    }

    public static function fromCountryCode(string $countryCode): self
    {
        $countryCode = strtoupper($countryCode);

        if ($countryCode === 'UK') {
            return self::GB;
        }

        return self::{$countryCode};
    }
}
