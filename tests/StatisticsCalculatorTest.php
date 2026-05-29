<?php

declare(strict_types=1);

use Eleload\LoadTesting\RequestOptions;
use Eleload\LoadTesting\RequestResult;
use Eleload\LoadTesting\RunResult;
use Eleload\Metrics\FailureEvaluator;
use Eleload\Metrics\StatisticsCalculator;

test('StatisticsCalculator aggregates throughput, rates, and latency', function (): void {
    $options = new RequestOptions(
        url: 'https://example.com',
        requests: 4,
        concurrency: 2,
        method: 'GET',
        timeout: 10,
        headers: [],
        body: null,
        name: 'top page smoke load',
        rampUpSec: 10.0,
        rate: 2.0,
        targetRps: 2.0,
        targetTps: 1.0
    );

    $result = new RunResult(
        options: $options,
        durationSec: 2.0,
        requestResults: [
            new RequestResult(1, 100.0, 200, 128.0, 0, ''),
            new RequestResult(2, 200.0, 201, 128.0, 0, ''),
            new RequestResult(3, 300.0, 500, 128.0, 0, ''),
            new RequestResult(4, 200.0, 0, 0.0, 6, 'Could not resolve host: example.com'),
        ]
    );

    $summary = (new StatisticsCalculator())->summarize($result);

    assertSame(4, $summary['summary']['requests']['total']);
    assertSame('top page smoke load', $summary['config']['name']);
    assertSame(10.0, $summary['config']['ramp_up']);
    assertSame(2.0, $summary['config']['rate']);
    assertSame(10.0, $summary['config']['ramp_up']);
    assertSame(null, $summary['config']['success_status']);
    assertSame(null, $summary['config']['expect_status']);
    assertSame(null, $summary['config']['expect_body_contains']);
    assertSame('top page smoke load', $summary['meta']['test_name']);
    assertSame(2, $summary['summary']['requests']['success']);
    assertSame(2, $summary['summary']['requests']['failed']);
    assertSame(50.0, $summary['summary']['requests']['success_rate']);
    assertSame(50.0, $summary['summary']['requests']['error_rate']);

    assertSame(2.0, $summary['summary']['throughput']['rps']);
    assertSame(1.0, $summary['summary']['throughput']['tps']);
    assertSame(50.0, $summary['summary']['throughput']['tps_rps_rate']);
    assertSame(100.0, $summary['summary']['throughput']['rps_achievement_rate']);
    assertSame(100.0, $summary['summary']['throughput']['tps_achievement_rate']);

    assertSame(100.0, $summary['summary']['latency']['min']);
    assertSame(200.0, $summary['summary']['latency']['avg']);
    assertSame(200.0, $summary['summary']['latency']['p50']);
    assertSame(300.0, $summary['summary']['latency']['p95']);
    assertSame(300.0, $summary['summary']['latency']['p99']);
    assertSame(300.0, $summary['summary']['latency']['max']);

    assertSame(25.0, $summary['summary']['status_codes']['200']['rate']);
    assertSame(25.0, $summary['summary']['status_codes']['500']['rate']);
    assertSame(2, count($summary['errors']));
    assertSame(3, $summary['errors'][0]['request']);
    assertSame(4, $summary['errors'][1]['request']);
    assertSame(false, $summary['errors'][0]['success']);
    assertSame(128.0, $summary['errors'][0]['download_bytes']);
    assertSame(4, count($summary['summary']['slowest_requests']));
    assertSame(3, $summary['summary']['slowest_requests'][0]['request']);
    assertSame(300.0, $summary['summary']['slowest_requests'][0]['latency_ms']);
});

test('StatisticsCalculator excludes warmup requests from metrics', function (): void {
    $options = new RequestOptions(
        url: 'https://example.com',
        requests: 3,
        concurrency: 1,
        method: 'GET',
        timeout: 10,
        warmupSec: 1.0
    );

    $result = new RunResult(
        options: $options,
        durationSec: 3.0,
        requestResults: [
            new RequestResult(1, 1000.0, 500, 128.0, 0, '', false),
            new RequestResult(2, 100.0, 200, 128.0, 0, ''),
            new RequestResult(3, 200.0, 200, 128.0, 0, ''),
        ]
    );

    $summary = (new StatisticsCalculator())->summarize($result);

    assertSame(2, $summary['summary']['requests']['total']);
    assertSame(3, $summary['summary']['requests']['executed']);
    assertSame(1, $summary['summary']['requests']['warmup']);
    assertSame(2, $summary['summary']['requests']['success']);
    assertSame(0, $summary['summary']['requests']['failed']);
    assertSame(100.0, $summary['summary']['latency']['min']);
    assertSame(200.0, $summary['summary']['latency']['max']);
    assertSame(2.0, $summary['summary']['duration_sec']);
});

