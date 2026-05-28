<?php

declare(strict_types=1);

use Eleload\Report\HtmlReporter;
use Eleload\Report\JsonReporter;
use Eleload\Report\MarkdownReporter;
use Eleload\Report\ReportPathGenerator;

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
        'meta' => ['tool' => 'eleload', 'version' => '0.1.0', 'test_name' => 'top page smoke load'],
    ];

    $tmpDir = sys_get_temp_dir() . '/eleload-tests-' . uniqid('', true);
    assertTrue(mkdir($tmpDir, 0775, true), 'Failed to create temp directory');

    $jsonPath = $tmpDir . '/report.json';
    $htmlPath = $tmpDir . '/report.html';
    $mdPath = $tmpDir . '/report.md';

    (new JsonReporter())->write($report, $jsonPath);
    (new HtmlReporter(dirname(__DIR__) . '/templates/report.php'))->write($report, $htmlPath);
    (new MarkdownReporter())->write($report, $mdPath);

    assertTrue(is_file($jsonPath), 'JSON report was not created');
    assertTrue(is_file($htmlPath), 'HTML report was not created');
    assertTrue(is_file($mdPath), 'Markdown report was not created');

    $json = (string) file_get_contents($jsonPath);
    $html = (string) file_get_contents($htmlPath);
    $markdown = (string) file_get_contents($mdPath);

    assertContains('"tool": "eleload"', $json);
    assertContains('"test_name": "top page smoke load"', $json);
    assertContains('<title>eleload report</title>', $html);
    assertContains('Test Name: top page smoke load', $html);
    assertContains('Total Requests', $html);
    assertContains('# Eleload Report', $markdown);
    assertContains('**Test Name:** top page smoke load', $markdown);
    assertContains('| RPS | 1.00 req/sec |', $markdown);
});

test('JsonReporter keeps status_codes as object map even for zero code', function (): void {
    $report = [
        'target' => ['url' => 'https://example.com', 'method' => 'GET'],
        'config' => ['requests' => 1, 'concurrency' => 1, 'timeout' => 10, 'target_rps' => null, 'target_tps' => null],
        'summary' => [
            'duration_sec' => 1.0,
            'requests' => ['total' => 1, 'success' => 0, 'failed' => 1, 'success_rate' => 0.0, 'error_rate' => 100.0],
            'throughput' => ['rps' => 1.0, 'tps' => 0.0, 'tps_rps_rate' => 0.0],
            'latency' => ['min' => 1.0, 'avg' => 1.0, 'p50' => 1.0, 'p95' => 1.0, 'p99' => 1.0, 'max' => 1.0],
            'status_codes' => [0 => ['count' => 1, 'rate' => 100.0]],
        ],
        'errors' => [['request' => 1, 'http_code' => 0, 'error_no' => 6, 'error' => 'Could not resolve host', 'latency_ms' => 1.0]],
        'meta' => ['tool' => 'eleload', 'version' => '0.1.0'],
    ];

    $tmpDir = sys_get_temp_dir() . '/eleload-tests-status-codes-' . uniqid('', true);
    assertTrue(mkdir($tmpDir, 0775, true), 'Failed to create temp directory');

    $jsonPath = $tmpDir . '/report.json';
    (new JsonReporter())->write($report, $jsonPath);

    $json = (string) file_get_contents($jsonPath);
    assertContains('"status_codes": {', $json, 'status_codes should be encoded as an object');
    assertContains('"0": {', $json, 'status code 0 should remain an object key');
});

test('Report writers preserve multibyte text and escape html output', function (): void {
    $report = [
        'target' => ['url' => 'https://example.com/検索?name=太郎&team=開発', 'method' => 'GET'],
        'config' => ['requests' => 1, 'concurrency' => 1, 'timeout' => 10, 'target_rps' => null, 'target_tps' => null],
        'summary' => [
            'duration_sec' => 1.0,
            'requests' => ['total' => 1, 'success' => 0, 'failed' => 1, 'success_rate' => 0.0, 'error_rate' => 100.0],
            'throughput' => ['rps' => 1.0, 'tps' => 0.0, 'tps_rps_rate' => 0.0],
            'latency' => ['min' => 1.0, 'avg' => 1.0, 'p50' => 1.0, 'p95' => 1.0, 'p99' => 1.0, 'max' => 1.0],
            'status_codes' => ['500' => ['count' => 1, 'rate' => 100.0]],
        ],
        'errors' => [
            [
                'request' => 1,
                'http_code' => 500,
                'error_no' => 0,
                'error' => '接続に失敗しました: <再試行>',
                'latency_ms' => 1.0,
            ],
        ],
        'meta' => ['tool' => 'eleload', 'version' => '0.1.0'],
    ];

    $tmpDir = sys_get_temp_dir() . '/eleload-tests-multibyte-' . uniqid('', true);
    assertTrue(mkdir($tmpDir, 0775, true), 'Failed to create temp directory');

    $jsonPath = $tmpDir . '/report.json';
    $htmlPath = $tmpDir . '/report.html';

    (new JsonReporter())->write($report, $jsonPath);
    (new HtmlReporter(dirname(__DIR__) . '/templates/report.php'))->write($report, $htmlPath);

    $json = (string) file_get_contents($jsonPath);
    $html = (string) file_get_contents($htmlPath);

    assertContains('検索', $json, 'JSON should preserve multibyte URL text');
    assertContains('接続に失敗しました', $json, 'JSON should preserve multibyte error text');
    assertContains('検索?name=太郎&amp;team=開発', $html, 'HTML should preserve multibyte URL text and escape ampersands');
    assertContains('接続に失敗しました: &lt;再試行&gt;', $html, 'HTML should escape multibyte error text safely');
});

test('ReportPathGenerator creates timestamped report paths', function (): void {
    $paths = (new ReportPathGenerator())->generate('reports', strtotime('2026-05-29 15:30:00'));

    assertSame('reports/eleload-20260529-153000.json', $paths['json']);
    assertSame('reports/eleload-20260529-153000.html', $paths['html']);
    assertSame('reports/eleload-20260529-153000.md', $paths['md']);
});
