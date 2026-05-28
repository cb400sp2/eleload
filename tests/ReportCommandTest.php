<?php

declare(strict_types=1);

test('report command regenerates html from json report', function (): void {
    $tmpDir = sys_get_temp_dir() . '/eleload-report-cmd-' . uniqid('', true);
    assertTrue(mkdir($tmpDir, 0775, true), 'Failed to create temp directory');

    $jsonPath = $tmpDir . '/input.json';
    $htmlPath = $tmpDir . '/output.html';

    $report = [
        'target' => ['url' => 'https://example.com', 'method' => 'GET'],
        'config' => ['requests' => 1, 'concurrency' => 1, 'timeout' => 10, 'target_rps' => null, 'target_tps' => null],
        'summary' => [
            'duration_sec' => 0.1,
            'requests' => ['total' => 1, 'success' => 1, 'failed' => 0, 'success_rate' => 100.0, 'error_rate' => 0.0],
            'throughput' => ['rps' => 10.0, 'tps' => 10.0, 'tps_rps_rate' => 100.0],
            'latency' => ['min' => 10.0, 'avg' => 10.0, 'p50' => 10.0, 'p95' => 10.0, 'p99' => 10.0, 'max' => 10.0],
            'status_codes' => ['200' => ['count' => 1, 'rate' => 100.0]],
        ],
        'errors' => [],
        'meta' => ['tool' => 'eleload', 'version' => '0.1.0'],
    ];

    file_put_contents(
        $jsonPath,
        json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
    );

    $binPath = dirname(__DIR__) . '/bin/phpload';
    $command = sprintf(
        '%s %s report %s --html=%s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($binPath),
        escapeshellarg($jsonPath),
        escapeshellarg($htmlPath)
    );

    exec($command, $outputLines, $exitCode);
    $output = implode("\n", $outputLines);

    assertSame(0, $exitCode, 'report command should exit successfully');
    assertTrue(is_file($htmlPath), 'Report HTML file was not created');
    assertContains('HTML report:', $output, 'report command output must mention output path');
});

test('report command preserves multibyte text from json report', function (): void {
    $tmpDir = sys_get_temp_dir() . '/eleload-report-cmd-multibyte-' . uniqid('', true);
    assertTrue(mkdir($tmpDir, 0775, true), 'Failed to create temp directory');

    $jsonPath = $tmpDir . '/input.json';
    $htmlPath = $tmpDir . '/output.html';

    $report = [
        'target' => ['url' => 'https://example.com/検索?name=太郎', 'method' => 'GET'],
        'config' => ['requests' => 1, 'concurrency' => 1, 'timeout' => 10, 'target_rps' => null, 'target_tps' => null],
        'summary' => [
            'duration_sec' => 0.1,
            'requests' => ['total' => 1, 'success' => 0, 'failed' => 1, 'success_rate' => 0.0, 'error_rate' => 100.0],
            'throughput' => ['rps' => 10.0, 'tps' => 0.0, 'tps_rps_rate' => 0.0],
            'latency' => ['min' => 10.0, 'avg' => 10.0, 'p50' => 10.0, 'p95' => 10.0, 'p99' => 10.0, 'max' => 10.0],
            'status_codes' => ['500' => ['count' => 1, 'rate' => 100.0]],
        ],
        'errors' => [
            ['request' => 1, 'http_code' => 500, 'error_no' => 0, 'error' => '応答が不正です', 'latency_ms' => 10.0],
        ],
        'meta' => ['tool' => 'eleload', 'version' => '0.1.0'],
    ];

    file_put_contents(
        $jsonPath,
        json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
    );

    $binPath = dirname(__DIR__) . '/bin/phpload';
    $command = sprintf(
        '%s %s report %s --html=%s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($binPath),
        escapeshellarg($jsonPath),
        escapeshellarg($htmlPath)
    );

    exec($command, $outputLines, $exitCode);

    assertSame(0, $exitCode, 'report command should exit successfully');
    assertTrue(is_file($htmlPath), 'Report HTML file was not created');

    $html = (string) file_get_contents($htmlPath);
    assertContains('検索?name=太郎', $html, 'HTML should preserve multibyte URL from JSON');
    assertContains('応答が不正です', $html, 'HTML should preserve multibyte error text from JSON');
});
