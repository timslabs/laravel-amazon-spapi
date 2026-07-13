<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Jobs;

use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SpApi\Api\reports\v2021_06_30\ReportsApi;
use SpApi\Model\reports\v2021_06_30\CreateReportSpecification;
use Tims\AmazonSpApi\Support\ResolvesSpApiClient;

class RequestReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ResolvesSpApiClient;
    use SerializesModels;

    /**
     * @param  list<string>  $marketplaceIds
     * @param  array<string, string>|null  $reportOptions
     */
    public function __construct(
        public string $reportType,
        public array $marketplaceIds,
        public ?DateTimeInterface $dataStartTime = null,
        public ?DateTimeInterface $dataEndTime = null,
        public ?array $reportOptions = null,
        public ?int $credentialsId = null,
    ) {
        $queue = $this->spApiQueue();
        if ($queue['connection']) {
            $this->onConnection($queue['connection']);
        }
        if ($queue['queue']) {
            $this->onQueue($queue['queue']);
        }
    }

    public function handle(): void
    {
        /** @var ReportsApi $api */
        $api = $this->api(ReportsApi::class);

        $body = new CreateReportSpecification([
            'report_type' => $this->reportType,
            'marketplace_ids' => $this->marketplaceIds,
            'data_start_time' => $this->dataStartTime,
            'data_end_time' => $this->dataEndTime,
            'report_options' => $this->reportOptions,
        ]);

        $response = $api->createReport($body);

        PollReportJob::dispatch(
            reportId: $response->getReportId(),
            credentialsId: $this->credentialsId,
        );
    }
}
