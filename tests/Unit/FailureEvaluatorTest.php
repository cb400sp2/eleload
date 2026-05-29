<?php

declare(strict_types=1);

namespace Eleload\Tests\Unit;

use Eleload\Cli\RunOptions;
use Eleload\Metrics\FailureEvaluator;
use PHPUnit\Framework\TestCase;

final class FailureEvaluatorTest extends TestCase
{
    private FailureEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new FailureEvaluator();
    }

    /** Build a minimal report with given latency, requests, and throughput values. */
    private function makeReport(
        float $p95 = 100.0,
        float $p99 = 200.0,
        float $errorRate = 0.0,
        float $rps = 10.0,
        float $tps = 10.0,
    ): array {
        return [
            'summary' => [
                'latency'    => ['p95' => $p95, 'p99' => $p99],
                'requests'   => ['error_rate' => $errorRate],
                'throughput' => ['rps' => $rps, 'tps' => $tps],
            ],
        ];
    }

    private function makeOptions(array $overrides = []): RunOptions
    {
        return new RunOptions(
            url: $overrides['url'] ?? 'https://example.com',
            requests: $overrides['requests'] ?? 1,
            concurrency: $overrides['concurrency'] ?? 1,
            method: $overrides['method'] ?? 'GET',
            timeout: $overrides['timeout'] ?? 10,
            connectTimeout: $overrides['connectTimeout'] ?? null,
            silent: $overrides['silent'] ?? false,
            verbose: $overrides['verbose'] ?? false,
            debug: $overrides['debug'] ?? false,
            yes: $overrides['yes'] ?? false,
            allowHighLoad: $overrides['allowHighLoad'] ?? false,
            followRedirects: $overrides['followRedirects'] ?? false,
            headers: $overrides['headers'] ?? [],
            bearerToken: $overrides['bearerToken'] ?? null,
            basicUser: $overrides['basicUser'] ?? null,
            basicPassword: $overrides['basicPassword'] ?? null,
            cookie: $overrides['cookie'] ?? null,
            body: $overrides['body'] ?? null,
            reportJsonPath: $overrides['reportJsonPath'] ?? null,
            reportHtmlPath: $overrides['reportHtmlPath'] ?? null,
            reportMdPath: $overrides['reportMdPath'] ?? null,
            reportCsvPath: $overrides['reportCsvPath'] ?? null,
            outputDir: $overrides['outputDir'] ?? null,
            name: $overrides['name'] ?? null,
            successStatusCodes: $overrides['successStatusCodes'] ?? null,
            expectStatusCodes: $overrides['expectStatusCodes'] ?? null,
            expectBodyContains: $overrides['expectBodyContains'] ?? null,
            durationSec: $overrides['durationSec'] ?? null,
            warmupSec: $overrides['warmupSec'] ?? 0.0,
            failOnP95: $overrides['failOnP95'] ?? null,
            failOnP99: $overrides['failOnP99'] ?? null,
            failOnErrorRate: $overrides['failOnErrorRate'] ?? null,
            failOnRpsBelow: $overrides['failOnRpsBelow'] ?? null,
            failOnTpsBelow: $overrides['failOnTpsBelow'] ?? null,
            rate: $overrides['rate'] ?? null,
            targetRps: $overrides['targetRps'] ?? null,
            targetTps: $overrides['targetTps'] ?? null,
            rampUpSec: $overrides['rampUpSec'] ?? 0.0,
        );
    }

    public function testNoThresholdsReturnsPassedFalse(): void
    {
        $result = $this->evaluator->evaluate($this->makeReport(), $this->makeOptions());

        self::assertFalse($result['failed']);
        self::assertSame([], $result['checks']);
    }

    public function testP95ThresholdPassesWhenBelowLimit(): void
    {
        $opts   = $this->makeOptions(['failOnP95' => 200.0]);
        $result = $this->evaluator->evaluate($this->makeReport(p95: 150.0), $opts);

        self::assertFalse($result['failed']);
        self::assertCount(1, $result['checks']);
        self::assertTrue($result['checks'][0]['passed']);
        self::assertSame('p95', $result['checks'][0]['name']);
        self::assertSame('<=', $result['checks'][0]['operator']);
    }

    public function testP95ThresholdFailsWhenAboveLimit(): void
    {
        $opts   = $this->makeOptions(['failOnP95' => 100.0]);
        $result = $this->evaluator->evaluate($this->makeReport(p95: 200.0), $opts);

        self::assertTrue($result['failed']);
        self::assertFalse($result['checks'][0]['passed']);
    }

    public function testP99ThresholdPassesWhenBelowLimit(): void
    {
        $opts   = $this->makeOptions(['failOnP99' => 300.0]);
        $result = $this->evaluator->evaluate($this->makeReport(p99: 200.0), $opts);

        self::assertFalse($result['failed']);
        self::assertTrue($result['checks'][0]['passed']);
        self::assertSame('p99', $result['checks'][0]['name']);
    }

    public function testErrorRateThresholdFailsWhenAboveLimit(): void
    {
        $opts   = $this->makeOptions(['failOnErrorRate' => 5.0]);
        $result = $this->evaluator->evaluate($this->makeReport(errorRate: 10.0), $opts);

        self::assertTrue($result['failed']);
        self::assertFalse($result['checks'][0]['passed']);
        self::assertSame('error_rate', $result['checks'][0]['name']);
    }

    public function testErrorRateThresholdPassesWhenBelowLimit(): void
    {
        $opts   = $this->makeOptions(['failOnErrorRate' => 10.0]);
        $result = $this->evaluator->evaluate($this->makeReport(errorRate: 5.0), $opts);

        self::assertFalse($result['failed']);
        self::assertTrue($result['checks'][0]['passed']);
    }

    public function testRpsBelowThresholdFails(): void
    {
        $opts   = $this->makeOptions(['failOnRpsBelow' => 20.0]);
        $result = $this->evaluator->evaluate($this->makeReport(rps: 10.0), $opts);

        self::assertTrue($result['failed']);
        self::assertSame('rps', $result['checks'][0]['name']);
        self::assertSame('>=', $result['checks'][0]['operator']);
    }

    public function testRpsAboveThresholdPasses(): void
    {
        $opts   = $this->makeOptions(['failOnRpsBelow' => 5.0]);
        $result = $this->evaluator->evaluate($this->makeReport(rps: 10.0), $opts);

        self::assertFalse($result['failed']);
        self::assertTrue($result['checks'][0]['passed']);
    }

    public function testTpsBelowThresholdFails(): void
    {
        $opts   = $this->makeOptions(['failOnTpsBelow' => 20.0]);
        $result = $this->evaluator->evaluate($this->makeReport(tps: 5.0), $opts);

        self::assertTrue($result['failed']);
        self::assertSame('tps', $result['checks'][0]['name']);
    }

    public function testTpsAboveThresholdPasses(): void
    {
        $opts   = $this->makeOptions(['failOnTpsBelow' => 5.0]);
        $result = $this->evaluator->evaluate($this->makeReport(tps: 10.0), $opts);

        self::assertFalse($result['failed']);
    }

    public function testMultipleThresholdsAllPass(): void
    {
        $opts = $this->makeOptions([
            'failOnP95'       => 200.0,
            'failOnP99'       => 300.0,
            'failOnErrorRate' => 10.0,
            'failOnRpsBelow'  => 5.0,
            'failOnTpsBelow'  => 5.0,
        ]);
        $result = $this->evaluator->evaluate(
            $this->makeReport(p95: 100.0, p99: 200.0, errorRate: 0.0, rps: 20.0, tps: 20.0),
            $opts
        );

        self::assertFalse($result['failed']);
        self::assertCount(5, $result['checks']);
    }

    public function testMultipleThresholdsOneFailsCausesOverallFail(): void
    {
        $opts = $this->makeOptions([
            'failOnP95'      => 200.0,
            'failOnRpsBelow' => 100.0, // will fail: rps = 10
        ]);
        $result = $this->evaluator->evaluate($this->makeReport(p95: 100.0, rps: 10.0), $opts);

        self::assertTrue($result['failed']);
    }

    public function testActualValuesAreRoundedToTwoDecimals(): void
    {
        $opts   = $this->makeOptions(['failOnP95' => 200.0]);
        $result = $this->evaluator->evaluate(
            $this->makeReport(p95: 123.456789),
            $opts
        );

        self::assertSame(123.46, $result['checks'][0]['actual']);
        self::assertSame(200.0, $result['checks'][0]['threshold']);
    }
}
