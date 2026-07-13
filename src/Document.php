<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use SimpleXMLElement;
use SpApi\Model\feeds\v2021_06_30\CreateFeedDocumentResponse;
use SpApi\Model\feeds\v2021_06_30\FeedDocument;
use SpApi\Model\reports\v2021_06_30\ReportDocument;
use Tims\AmazonSpApi\Exceptions\DocumentException;

/**
 * Download / upload helpers for Feeds and Reports document URLs.
 */
class Document
{
    public function __construct(
        private readonly string $url,
        private readonly ?string $compressionAlgorithm = null,
        private readonly ?string $documentId = null,
    ) {}

    public static function fromReportDocument(ReportDocument $document): self
    {
        return new self(
            url: (string) $document->getUrl(),
            compressionAlgorithm: $document->getCompressionAlgorithm(),
            documentId: $document->getReportDocumentId(),
        );
    }

    public static function fromFeedDocument(FeedDocument $document): self
    {
        return new self(
            url: (string) $document->getUrl(),
            compressionAlgorithm: $document->getCompressionAlgorithm(),
            documentId: $document->getFeedDocumentId(),
        );
    }

    public static function fromCreateFeedDocumentResponse(CreateFeedDocumentResponse $document): self
    {
        return new self(
            url: (string) $document->getUrl(),
            compressionAlgorithm: null,
            documentId: $document->getFeedDocumentId(),
        );
    }

    public function url(): string
    {
        return $this->url;
    }

    public function documentId(): ?string
    {
        return $this->documentId;
    }

    /**
     * Download and optionally gunzip the document body as a string.
     */
    public function download(?ClientInterface $client = null): string
    {
        $client ??= new Client;
        $response = $client->request('GET', $this->url, ['stream' => true]);
        $contents = (string) $response->getBody();

        if ($this->isGzip()) {
            $decoded = gzdecode($contents);
            if ($decoded === false) {
                throw new DocumentException('Failed to gunzip document contents.');
            }

            return $decoded;
        }

        return $contents;
    }

    /**
     * Download as a PSR stream (gunzipped when compressionAlgorithm is GZIP).
     */
    public function downloadStream(?ClientInterface $client = null): StreamInterface
    {
        return Utils::streamFor($this->download($client));
    }

    /**
     * Download and parse common report formats.
     *
     * @return array<int, array<int|string, string|null>>|array<string, mixed>|SimpleXMLElement|string
     */
    public function downloadParsed(
        string $format = 'auto',
        ?ClientInterface $client = null,
    ): array|SimpleXMLElement|string {
        $contents = $this->download($client);
        $format = strtolower($format);

        if ($format === 'auto') {
            $format = $this->detectFormat($contents);
        }

        return match ($format) {
            'json' => $this->parseJson($contents),
            'xml' => $this->parseXml($contents),
            'tsv', 'csv' => $this->parseDelimited($contents, $format === 'csv' ? ',' : "\t"),
            'raw', 'txt', 'pdf' => $contents,
            default => throw new DocumentException("Unsupported document format [{$format}]."),
        };
    }

    /**
     * PUT feed contents to a createFeedDocument upload URL.
     *
     * @param  string|resource|StreamInterface  $contents
     */
    public function upload(
        mixed $contents,
        string $contentType,
        ?ClientInterface $client = null,
    ): void {
        $client ??= new Client;

        $response = $client->request('PUT', $this->url, [
            'headers' => [
                'Content-Type' => $contentType,
            ],
            'body' => $contents,
            'http_errors' => false,
        ]);

        $status = $response->getStatusCode();
        if ($status >= 300) {
            throw new DocumentException(
                "Document upload failed ({$status}): {$response->getBody()}"
            );
        }
    }

    /**
     * Common feed content types (pass explicit type when not listed).
     */
    public static function contentTypeForFeed(string $feedType, string $charset = 'UTF-8'): string
    {
        $feedType = strtoupper($feedType);

        $map = [
            'JSON_LISTINGS_FEED' => 'application/json',
            'POST_PRODUCT_DATA' => 'text/xml',
            'POST_INVENTORY_AVAILABILITY_DATA' => 'text/xml',
            'POST_PRODUCT_PRICING_DATA' => 'text/xml',
            'POST_PRODUCT_IMAGE_DATA' => 'text/xml',
            'POST_PRODUCT_RELATIONSHIP_DATA' => 'text/xml',
            'POST_ORDER_ACKNOWLEDGEMENT_DATA' => 'text/xml',
            'POST_ORDER_FULFILLMENT_DATA' => 'text/xml',
            'POST_PAYMENT_ADJUSTMENT_DATA' => 'text/xml',
            'POST_FLAT_FILE_INVLOADER_DATA' => 'text/tab-separated-values',
            'POST_FLAT_FILE_LISTINGS_DATA' => 'text/tab-separated-values',
            'POST_FLAT_FILE_ORDER_ACKNOWLEDGEMENT_DATA' => 'text/tab-separated-values',
            'POST_FLAT_FILE_FULFILLMENT_DATA' => 'text/tab-separated-values',
        ];

        $base = $map[$feedType] ?? null;
        if ($base === null) {
            throw new DocumentException(
                "Unknown feed type [{$feedType}]. Pass an explicit Content-Type to Document::upload()."
            );
        }

        return "{$base}; charset={$charset}";
    }

    private function isGzip(): bool
    {
        return strtoupper((string) $this->compressionAlgorithm) === 'GZIP';
    }

    private function detectFormat(string $contents): string
    {
        $trimmed = ltrim($contents);

        if ($trimmed === '') {
            return 'raw';
        }

        if ($trimmed[0] === '{' || $trimmed[0] === '[') {
            return 'json';
        }

        if ($trimmed[0] === '<') {
            return 'xml';
        }

        if (substr_count($trimmed, "\t") > substr_count(strstr($trimmed, "\n") ?: '', ',')) {
            return 'tsv';
        }

        return 'raw';
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJson(string $contents): array
    {
        $decoded = json_decode($contents, true);
        if (! is_array($decoded)) {
            throw new DocumentException('Document JSON could not be decoded.');
        }

        return $decoded;
    }

    private function parseXml(string $contents): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new DocumentException('Document XML could not be parsed.');
        }

        return $xml;
    }

    /**
     * @return list<list<string|null>>
     */
    private function parseDelimited(string $contents, string $delimiter): array
    {
        $rows = [];
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new DocumentException('Unable to open temp stream for delimited parse.');
        }

        fwrite($stream, $contents);
        rewind($stream);

        while (($row = fgetcsv($stream, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }
}
