<?php

declare(strict_types=1);

use Eleload\LoadTesting\RequestOptions;
use Eleload\LoadTesting\RequestResult;
use Eleload\LoadTesting\RunResult;
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
});
