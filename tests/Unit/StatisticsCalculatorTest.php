<?php

declare(strict_types=1);

namespace Eleload\Tests\Unit;

use Eleload\LoadTesting\RequestOptions;
use Eleload\LoadTesting\RequestResult;
use Eleload\LoadTesting\RunResult;
use Eleload\Metrics\StatisticsCalculator;
use PHPUnit\Framework\TestCase;

final class StatisticsCalculatorTest extends TestCase
{
    private StatisticsCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new StatisticsCalculator();
    }

    private function makeOptions(array $overrides = []): RequestOptions
    {
        return new RequestOptions(
            url: $overrides['url'] ?? 'https://example.com',
            requests: $overrides['requests'] ?? 4,
            concurrency: $overrides['concurrency'] ?? 2,
            method: $overrides['method'] ?? 'GET',
            timeout: $overrides['timeout'] ?? 10,
            headers: $overrides['headers'] ?? [],
            body: $overrides['body'] ?? null,
            name: $overrides['name'] ?? null,
            successStatusCodes: $overrides['successStatusCodes'] ?? null,
            expectStatusCodes: $overrides['expectStatusCodes'] ?? null,
            expectBodyContains: $overrides['expectBodyContains'] ?? null,
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

    public function testAggregatesTotalRequestCount(): void
    {
        $opts    = $this->makeOptions();
        $run     = $this->makeResult($opts, 2.0, [
            new RequestResult(requestNumber: 1, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 2, latencyMs: 200.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 3, latencyMs: 300.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 4, latencyMs: 150.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $summary = $this->calc->summarize($run);

        self::assertSame(4, $summary['summary']['requests']['total']);
    }

    public function testCountsSuccessAndFailedRequests(): void
    {
        $opts = $this->makeOptions(['requests' => 3]);
        $run  = $this->makeResult($opts, 2.0, [
            new RequestResult(requestNumber: 1, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 2, latencyMs: 200.0, httpCode: 500, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 3, latencyMs: 300.0, httpCode: 0, downloadBytes: 0.0, errorNo: 6, error: 'error'),
        ]);
        $summary = $this->calc->summarize($run);

        self::assertSame(1, $summary['summary']['requests']['success']);
        self::assertSame(2, $summary['summary']['requests']['failed']);
    }

    public function testComputesLatencyMetrics(): void
    {
        $opts = $this->makeOptions(['requests' => 4]);
        $run  = $this->makeResult($opts, 2.0, [
            new RequestResult(requestNumber: 1, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 2, latencyMs: 200.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 3, latencyMs: 300.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 4, latencyMs: 400.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $summary = $this->calc->summarize($run);
        $latency = $summary['summary']['latency'];

        self::assertSame(100.0, $latency['min']);
        self::assertSame(400.0, $latency['max']);
        self::assertSame(250.0, $latency['avg']);
    }

    public function testComputesThroughputRps(): void
    {
        $opts    = $this->makeOptions(['requests' => 4]);
        $run     = $this->makeResult($opts, 2.0, [
            new RequestResult(requestNumber: 1, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 2, latencyMs: 200.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 3, latencyMs: 300.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 4, latencyMs: 400.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $summary = $this->calc->summarize($run);

        self::assertSame(2.0, $summary['summary']['throughput']['rps']);
    }

    public function testExcludesWarmupRequestsFromMetrics(): void
    {
        $opts = $this->makeOptions(['requests' => 4, 'warmupSec' => 1.0]);
        $run  = $this->makeResult($opts, 3.0, [
            new RequestResult(requestNumber: 1, latencyMs: 50.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: '', includedInMetrics: false),
            new RequestResult(requestNumber: 2, latencyMs: 200.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 3, latencyMs: 300.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 4, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $summary = $this->calc->summarize($run);

        self::assertSame(3, $summary['summary']['requests']['total']);
    }

    public function testCustomSuccessStatusCodes(): void
    {
        $opts = $this->makeOptions(['requests' => 2, 'successStatusCodes' => [201, 204]]);
        $run  = $this->makeResult($opts, 1.0, [
            new RequestResult(requestNumber: 1, latencyMs: 100.0, httpCode: 201, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 2, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $summary = $this->calc->summarize($run);

        self::assertSame(1, $summary['summary']['requests']['success']);
        self::assertSame(1, $summary['summary']['requests']['failed']);
    }

    public function testTreats302AsSuccessByDefault(): void
    {
        $opts = $this->makeOptions(['requests' => 1]);
        $run  = $this->makeResult($opts, 1.0, [
            new RequestResult(requestNumber: 1, latencyMs: 100.0, httpCode: 302, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $summary = $this->calc->summarize($run);

        self::assertSame(1, $summary['summary']['requests']['success']);
    }

    public function testExpectStatusCodeFilter(): void
    {
        $opts = $this->makeOptions(['requests' => 2, 'expectStatusCodes' => [200]]);
        $run  = $this->makeResult($opts, 1.0, [
            new RequestResult(requestNumber: 1, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 2, latencyMs: 100.0, httpCode: 201, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $summary = $this->calc->summarize($run);

        self::assertSame(1, $summary['summary']['requests']['success']);
        self::assertSame(1, $summary['summary']['requests']['failed']);
    }

    public function testExpectBodyContainsFilter(): void
    {
        $opts = $this->makeOptions(['requests' => 2, 'expectBodyContains' => '"status":"ok"']);
        $run  = $this->makeResult($opts, 1.0, [
            new RequestResult(requestNumber: 1, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: '', bodyContainsExpected: true),
            new RequestResult(requestNumber: 2, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: '', bodyContainsExpected: false),
        ]);
        $summary = $this->calc->summarize($run);

        self::assertSame(1, $summary['summary']['requests']['success']);
        self::assertSame(1, $summary['summary']['requests']['failed']);
    }

    public function testErrorRateCalculation(): void
    {
        $opts = $this->makeOptions(['requests' => 4]);
        $run  = $this->makeResult($opts, 2.0, [
            new RequestResult(requestNumber: 1, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 2, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 3, latencyMs: 100.0, httpCode: 500, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 4, latencyMs: 100.0, httpCode: 500, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $summary = $this->calc->summarize($run);

        self::assertSame(50.0, $summary['summary']['requests']['error_rate']);
        self::assertSame(50.0, $summary['summary']['requests']['success_rate']);
    }

    public function testStatusCodeAggregation(): void
    {
        $opts = $this->makeOptions(['requests' => 4]);
        $run  = $this->makeResult($opts, 2.0, [
            new RequestResult(requestNumber: 1, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 2, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 3, latencyMs: 100.0, httpCode: 500, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 4, latencyMs: 100.0, httpCode: 404, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $summary = $this->calc->summarize($run);
        $codes   = $summary['summary']['status_codes'];

        self::assertSame(2, $codes['200']['count']);
        self::assertSame(1, $codes['500']['count']);
        self::assertSame(1, $codes['404']['count']);
    }

    public function testSchemaVersionAndVersionPresentInMeta(): void
    {
        $opts    = $this->makeOptions();
        $run     = $this->makeResult($opts, 1.0, [
            new RequestResult(requestNumber: 1, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $summary = $this->calc->summarize($run);

        self::assertArrayHasKey('schema_version', $summary['meta']);
        self::assertArrayHasKey('version', $summary['meta']);
    }

    public function testSlowestRequestsSortedByLatencyDescending(): void
    {
        $opts = $this->makeOptions(['requests' => 3]);
        $run  = $this->makeResult($opts, 2.0, [
            new RequestResult(requestNumber: 1, latencyMs: 100.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 2, latencyMs: 400.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
            new RequestResult(requestNumber: 3, latencyMs: 250.0, httpCode: 200, downloadBytes: 0.0, errorNo: 0, error: ''),
        ]);
        $summary  = $this->calc->summarize($run);
        $slowest  = $summary['summary']['slowest_requests'];

        self::assertSame(400.0, $slowest[0]['latency_ms']);
        self::assertSame(250.0, $slowest[1]['latency_ms']);
    }
}
