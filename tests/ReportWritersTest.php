<?php

declare(strict_types=1);

use Eleload\Report\HtmlReporter;
use Eleload\Report\JsonReporter;

test('Report writers create json and html files', function (): void {
    $report = [
        'target' => ['url' => 'https://example.com', 'method' => 'GET'],
        'config' => ['requests' => 1, 'concurrency' => 1, 'timeout' => 10, 'target_rps' => null, 'target_tps' => null],
        'summary' => [
            'duration_sec' => 1.0,
            'requests' => ['total' => 1, 'success' => 1, 'failed' => 0, 'success_rate' => 100.0, 'error_rate' => 0.0],
            'throughput' => ['rps' => 1.0, 'tps' => 1.0, 'tps_rps_rate' => 100.0],
            'latency' => ['min' => 10.0, 'avg' => 10.0, 'p50' => 10.0, 'p95' => 10.0, 'p99' => 10.0, 'max' => 10.0],
            'status_codes' => ['200' => ['count' => 1, 'rate' => 100.0]],
        ],
        'errors' => [],
        'meta' => ['tool' => 'eleload', 'version' => '0.1.0'],
    ];

    $tmpDir = sys_get_temp_dir() . '/eleload-tests-' . uniqid('', true);
    assertTrue(mkdir($tmpDir, 0775, true), 'Failed to create temp directory');

    $jsonPath = $tmpDir . '/report.json';
    $htmlPath = $tmpDir . '/report.html';

    (new JsonReporter())->write($report, $jsonPath);
    (new HtmlReporter(dirname(__DIR__) . '/templates/report.php'))->write($report, $htmlPath);

    assertTrue(is_file($jsonPath), 'JSON report was not created');
    assertTrue(is_file($htmlPath), 'HTML report was not created');

    $json = (string) file_get_contents($jsonPath);
    $html = (string) file_get_contents($htmlPath);

    assertContains('"tool": "eleload"', $json);
    assertContains('<title>eleload report</title>', $html);
    assertContains('Total Requests', $html);
});

