<?php

declare(strict_types=1);

use Eleload\LoadTesting\CurlMultiRunner;
use Eleload\LoadTesting\RequestOptions;
use Eleload\Metrics\StatisticsCalculator;
use Eleload\Report\CsvReporter;

test('CurlMultiRunner spills request results to disk when the in-memory threshold is exceeded', function (): void {
    $runResult = (new CurlMultiRunner(maxInMemoryRequestResults: 1))->run(new RequestOptions(
        url: 'http://127.0.0.1:1',
        requests: 4,
        concurrency: 4,
        method: 'GET',
        timeout: 1
    ));

    assertSame(0, count($runResult->requestResults), 'spilled runs should not retain raw request objects in memory');
    assertSame(4, $runResult->countRequestResults(), 'spilled runs should preserve executed request counts');

    $iteratedResults = iterator_to_array($runResult->iterateRequestResults(), false);
    assertSame(4, count($iteratedResults), 'spilled runs should still allow request iteration');

    $summary = (new StatisticsCalculator())->summarize($runResult);
    assertSame(4, $summary['summary']['requests']['executed']);
    assertSame(true, $summary['summary']['latency']['min'] >= 0.0);
    assertSame(true, $summary['summary']['latency']['max'] >= $summary['summary']['latency']['min']);

    $tmpDir = sys_get_temp_dir() . '/eleload-tests-spill-' . uniqid('', true);
    assertTrue(mkdir($tmpDir, 0775, true), 'Failed to create temp directory');
    $csvPath = $tmpDir . '/report.csv';

    (new CsvReporter())->write($runResult, $csvPath);

    assertTrue(is_file($csvPath), 'CSV report was not created for spilled results');
    $csv = (string) file_get_contents($csvPath);
    assertContains('request,included_in_metrics,success,http_code,error_no,latency_ms,download_bytes,body_contains_expected,error', $csv);
});