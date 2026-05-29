<?php

declare(strict_types=1);

use Eleload\LoadTesting\RequestOptions;
use Eleload\LoadTesting\RequestResult;
use Eleload\LoadTesting\RunResult;
use Eleload\Metrics\PercentileCalculator;
use Eleload\Metrics\StatisticsCalculator;

// ---------------------------------------------------------------------------
// PercentileCalculator accuracy regression tests
// ---------------------------------------------------------------------------

test('PercentileCalculator returns 0 for empty array', function (): void {
    $calc = new PercentileCalculator();
    assertSame(0.0, $calc->calculate([], 50));
    assertSame(0.0, $calc->calculate([], 95));
    assertSame(0.0, $calc->calculate([], 99));
});

test('PercentileCalculator returns single value for one-element array', function (): void {
    $calc = new PercentileCalculator();
    assertSame(42.0, $calc->calculate([42.0], 50));
    assertSame(42.0, $calc->calculate([42.0], 95));
    assertSame(42.0, $calc->calculate([42.0], 99));
});

test('PercentileCalculator computes correct percentiles for 10-element dataset', function (): void {
    // [10, 20, 30, 40, 50, 60, 70, 80, 90, 100] (already sorted)
    // p50: ceil(0.50 * 10) - 1 = 4  → 50
    // p95: ceil(0.95 * 10) - 1 = 9  → 100
    // p99: ceil(0.99 * 10) - 1 = 9  → 100
    $values = [10.0, 20.0, 30.0, 40.0, 50.0, 60.0, 70.0, 80.0, 90.0, 100.0];
    $calc = new PercentileCalculator();

    assertSame(50.0, $calc->calculate($values, 50));
    assertSame(100.0, $calc->calculate($values, 95));
    assertSame(100.0, $calc->calculate($values, 99));
});

test('PercentileCalculator computes correct percentiles for 100-element dataset', function (): void {
    // Values 1..100
    // p50: ceil(0.50 * 100) - 1 = 49  → 50
    // p95: ceil(0.95 * 100) - 1 = 94  → 95
    // p99: ceil(0.99 * 100) - 1 = 98  → 99
    $values = array_map('floatval', range(1, 100));
    $calc = new PercentileCalculator();

    assertSame(50.0, $calc->calculate($values, 50));
    assertSame(95.0, $calc->calculate($values, 95));
    assertSame(99.0, $calc->calculate($values, 99));
});

test('PercentileCalculator sorts input before computing', function (): void {
    // Provide in reverse order; result must be the same as sorted
    $values = array_map('floatval', range(100, 1, -1));
    $calc = new PercentileCalculator();

    assertSame(50.0, $calc->calculate($values, 50));
    assertSame(95.0, $calc->calculate($values, 95));
    assertSame(99.0, $calc->calculate($values, 99));
});

// ---------------------------------------------------------------------------
// StatisticsCalculator accuracy regression tests
// ---------------------------------------------------------------------------

test('StatisticsCalculator computes accurate latency percentiles from RequestResults', function (): void {
    $options = new RequestOptions(
        url: 'https://example.com',
        requests: 100,
        concurrency: 10,
        method: 'GET',
        timeout: 10
    );

    // Build 100 requests with latencies 1..100 ms, all 200 OK
    $requestResults = [];
    for ($i = 1; $i <= 100; $i++) {
        $requestResults[] = new RequestResult($i, (float) $i, 200, 0.0, 0, '');
    }

    $result = new RunResult(
        options: $options,
        durationSec: 10.0,
        requestResults: $requestResults
    );

    $summary = (new StatisticsCalculator())->summarize($result);
    $latency = $summary['summary']['latency'];

    assertSame(1.0, $latency['min']);
    assertSame(100.0, $latency['max']);
    assertSame(50.5, $latency['avg']);  // (1+100)/2 * 100/100 = 50.5
    assertSame(50.0, $latency['p50']); // ceil(0.50*100)-1 = 49 → value[49] = 50
    assertSame(95.0, $latency['p95']); // ceil(0.95*100)-1 = 94 → value[94] = 95
    assertSame(99.0, $latency['p99']); // ceil(0.99*100)-1 = 98 → value[98] = 99
});

