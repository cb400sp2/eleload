<?php

declare(strict_types=1);

namespace Eleload\Tests\Unit;

use Eleload\LoadTesting\RequestOptions;
use Eleload\LoadTesting\RequestResult;
use Eleload\LoadTesting\RunResult;
use Eleload\Metrics\StatisticsCalculator;
use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests: verifies that StatisticsCalculator output conforms to the
 * JSON Schema defined in schema/report-v1.json (schema_version = 1).
 */
final class JsonSchemaContractTest extends TestCase
{
    private StatisticsCalculator $calc;
    private object $schema;

    protected function setUp(): void
    {
        $this->calc = new StatisticsCalculator();

        $schemaPath = dirname(__DIR__, 2) . '/schema/report-v1.json';
        $this->assertTrue(file_exists($schemaPath), "Schema file not found: {$schemaPath}");

        $decoded = json_decode((string) file_get_contents($schemaPath));
        $this->assertIsObject($decoded, 'Schema must be a JSON object');
        $this->schema = $decoded;
    }

    private function makeOptions(array $overrides = []): RequestOptions
    {
        return new RequestOptions(
            url: $overrides['url'] ?? 'https://example.com',
            requests: $overrides['requests'] ?? 4,
            concurrency: $overrides['concurrency'] ?? 2,
            method: $overrides['method'] ?? 'GET',
            timeout: $overrides['timeout'] ?? 10,
            name: $overrides['name'] ?? null,
            warmupSec: $overrides['warmupSec'] ?? 0.0,
        );
    }

    private function makeResult(RequestOptions $options, float $duration, array $requests): RunResult
    {
        return new RunResult(
            options: $options,
            durationSec: $duration,
            requestResults: $requests,
        );
    }

