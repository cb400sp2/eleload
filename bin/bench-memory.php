#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Memory benchmark: measures peak RSS for a configurable number of synthetic
 * RequestResult objects processed through StatisticsCalculator.
 *
 * Usage:
 *   php bin/bench-memory.php [--requests=N] [--buffer-size=N]
 *
 * Examples:
 *   php bin/bench-memory.php --requests=100000
 *   php bin/bench-memory.php --requests=100000 --buffer-size=1000
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Eleload\LoadTesting\RequestOptions;
use Eleload\LoadTesting\RequestResult;
use Eleload\LoadTesting\RunResult;
use Eleload\Metrics\StatisticsCalculator;

$requests = 100_000;
$bufferSize = 10_000;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--requests=')) {
        $requests = (int) substr($arg, strlen('--requests='));
    } elseif (str_starts_with($arg, '--buffer-size=')) {
        $bufferSize = (int) substr($arg, strlen('--buffer-size='));
    }
}

echo sprintf("Benchmark: %d synthetic requests, buffer-size=%d\n", $requests, $bufferSize);
echo sprintf("PHP memory_limit: %s\n\n", ini_get('memory_limit') ?: 'unknown');

$memBefore = memory_get_peak_usage(true);
$startTime = microtime(true);

// Build a RunResult with synthetic data using CurlMultiRunner spill mechanism
$options = new RequestOptions(
    url: 'http://localhost',
    requests: $requests,
    concurrency: 1
);

// Build request results array (in-memory, up to buffer size then spill)
$results = [];
$spillPath = null;
$spillHandle = null;
$count = 0;

for ($i = 1; $i <= $requests; $i++) {
    $result = new RequestResult(
        requestNumber: $i,
        httpCode: 200,
        latencyMs: 10.0 + ($i % 100),
        downloadBytes: 1024.0,
        error: '',
        errorNo: 0,
        isSuccess: true,
        elapsedSec: ($i - 1) / 100.0,
        includedInMetrics: true,
        bodyContainsExpected: null
    );

    if (count($results) < $bufferSize) {
        $results[] = $result;
    } else {
        // Spill to disk
        if ($spillPath === null) {
            $spillPath = tempnam(sys_get_temp_dir(), 'bench_spill_');
            $spillHandle = fopen($spillPath, 'wb');
            // Write buffered results first
            foreach ($results as $buffered) {
                fwrite($spillHandle, json_encode($buffered->toArray()) . "\n");
            }
            $results = [];
        }
        fwrite($spillHandle, json_encode($result->toArray()) . "\n");
    }
    $count++;
}

if ($spillHandle !== null) {
    fclose($spillHandle);
}

$memAfterBuild = memory_get_peak_usage(true);

$runResult = new RunResult(
    options: $options,
    durationSec: $requests / 100.0,
    requestResults: $results,
    requestResultsPath: $spillPath,
    requestResultCount: $count
);

// Run statistics
$stats = new StatisticsCalculator();
$report = $stats->summarize($runResult);

$elapsed = microtime(true) - $startTime;
$memPeak = memory_get_peak_usage(true);

echo sprintf("Results: %d requests processed\n", $report['summary']['requests']['total']);
echo sprintf("RPS: %.2f\n", $report['summary']['throughput']['rps']);
echo sprintf("Peak memory (build): %s\n", formatBytes($memBefore));
echo sprintf("Peak memory (after build): %s\n", formatBytes($memAfterBuild));
echo sprintf("Peak memory (final): %s\n", formatBytes($memPeak));
echo sprintf("Elapsed: %.3fs\n", $elapsed);
echo sprintf("Spilled to disk: %s\n", $spillPath !== null ? 'yes' : 'no');

function formatBytes(int $bytes): string
{
    if ($bytes >= 1_048_576) {
        return sprintf('%.1f MB', $bytes / 1_048_576);
    }
    return sprintf('%.1f KB', $bytes / 1_024);
}
