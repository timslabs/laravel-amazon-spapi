<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Events;

class ReportFailed
{
    public function __construct(
        public readonly string $reportId,
        public readonly string $status,
        public readonly ?int $credentialsId = null,
        public readonly ?string $message = null,
    ) {}
}
