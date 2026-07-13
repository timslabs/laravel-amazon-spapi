<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Enums;

enum Region: string
{
    case NA = 'NA';
    case EU = 'EU';
    case FE = 'FE';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
