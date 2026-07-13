<?php

declare(strict_types=1);

namespace Tims\AmazonSpApi\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use SimpleXMLElement;
use SpApi\Model\feeds\v2021_06_30\CreateFeedDocumentResponse;
use SpApi\Model\feeds\v2021_06_30\FeedDocument;
use SpApi\Model\reports\v2021_06_30\ReportDocument;
use Tims\AmazonSpApi\Document;
use Tims\AmazonSpApi\Exceptions\DocumentException;

class DocumentTest extends TestCase
{
    public function test_downloads_and_gunzips_report_document(): void
    {
        $payload = "sku\tquantity\nABC\t1\n";
        $mock = new MockHandler([
            new Response(200, [], gzencode($payload)),
        ]);

        $document = new Document(
            url: 'https://example.com/report.gz',
            compressionAlgorithm: ReportDocument::COMPRESSION_ALGORITHM_GZIP,
            documentId: 'doc-1',
        );

        $contents = $document->download(new Client(['handler' => HandlerStack::create($mock)]));

        $this->assertSame($payload, $contents);
    }

    public function test_download_stream_returns_psr_stream(): void
    {
        $payload = 'hello';
        $mock = new MockHandler([
            new Response(200, [], $payload),
        ]);

        $document = new Document(url: 'https://example.com/raw.txt');
        $stream = $document->downloadStream(new Client(['handler' => HandlerStack::create($mock)]));

        $this->assertSame($payload, (string) $stream);
    }

    public function test_parses_tsv_report(): void
    {
        $payload = "sku\tquantity\nABC\t1\n";
        $mock = new MockHandler([
            new Response(200, [], $payload),
        ]);

        $document = new Document(url: 'https://example.com/report.tsv');
        $rows = $document->downloadParsed(
            'tsv',
            new Client(['handler' => HandlerStack::create($mock)]),
        );

        $this->assertSame([['sku', 'quantity'], ['ABC', '1']], $rows);
    }

    public function test_parses_csv_report(): void
    {
        $payload = "sku,quantity\nABC,1\n";
        $mock = new MockHandler([
            new Response(200, [], $payload),
        ]);

        $document = new Document(url: 'https://example.com/report.csv');
        $rows = $document->downloadParsed(
            'csv',
            new Client(['handler' => HandlerStack::create($mock)]),
        );

        $this->assertSame([['sku', 'quantity'], ['ABC', '1']], $rows);
    }

    public function test_parses_json_report(): void
    {
        $payload = '{"sku":"ABC","quantity":1}';
        $mock = new MockHandler([
            new Response(200, [], $payload),
        ]);

        $document = new Document(url: 'https://example.com/report.json');
        $data = $document->downloadParsed(
            'json',
            new Client(['handler' => HandlerStack::create($mock)]),
        );

        $this->assertSame(['sku' => 'ABC', 'quantity' => 1], $data);
    }

    public function test_parses_xml_report(): void
    {
        $payload = '<root><sku>ABC</sku></root>';
        $mock = new MockHandler([
            new Response(200, [], $payload),
        ]);

        $document = new Document(url: 'https://example.com/report.xml');
        $xml = $document->downloadParsed(
            'xml',
            new Client(['handler' => HandlerStack::create($mock)]),
        );

        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
        $this->assertSame('ABC', (string) $xml->sku);
    }

    public function test_auto_detects_json_format(): void
    {
        $payload = '["a","b"]';
        $mock = new MockHandler([
            new Response(200, [], $payload),
        ]);

        $document = new Document(url: 'https://example.com/report');
        $data = $document->downloadParsed(
            'auto',
            new Client(['handler' => HandlerStack::create($mock)]),
        );

        $this->assertSame(['a', 'b'], $data);
    }

    public function test_unsupported_format_throws(): void
    {
        $mock = new MockHandler([
            new Response(200, [], 'payload'),
        ]);

        $document = new Document(url: 'https://example.com/report');

        $this->expectException(DocumentException::class);

        $document->downloadParsed(
            'xlsx',
            new Client(['handler' => HandlerStack::create($mock)]),
        );
    }

    public function test_uploads_feed_document(): void
    {
        $mock = new MockHandler([
            new Response(200, [], ''),
        ]);

        $response = new CreateFeedDocumentResponse([
            'feed_document_id' => 'feed-doc-1',
            'url' => 'https://example.com/upload',
        ]);

        $document = Document::fromCreateFeedDocumentResponse($response);
        $document->upload(
            '<AmazonEnvelope/>',
            Document::contentTypeForFeed('POST_PRODUCT_PRICING_DATA'),
            new Client(['handler' => HandlerStack::create($mock)]),
        );

        $this->assertSame('feed-doc-1', $document->documentId());
        $this->assertSame(0, $mock->count());
    }

    public function test_upload_failure_throws(): void
    {
        $mock = new MockHandler([
            new Response(403, [], 'denied'),
        ]);

        $document = Document::fromFeedDocument(new FeedDocument([
            'feed_document_id' => 'feed-doc-2',
            'url' => 'https://example.com/upload',
        ]));

        $this->expectException(DocumentException::class);

        $document->upload(
            '<AmazonEnvelope/>',
            'text/xml; charset=UTF-8',
            new Client(['handler' => HandlerStack::create($mock)]),
        );
    }

    public function test_from_report_document_exposes_url_and_id(): void
    {
        $report = new ReportDocument([
            'report_document_id' => 'rep-1',
            'url' => 'https://example.com/rep',
        ]);

        $document = Document::fromReportDocument($report);

        $this->assertSame('https://example.com/rep', $document->url());
        $this->assertSame('rep-1', $document->documentId());
    }

    public function test_unknown_feed_type_requires_explicit_content_type(): void
    {
        $this->expectException(DocumentException::class);

        Document::contentTypeForFeed('UNKNOWN_FEED_TYPE');
    }
}
