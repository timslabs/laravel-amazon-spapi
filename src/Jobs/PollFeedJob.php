<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use SpApi\Api\feeds\v2021_06_30\FeedsApi;
use SpApi\Model\feeds\v2021_06_30\Feed;
use Tims\AmazonSpApi\Document;
use Tims\AmazonSpApi\Events\FeedFailed;
use Tims\AmazonSpApi\Events\FeedResultReady;
use Tims\AmazonSpApi\Support\ResolvesSpApiClient;

class PollFeedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ResolvesSpApiClient;
    use SerializesModels;

    public int $tries;

    public function __construct(
        public string $feedId,
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
        /** @var FeedsApi $api */
        $api = $this->api(FeedsApi::class);
        $feed = $api->getFeed($this->feedId);
        $status = $feed->getProcessingStatus();

        if (in_array($status, [Feed::PROCESSING_STATUS_IN_QUEUE, Feed::PROCESSING_STATUS_IN_PROGRESS], true)) {
            $delay = (int) config('amazon-spapi.queue.feed_poll_seconds', 60);
            $this->release($delay);

            return;
        }

        if ($status !== Feed::PROCESSING_STATUS_DONE) {
            Event::dispatch(new FeedFailed(
                feedId: $this->feedId,
                status: $status,
                credentialsId: $this->credentialsId,
                message: "Feed ended with status [{$status}].",
            ));

            return;
        }

        $documentId = $feed->getResultFeedDocumentId();
        if (! $documentId) {
            Event::dispatch(new FeedFailed(
                feedId: $this->feedId,
                status: $status,
                credentialsId: $this->credentialsId,
                message: 'Feed is DONE but resultFeedDocumentId is missing.',
            ));

            return;
        }

        $document = $api->getFeedDocument($documentId);
        $contents = Document::fromFeedDocument($document)->download();

        Event::dispatch(new FeedResultReady(
            feedId: $this->feedId,
            resultFeedDocumentId: $documentId,
            contents: $contents,
            credentialsId: $this->credentialsId,
        ));
    }
}
