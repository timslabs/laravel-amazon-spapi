<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use SpApi\Api\reports\v2021_06_30\ReportsApi;
use SpApi\Model\reports\v2021_06_30\Report;
use Tims\AmazonSpApi\Document;
use Tims\AmazonSpApi\Events\ReportDocumentReady;
use Tims\AmazonSpApi\Events\ReportFailed;
use Tims\AmazonSpApi\Support\ResolvesSpApiClient;

class PollReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ResolvesSpApiClient;
    use SerializesModels;

    public int $tries;

    public function __construct(
        public string $reportId,
        public ?int $credentialsId = null,
    ) {
        $this->tries = (int) config('amazon-spapi.queue.max_poll_attempts', 60);

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
        $report = $api->getReport($this->reportId);
        $status = $report->getProcessingStatus();

        if (in_array($status, [Report::PROCESSING_STATUS_IN_QUEUE, Report::PROCESSING_STATUS_IN_PROGRESS], true)) {
            $delay = (int) config('amazon-spapi.queue.report_poll_seconds', 60);
            $this->release($delay);

            return;
        }

        if ($status !== Report::PROCESSING_STATUS_DONE) {
            Event::dispatch(new ReportFailed(
                reportId: $this->reportId,
                status: $status,
                credentialsId: $this->credentialsId,
                message: "Report ended with status [{$status}].",
            ));

            return;
        }

        $documentId = $report->getReportDocumentId();
        if (! $documentId) {
            Event::dispatch(new ReportFailed(
                reportId: $this->reportId,
                status: $status,
                credentialsId: $this->credentialsId,
                message: 'Report is DONE but reportDocumentId is missing.',
            ));

            return;
        }

        $document = $api->getReportDocument($documentId);
        $contents = Document::fromReportDocument($document)->download();

        Event::dispatch(new ReportDocumentReady(
            reportId: $this->reportId,
            reportDocumentId: $documentId,
            contents: $contents,
            credentialsId: $this->credentialsId,
        ));
    }
}