    private function validate(array $report): void
    {
        // json_encode → json_decode to get object graph (required by Validator)
        $data = json_decode(json_encode($report, JSON_THROW_ON_ERROR));

        $validator = new Validator();
        $validator->validate($data, $this->schema);

        $errors = $validator->getErrors();
        if ($errors !== []) {
            $messages = array_map(
                static fn (array $e): string => "[{$e['property']}] {$e['message']}",
                $errors
            );
            self::fail("JSON Schema validation failed:\n" . implode("\n", $messages));
        }

        self::assertTrue(true); // explicit assertion
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function testSuccessfulRunConformsToSchema(): void
    {
        $opts = $this->makeOptions(['requests' => 3, 'name' => 'smoke']);
        $run  = $this->makeResult($opts, 1.5, [
            new RequestResult(requestNumber: 1, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 2, latencyMs: 120.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 3, latencyMs: 80.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);

        $this->validate($this->calc->summarize($run));
    }

    public function testRunWithErrorsConformsToSchema(): void
    {
        $opts = $this->makeOptions(['requests' => 2]);
        $run  = $this->makeResult($opts, 0.5, [
            new RequestResult(requestNumber: 1, latencyMs: 10.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 2, latencyMs: 5.0, httpCode: 0, downloadBytes: 0.0, errorNo: 7, error: 'Connection refused'),
        ]);

        $this->validate($this->calc->summarize($run));
    }

    public function testRunWithNoRequestsConformsToSchema(): void
    {
        $opts = $this->makeOptions(['requests' => 0]);
        $run  = $this->makeResult($opts, 0.001, []);

        $this->validate($this->calc->summarize($run));
    }

    public function testRunWithWarmupConformsToSchema(): void
    {
        $opts = $this->makeOptions(['requests' => 4, 'warmupSec' => 0.5]);
        $run  = $this->makeResult($opts, 2.0, [
            new RequestResult(requestNumber: 1, latencyMs: 200.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: '', includedInMetrics: false),
            new RequestResult(requestNumber: 2, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 3, latencyMs: 110.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 4, latencyMs: 90.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);

        $this->validate($this->calc->summarize($run));
    }

    // -------------------------------------------------------------------------
    // schema_version backward-compatibility checks
    // -------------------------------------------------------------------------

    public function testSchemaVersionIsAlways1(): void
    {
        $opts = $this->makeOptions();
        $run  = $this->makeResult($opts, 1.0, [
            new RequestResult(requestNumber: 1, latencyMs: 50.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $report = $this->calc->summarize($run);

        self::assertSame(
            1,
            $report['meta']['schema_version'],
            'schema_version must be 1 for backward compatibility'
        );
    }

    public function testToolNameIsAlwaysEleload(): void
    {
        $opts = $this->makeOptions();
        $run  = $this->makeResult($opts, 1.0, [
            new RequestResult(requestNumber: 1, latencyMs: 50.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $report = $this->calc->summarize($run);

        self::assertSame('eleload', $report['meta']['tool']);
    }

    public function testRequiredTopLevelKeysPresent(): void
    {
        $opts = $this->makeOptions();
        $run  = $this->makeResult($opts, 1.0, [
            new RequestResult(requestNumber: 1, latencyMs: 50.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $report = $this->calc->summarize($run);

        foreach (['target', 'config', 'summary', 'errors', 'time_buckets', 'meta'] as $key) {
            self::assertArrayHasKey($key, $report, "Top-level key '{$key}' must be present");
        }
    }

    public function testRequiredSummaryKeysPresent(): void
    {
        $opts = $this->makeOptions();
        $run  = $this->makeResult($opts, 1.0, [
            new RequestResult(requestNumber: 1, latencyMs: 50.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $report = $this->calc->summarize($run);

        foreach (['duration_sec', 'total_duration_sec', 'requests', 'throughput', 'latency', 'status_codes', 'slowest_requests'] as $key) {
            self::assertArrayHasKey($key, $report['summary'], "summary.{$key} must be present");
        }
    }

    public function testRequiredLatencyKeysPresent(): void
    {
        $opts = $this->makeOptions();
        $run  = $this->makeResult($opts, 1.0, [
            new RequestResult(requestNumber: 1, latencyMs: 50.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $report = $this->calc->summarize($run);

        foreach (['min', 'avg', 'p50', 'p95', 'p99', 'max'] as $key) {
            self::assertArrayHasKey($key, $report['summary']['latency'], "latency.{$key} must be present");
        }
    }

    public function testRequestCountsAreConsistent(): void
    {
        $opts = $this->makeOptions(['requests' => 4]);
        $run  = $this->makeResult($opts, 1.0, [
            new RequestResult(requestNumber: 1, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 2, latencyMs: 100.0, httpCode: 500, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 3, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 4, latencyMs: 100.0, httpCode: 404, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $report = $this->calc->summarize($run);
        $req = $report['summary']['requests'];

        self::assertSame(
            $req['success'] + $req['failed'],
            $req['total'],
            'success + failed must equal total'
        );
        self::assertSame(2, $req['success'], '200 responses are success');
        self::assertSame(2, $req['failed'], '500+404 are failures');
    }

    public function testHttpVersionAppearsInConfigAndConformsToSchema(): void
    {
        $opts = new \Eleload\LoadTesting\RequestOptions(
            url: 'https://example.com',
            requests: 1,
            concurrency: 1,
            method: 'GET',
            timeout: 10,
            httpVersion: '1.1',
        );
        $run = $this->makeResult($opts, 0.5, [
            new RequestResult(requestNumber: 1, latencyMs: 50.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $report = $this->calc->summarize($run);

        self::assertSame('1.1', $report['config']['http_version']);
        $this->validate($report);
    }

    public function testDnsCacheTtlAppearsInConfigAndConformsToSchema(): void
    {
        $opts = new \Eleload\LoadTesting\RequestOptions(
            url: 'https://example.com',
            requests: 1,
            concurrency: 1,
            method: 'GET',
            timeout: 10,
            dnsCacheTtl: 30,
        );
        $run = $this->makeResult($opts, 0.5, [
            new RequestResult(requestNumber: 1, latencyMs: 50.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $report = $this->calc->summarize($run);

        self::assertSame(30, $report['config']['dns_cache_ttl']);
        $this->validate($report);
    }

    public function testAcceptEncodingAndNoDecompressConformToSchema(): void
    {
        $opts = new \Eleload\LoadTesting\RequestOptions(
            url: 'https://example.com',
            requests: 1,
            concurrency: 1,
            method: 'GET',
            timeout: 10,
            acceptEncoding: 'br',
            noDecompress: true,
        );
        $run = $this->makeResult($opts, 0.5, [
            new RequestResult(requestNumber: 1, latencyMs: 50.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $report = $this->calc->summarize($run);

        self::assertSame('br', $report['config']['accept_encoding']);
        self::assertTrue($report['config']['no_decompress']);
        $this->validate($report);
    }
}
