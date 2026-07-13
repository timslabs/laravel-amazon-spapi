<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Enums;

/**
 * LWA scopes for grantless SP-API operations.
 *
 * @see https://developer-docs.amazon.com/sp-api/docs/grantless-operations
 */
enum GrantlessScope: string
{
    case Notifications = 'sellingpartnerapi::notifications';
    case ClientCredentialRotation = 'sellingpartnerapi::client_credential:rotation';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
