<?php

declare(strict_types=1);

test('compare command creates html and markdown reports', function (): void {
    $tmpDir = sys_get_temp_dir() . '/eleload-compare-cmd-' . uniqid('', true);
    assertTrue(mkdir($tmpDir, 0775, true), 'Failed to create temp directory');

    $beforePath = $tmpDir . '/before.json';
    $afterPath = $tmpDir . '/after.json';
    $htmlPath = $tmpDir . '/compare.html';
    $mdPath = $tmpDir . '/compare.md';

    $before = [
        'target' => ['url' => 'https://example.com/検索?team=開発', 'method' => 'GET'],
        'summary' => [
            'throughput' => ['rps' => 100.0, 'tps' => 95.0],
            'latency' => ['p95' => 220.0, 'p99' => 400.0],
            'requests' => ['error_rate' => 2.0],
        ],
        'meta' => ['tool' => 'eleload', 'version' => '0.1.0', 'test_name' => '比較前'],
    ];
    $after = [
        'target' => ['url' => 'https://example.com/検索?team=開発', 'method' => 'GET'],
        'summary' => [
            'throughput' => ['rps' => 120.0, 'tps' => 90.0],
            'latency' => ['p95' => 180.0, 'p99' => 430.0],
            'requests' => ['error_rate' => 1.0],
        ],
        'meta' => ['tool' => 'eleload', 'version' => '0.1.0', 'test_name' => '比較後'],
    ];

    file_put_contents(
        $beforePath,
        json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
    );
    file_put_contents(
        $afterPath,
        json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
    );

    $binPath = dirname(__DIR__) . '/bin/eleload';
    $command = sprintf(
        '%s %s compare %s %s --html=%s --md=%s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($binPath),
        escapeshellarg($beforePath),
        escapeshellarg($afterPath),
        escapeshellarg($htmlPath),
        escapeshellarg($mdPath)
    );

    exec($command, $outputLines, $exitCode);
    $output = implode("\n", $outputLines);

    assertSame(0, $exitCode, 'compare command should exit successfully');
    assertTrue(is_file($htmlPath), 'Comparison HTML was not created');
    assertTrue(is_file($mdPath), 'Comparison Markdown was not created');
    assertContains('HTML comparison report:', $output);
    assertContains('Markdown comparison report:', $output);

    $html = (string) file_get_contents($htmlPath);
    $markdown = (string) file_get_contents($mdPath);

    assertContains('比較前', $html, 'HTML should preserve multibyte test name');
    assertContains('比較後', $html, 'HTML should preserve multibyte test name');
    assertContains('Improved', $html, 'HTML should show improved metric status');
    assertContains('Regressed', $html, 'HTML should show regressed metric status');
    assertContains('比較前', $markdown, 'Markdown should preserve multibyte test name');
    assertContains('| RPS | 100.00 | 120.00 | +20.00 |', $markdown);
    assertContains('IMPROVED', $markdown);
    assertContains('REGRESSED', $markdown);
});

test('compare command supports html output only', function (): void {
    $tmpDir = sys_get_temp_dir() . '/eleload-compare-html-only-' . uniqid('', true);
    assertTrue(mkdir($tmpDir, 0775, true), 'Failed to create temp directory');

    $beforePath = $tmpDir . '/before.json';
    $afterPath = $tmpDir . '/after.json';
    $htmlPath = $tmpDir . '/compare.html';

    $base = [
        'target' => ['url' => 'https://example.com', 'method' => 'GET'],
        'summary' => [
            'throughput' => ['rps' => 100.0, 'tps' => 100.0],
            'latency' => ['p95' => 200.0, 'p99' => 300.0],
            'requests' => ['error_rate' => 1.0],
        ],
        'meta' => ['tool' => 'eleload', 'version' => '0.1.0', 'test_name' => 'baseline'],
    ];

    file_put_contents(
        $beforePath,
        json_encode($base, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
    );
    file_put_contents(
        $afterPath,
        json_encode($base, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
    );

    $binPath = dirname(__DIR__) . '/bin/eleload';
    $command = sprintf(
        '%s %s compare %s %s --html=%s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($binPath),
        escapeshellarg($beforePath),
        escapeshellarg($afterPath),
        escapeshellarg($htmlPath)
    );

    exec($command, $outputLines, $exitCode);
    $output = implode("\n", $outputLines);

    assertSame(0, $exitCode, 'compare command should exit successfully with html only');
    assertTrue(is_file($htmlPath), 'Comparison HTML was not created');
    assertContains('HTML comparison report:', $output);
});
