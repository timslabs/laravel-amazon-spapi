<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Events;

class ReportDocumentReady
{
    public function __construct(
        public readonly string $reportId,
        public readonly string $reportDocumentId,
        public readonly string $contents,
        public readonly ?int $credentialsId = null,
    ) {}
}