test('StatisticsCalculator computes accurate throughput for known request count and duration', function (): void {
    $options = new RequestOptions(
        url: 'https://example.com',
        requests: 60,
        concurrency: 10,
        method: 'GET',
        timeout: 10
    );

    $requestResults = [];
    for ($i = 1; $i <= 60; $i++) {
        $requestResults[] = new RequestResult($i, 100.0, 200, 0.0, 0, '');
    }

    $result = new RunResult(
        options: $options,
        durationSec: 3.0,
        requestResults: $requestResults
    );

    $summary = (new StatisticsCalculator())->summarize($result);

    assertSame(60, $summary['summary']['requests']['total']);
    assertSame(60, $summary['summary']['requests']['success']);
    assertSame(0, $summary['summary']['requests']['failed']);
    // 60 requests / 3s = 20 rps = 20 tps
    assertSame(20.0, $summary['summary']['throughput']['rps']);
    assertSame(20.0, $summary['summary']['throughput']['tps']);
    assertSame(100.0, $summary['summary']['throughput']['tps_rps_rate']);
});

// ---------------------------------------------------------------------------
// Edge-case: zero included-in-metrics results (all warmup)
// ---------------------------------------------------------------------------

test('StatisticsCalculator handles all-warmup results without division-by-zero', function (): void {
    $options = new RequestOptions(
        url: 'https://example.com',
        requests: 3,
        concurrency: 1,
        method: 'GET',
        timeout: 10,
        warmupSec: 30.0
    );

    $result = new RunResult(
        options: $options,
        durationSec: 5.0,
        requestResults: [
            new RequestResult(1, 50.0, 200, 0.0, 0, '', false),
            new RequestResult(2, 60.0, 200, 0.0, 0, '', false),
            new RequestResult(3, 70.0, 500, 0.0, 0, '', false),
        ]
    );

    $summary = (new StatisticsCalculator())->summarize($result);

    assertSame(0, $summary['summary']['requests']['total']);
    assertSame(3, $summary['summary']['requests']['warmup']);
    assertSame(0, $summary['summary']['requests']['success']);
    assertSame(0, $summary['summary']['requests']['failed']);
    assertSame(0.0, $summary['summary']['requests']['success_rate']);
    assertSame(0.0, $summary['summary']['requests']['error_rate']);
    assertSame(0.0, $summary['summary']['throughput']['rps']);
    assertSame(0.0, $summary['summary']['throughput']['tps']);
    assertSame(0.0, $summary['summary']['latency']['min']);
    assertSame(0.0, $summary['summary']['latency']['max']);
    assertSame(0.0, $summary['summary']['latency']['avg']);
    assertSame(0.0, $summary['summary']['latency']['p50']);
    assertSame(0.0, $summary['summary']['latency']['p95']);
    assertSame(0.0, $summary['summary']['latency']['p99']);
    assertSame([], $summary['time_buckets']);
});

test('StatisticsCalculator handles single request result correctly', function (): void {
    $options = new RequestOptions(
        url: 'https://example.com',
        requests: 1,
        concurrency: 1,
        method: 'GET',
        timeout: 10
    );

    $result = new RunResult(
        options: $options,
        durationSec: 0.2,
        requestResults: [
            new RequestResult(1, 150.0, 200, 512.0, 0, ''),
        ]
    );

    $summary = (new StatisticsCalculator())->summarize($result);

    assertSame(1, $summary['summary']['requests']['total']);
    assertSame(1, $summary['summary']['requests']['success']);
    assertSame(0, $summary['summary']['requests']['failed']);
    assertSame(100.0, $summary['summary']['requests']['success_rate']);
    assertSame(0.0, $summary['summary']['requests']['error_rate']);
    assertSame(150.0, $summary['summary']['latency']['min']);
    assertSame(150.0, $summary['summary']['latency']['max']);
    assertSame(150.0, $summary['summary']['latency']['avg']);
    assertSame(150.0, $summary['summary']['latency']['p50']);
    assertSame(150.0, $summary['summary']['latency']['p95']);
    assertSame(150.0, $summary['summary']['latency']['p99']);
    assertTrue($summary['summary']['throughput']['rps'] > 0.0);
});
