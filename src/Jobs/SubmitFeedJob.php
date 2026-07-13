<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SpApi\Api\feeds\v2021_06_30\FeedsApi;
use SpApi\Model\feeds\v2021_06_30\CreateFeedDocumentSpecification;
use SpApi\Model\feeds\v2021_06_30\CreateFeedSpecification;
use Tims\AmazonSpApi\Document;
use Tims\AmazonSpApi\Exceptions\DocumentException;
use Tims\AmazonSpApi\Support\ResolvesSpApiClient;

class SubmitFeedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ResolvesSpApiClient;
    use SerializesModels;

    /**
     * @param  list<string>  $marketplaceIds
     * @param  array<string, string>|null  $feedOptions
     */
    public function __construct(
        public string $feedType,
        public array $marketplaceIds,
        public ?string $contents = null,
        public ?string $contentsPath = null,
        public ?string $contentType = null,
        public ?array $feedOptions = null,
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
        $payload = $this->resolveContents();
        $contentType = $this->contentType ?? Document::contentTypeForFeed($this->feedType);

        /** @var FeedsApi $api */
        $api = $this->api(FeedsApi::class);

        $created = $api->createFeedDocument(new CreateFeedDocumentSpecification([
            'content_type' => $contentType,
        ]));

        Document::fromCreateFeedDocumentResponse($created)->upload($payload, $contentType);

        $feed = $api->createFeed(new CreateFeedSpecification([
            'feed_type' => $this->feedType,
            'marketplace_ids' => $this->marketplaceIds,
            'input_feed_document_id' => $created->getFeedDocumentId(),
            'feed_options' => $this->feedOptions,
        ]));

        PollFeedJob::dispatch(
            feedId: $feed->getFeedId(),
            credentialsId: $this->credentialsId,
        );
    }

    private function resolveContents(): string
    {
        if ($this->contents !== null) {
            return $this->contents;
        }

        if ($this->contentsPath !== null) {
            if (! is_file($this->contentsPath)) {
                throw new DocumentException("Feed contents file not found: {$this->contentsPath}");
            }

            $contents = file_get_contents($this->contentsPath);
            if ($contents === false) {
                throw new DocumentException("Unable to read feed contents: {$this->contentsPath}");
            }

            return $contents;
        }

        throw new DocumentException('SubmitFeedJob requires contents or contentsPath.');
    }
}