test('StatisticsCalculator applies custom success status codes', function (): void {
    $options = new RequestOptions(
        url: 'https://example.com',
        requests: 3,
        concurrency: 1,
        method: 'GET',
        timeout: 10,
        successStatusCodes: [200]
    );

    $result = new RunResult(
        options: $options,
        durationSec: 1.0,
        requestResults: [
            new RequestResult(1, 100.0, 200, 128.0, 0, ''),
            new RequestResult(2, 110.0, 302, 128.0, 0, ''),
            new RequestResult(3, 120.0, 500, 128.0, 0, ''),
        ]
    );

    $summary = (new StatisticsCalculator())->summarize($result);

    assertSame([200], $summary['config']['success_status']);
    assertSame(1, $summary['summary']['requests']['success']);
    assertSame(2, $summary['summary']['requests']['failed']);
    assertSame(33.33, $summary['summary']['requests']['success_rate']);
    assertSame(66.67, $summary['summary']['requests']['error_rate']);
    assertSame(2, $summary['errors'][0]['request']);
    assertSame(3, $summary['errors'][1]['request']);
});

test('StatisticsCalculator treats 302 as success by default', function (): void {
    $options = new RequestOptions(
        url: 'https://example.com',
        requests: 1,
        concurrency: 1,
        method: 'GET',
        timeout: 10
    );

    $result = new RunResult(
        options: $options,
        durationSec: 1.0,
        requestResults: [
            new RequestResult(1, 100.0, 302, 128.0, 0, ''),
        ]
    );

    $summary = (new StatisticsCalculator())->summarize($result);

    assertSame(1, $summary['summary']['requests']['success']);
    assertSame(0, $summary['summary']['requests']['failed']);
});

test('StatisticsCalculator applies expect status filter', function (): void {
    $options = new RequestOptions(
        url: 'https://example.com',
        requests: 3,
        concurrency: 1,
        method: 'GET',
        timeout: 10,
        expectStatusCodes: [200]
    );

    $result = new RunResult(
        options: $options,
        durationSec: 1.0,
        requestResults: [
            new RequestResult(1, 100.0, 200, 128.0, 0, ''),
            new RequestResult(2, 110.0, 201, 128.0, 0, ''),
            new RequestResult(3, 120.0, 500, 128.0, 0, ''),
        ]
    );

    $summary = (new StatisticsCalculator())->summarize($result);

    assertSame([200], $summary['config']['expect_status']);
    assertSame(1, $summary['summary']['requests']['success']);
    assertSame(2, $summary['summary']['requests']['failed']);
    assertSame(33.33, $summary['summary']['requests']['success_rate']);
    assertSame(66.67, $summary['summary']['requests']['error_rate']);
    assertSame(2, $summary['errors'][0]['request']);
    assertSame(3, $summary['errors'][1]['request']);
});

test('StatisticsCalculator applies expect body contains filter', function (): void {
    $options = new RequestOptions(
        url: 'https://example.com',
        requests: 3,
        concurrency: 1,
        method: 'GET',
        timeout: 10,
        expectBodyContains: 'Welcome'
    );

    $result = new RunResult(
        options: $options,
        durationSec: 1.0,
        requestResults: [
            new RequestResult(1, 100.0, 200, 128.0, 0, '', true, true),
            new RequestResult(2, 110.0, 200, 128.0, 0, '', true, false),
            new RequestResult(3, 120.0, 500, 128.0, 0, '', true, false),
        ]
    );

    $summary = (new StatisticsCalculator())->summarize($result);

    assertSame('Welcome', $summary['config']['expect_body_contains']);
    assertSame(1, $summary['summary']['requests']['success']);
    assertSame(2, $summary['summary']['requests']['failed']);
    assertSame(33.33, $summary['summary']['requests']['success_rate']);
    assertSame(66.67, $summary['summary']['requests']['error_rate']);
    assertSame(2, $summary['errors'][0]['request']);
    assertSame(3, $summary['errors'][1]['request']);
});

