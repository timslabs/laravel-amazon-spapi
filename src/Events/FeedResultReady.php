<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Events;

class FeedResultReady
{
    public function __construct(
        public readonly string $feedId,
        public readonly string $resultFeedDocumentId,
        public readonly string $contents,
        public readonly ?int $credentialsId = null,
    ) {}
}
