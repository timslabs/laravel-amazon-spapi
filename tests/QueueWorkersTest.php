<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Mockery;
use SpApi\Api\feeds\v2021_06_30\FeedsApi;
use SpApi\Api\reports\v2021_06_30\ReportsApi;
use SpApi\Model\feeds\v2021_06_30\CreateFeedDocumentResponse;
use SpApi\Model\feeds\v2021_06_30\CreateFeedDocumentSpecification;
use SpApi\Model\feeds\v2021_06_30\CreateFeedResponse;
use SpApi\Model\feeds\v2021_06_30\CreateFeedSpecification;
use SpApi\Model\feeds\v2021_06_30\Feed;
use SpApi\Model\reports\v2021_06_30\CreateReportResponse;
use SpApi\Model\reports\v2021_06_30\Report;
use SpApi\Model\reports\v2021_06_30\ReportDocument;
use Tims\AmazonSpApi\Document;
use Tims\AmazonSpApi\Events\FeedFailed;
use Tims\AmazonSpApi\Events\FeedResultReady;
use Tims\AmazonSpApi\Events\ReportDocumentReady;
use Tims\AmazonSpApi\Events\ReportFailed;
use Tims\AmazonSpApi\Jobs\PollFeedJob;
use Tims\AmazonSpApi\Jobs\PollReportJob;
use Tims\AmazonSpApi\Jobs\RequestReportJob;
use Tims\AmazonSpApi\Jobs\SubmitFeedJob;
use Tims\AmazonSpApi\SpApiManager;
use Tims\AmazonSpApi\Testing\SpApiFake;

class QueueWorkersTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_request_report_job_dispatches_poll_job(): void
    {
        Bus::fake([PollReportJob::class]);

        $fake = SpApiFake::start();
        $fake->mock(ReportsApi::class, function ($api) {
            $api->shouldReceive('createReport')
                ->once()
                ->andReturn(new CreateReportResponse(['report_id' => 'R123']));
        });

        (new RequestReportJob(
            reportType: 'GET_MERCHANT_LISTINGS_DATA',
            marketplaceIds: ['ATVPDKIKX0DER'],
        ))->handle();

        Bus::assertDispatched(PollReportJob::class, function (PollReportJob $job) {
            return $job->reportId === 'R123';
        });
    }

    public function test_poll_report_job_emits_ready_when_done(): void
    {
        Event::fake([ReportDocumentReady::class, ReportFailed::class]);

        $fake = SpApiFake::start();
        $fake->mock(ReportsApi::class, function ($api) {
            $api->shouldReceive('getReport')->once()->andReturn(new Report([
                'report_id' => 'R123',
                'report_type' => 'GET_MERCHANT_LISTINGS_DATA',
                'marketplace_ids' => ['ATVPDKIKX0DER'],
                'created_time' => new \DateTime,
                'processing_status' => Report::PROCESSING_STATUS_DONE,
                'report_document_id' => 'D123',
            ]));

            $api->shouldReceive('getReportDocument')->once()->andReturn(new ReportDocument([
                'report_document_id' => 'D123',
                'url' => 'https://example.com/report.tsv',
            ]));
        });

        $job = new class('R123') extends PollReportJob
        {
            public function handle(): void
            {
                /** @var ReportsApi $api */
                $api = $this->api(ReportsApi::class);
                $report = $api->getReport($this->reportId);
                $document = $api->getReportDocument($report->getReportDocumentId());

                $mock = new MockHandler([new Response(200, [], "sku\tqty\nA\t1\n")]);
                $contents = Document::fromReportDocument($document)
                    ->download(new Client(['handler' => HandlerStack::create($mock)]));

                Event::dispatch(new ReportDocumentReady(
                    reportId: $this->reportId,
                    reportDocumentId: (string) $report->getReportDocumentId(),
                    contents: $contents,
                    credentialsId: $this->credentialsId,
                ));
            }
        };

        $job->handle();

        Event::assertDispatched(ReportDocumentReady::class, function (ReportDocumentReady $event) {
            return $event->reportId === 'R123'
                && $event->reportDocumentId === 'D123'
                && str_contains($event->contents, 'sku');
        });
    }

    public function test_poll_report_job_emits_failed_on_fatal(): void
    {
        Event::fake([ReportDocumentReady::class, ReportFailed::class]);

        $fake = SpApiFake::start();
        $fake->mock(ReportsApi::class, function ($api) {
            $api->shouldReceive('getReport')->once()->andReturn(new Report([
                'report_id' => 'R123',
                'report_type' => 'GET_MERCHANT_LISTINGS_DATA',
                'marketplace_ids' => ['ATVPDKIKX0DER'],
                'created_time' => new \DateTime,
                'processing_status' => Report::PROCESSING_STATUS_FATAL,
            ]));
        });

        (new PollReportJob('R123'))->handle();

        Event::assertDispatched(ReportFailed::class);
        Event::assertNotDispatched(ReportDocumentReady::class);
    }

    public function test_submit_feed_job_dispatches_poll_job(): void
    {
        Bus::fake([PollFeedJob::class]);

        $fake = SpApiFake::start();
        $fake->mock(FeedsApi::class, function ($api) {
            $api->shouldReceive('createFeedDocument')->once()->andReturn(new CreateFeedDocumentResponse([
                'feed_document_id' => 'FD1',
                'url' => 'https://example.com/upload',
            ]));
            $api->shouldReceive('createFeed')->once()->andReturn(new CreateFeedResponse([
                'feed_id' => 'F1',
            ]));
        });

        $job = new class(feedType: 'POST_PRODUCT_PRICING_DATA', marketplaceIds: ['ATVPDKIKX0DER'], contents: '<AmazonEnvelope/>') extends SubmitFeedJob
        {
            public function handle(): void
            {
                $contentType = $this->contentType ?? Document::contentTypeForFeed($this->feedType);
                /** @var FeedsApi $api */
                $api = $this->api(FeedsApi::class);
                $created = $api->createFeedDocument(new CreateFeedDocumentSpecification([
                    'content_type' => $contentType,
                ]));

                $mock = new MockHandler([new Response(200, [], '')]);
                Document::fromCreateFeedDocumentResponse($created)->upload(
                    (string) $this->contents,
                    $contentType,
                    new Client(['handler' => HandlerStack::create($mock)]),
                );

                $feed = $api->createFeed(new CreateFeedSpecification([
                    'feed_type' => $this->feedType,
                    'marketplace_ids' => $this->marketplaceIds,
                    'input_feed_document_id' => $created->getFeedDocumentId(),
                ]));

                PollFeedJob::dispatch(feedId: $feed->getFeedId(), credentialsId: $this->credentialsId);
            }
        };

        $job->handle();

        Bus::assertDispatched(PollFeedJob::class, fn (PollFeedJob $j) => $j->feedId === 'F1');
    }

    public function test_poll_feed_job_emits_failed_on_cancelled(): void
    {
        Event::fake([FeedResultReady::class, FeedFailed::class]);

        $fake = SpApiFake::start();
        $fake->mock(FeedsApi::class, function ($api) {
            $api->shouldReceive('getFeed')->once()->andReturn(new Feed([
                'feed_id' => 'F1',
                'feed_type' => 'POST_PRODUCT_PRICING_DATA',
                'created_time' => new \DateTime,
                'processing_status' => Feed::PROCESSING_STATUS_CANCELLED,
            ]));
        });

        (new PollFeedJob('F1'))->handle();

        Event::assertDispatched(FeedFailed::class);
        Event::assertNotDispatched(FeedResultReady::class);
    }

    public function test_sp_api_fake_requires_registered_mock(): void
    {
        SpApiFake::start();

        $this->expectException(\InvalidArgumentException::class);
        app(SpApiManager::class)->make(ReportsApi::class);
    }
}