test('FailureEvaluator reports threshold violations', function (): void {
    $parser = new Eleload\Cli\ArgvParser();
    $options = $parser->parseRun([
        'https://example.com',
        '--fail-on-p95=150',
        '--fail-on-p99=250',
        '--fail-on-error-rate=1',
        '--fail-on-rps-below=10',
        '--fail-on-tps-below=5',
    ]);

    $report = [
        'summary' => [
            'requests' => ['error_rate' => 2.0],
            'throughput' => ['rps' => 9.0, 'tps' => 7.0],
            'latency' => ['p95' => 200.0, 'p99' => 240.0],
        ],
    ];

    $result = (new FailureEvaluator())->evaluate($report, $options);

    assertSame(true, $result['failed']);
    assertSame(false, $result['checks'][0]['passed']);
    assertSame(true, $result['checks'][1]['passed']);
    assertSame(false, $result['checks'][2]['passed']);
    assertSame(false, $result['checks'][3]['passed']);
    assertSame(true, $result['checks'][4]['passed']);
});

test('StatisticsCalculator builds time_buckets from elapsedSec', function (): void {
    $options = new RequestOptions(
        url: 'https://example.com',
        requests: 6,
        concurrency: 2,
        method: 'GET',
        timeout: 10
    );

    // Two requests complete at ~0.5 s (bucket 0), two at ~1.5 s (bucket 1), two at ~2.5 s (bucket 2).
    $result = new RunResult(
        options: $options,
        durationSec: 3.0,
        requestResults: [
            new RequestResult(1, 100.0, 200, 0.0, 0, '', true, null, 0.4),
            new RequestResult(2, 200.0, 500, 0.0, 0, '', true, null, 0.6),
            new RequestResult(3, 150.0, 200, 0.0, 0, '', true, null, 1.3),
            new RequestResult(4, 250.0, 200, 0.0, 0, '', true, null, 1.7),
            new RequestResult(5, 120.0, 200, 0.0, 0, '', true, null, 2.2),
            new RequestResult(6, 180.0, 200, 0.0, 0, '', true, null, 2.9),
        ]
    );

    $summary = (new StatisticsCalculator())->summarize($result);
    $tb = $summary['time_buckets'];

    assertSame(3, count($tb));

    // Bucket 0: requests 1 & 2 — 1 success, 1 failure
    assertSame(0, $tb[0]['t']);
    assertSame(2.0, $tb[0]['rps']);
    assertSame(1.0, $tb[0]['tps']);
    assertSame(50.0, $tb[0]['error_rate']);
    assertSame(150.0, $tb[0]['avg_latency_ms']);

    // Bucket 1: requests 3 & 4 — both success
    assertSame(1, $tb[1]['t']);
    assertSame(2.0, $tb[1]['rps']);
    assertSame(2.0, $tb[1]['tps']);
    assertSame(0.0, $tb[1]['error_rate']);
    assertSame(200.0, $tb[1]['avg_latency_ms']);

    // Bucket 2: requests 5 & 6 — both success
    assertSame(2, $tb[2]['t']);
    assertSame(2.0, $tb[2]['rps']);
    assertSame(2.0, $tb[2]['tps']);
    assertSame(0.0, $tb[2]['error_rate']);
    assertSame(150.0, $tb[2]['avg_latency_ms']);
});

test('StatisticsCalculator time_buckets exclude warmup requests', function (): void {
    $options = new RequestOptions(
        url: 'https://example.com',
        requests: 4,
        concurrency: 1,
        method: 'GET',
        timeout: 10,
        warmupSec: 1.0
    );

    $result = new RunResult(
        options: $options,
        durationSec: 3.0,
        requestResults: [
            // warmup (includedInMetrics=false)
            new RequestResult(1, 500.0, 200, 0.0, 0, '', false, null, 0.5),
            // after warmup: elapsedSec=1.2 → bucket index = floor(1.2 - 1.0) = 0
            new RequestResult(2, 100.0, 200, 0.0, 0, '', true, null, 1.2),
            // elapsedSec=2.1 → bucket index = floor(2.1 - 1.0) = 1
            new RequestResult(3, 200.0, 200, 0.0, 0, '', true, null, 2.1),
            // elapsedSec=2.8 → bucket index = floor(2.8 - 1.0) = 1
            new RequestResult(4, 300.0, 500, 0.0, 0, '', true, null, 2.8),
        ]
    );

    $summary = (new StatisticsCalculator())->summarize($result);
    $tb = $summary['time_buckets'];

    // Only 2 buckets (warmup request excluded)
    assertSame(2, count($tb));

    assertSame(0, $tb[0]['t']);
    assertSame(1.0, $tb[0]['rps']);
    assertSame(1.0, $tb[0]['tps']);

    assertSame(1, $tb[1]['t']);
    assertSame(2.0, $tb[1]['rps']);
    assertSame(1.0, $tb[1]['tps']);  // request 4 (500) is a failure
    assertSame(50.0, $tb[1]['error_rate']);
});
