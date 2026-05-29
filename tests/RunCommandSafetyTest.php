<?php

declare(strict_types=1);

test('run command blocks high-load settings without explicit confirmation in non-interactive mode', function (): void {
    $binPath = dirname(__DIR__) . '/bin/eleload';
    $command = sprintf(
        '%s %s run %s --requests=10001 --concurrency=1 --timeout=1 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($binPath),
        escapeshellarg('https://example.com')
    );

    exec($command, $outputLines, $exitCode);
    $output = implode("\n", $outputLines);

    assertSame(1, $exitCode, 'high-load run should fail without explicit confirmation');
    assertContains('High-load settings detected', $output);
    assertContains('--yes', $output);
    assertContains('--allow-high-load', $output);
});

test('run command allows high-load settings with allow-high-load override', function (): void {
    $tmpDir = sys_get_temp_dir() . '/eleload-safety-allow-' . uniqid('', true);
    assertTrue(mkdir($tmpDir, 0775, true), 'Failed to create temp directory');
    $jsonPath = $tmpDir . '/report.json';

    $binPath = dirname(__DIR__) . '/bin/eleload';
    $command = sprintf(
        '%s %s run %s --requests=1 --concurrency=501 --timeout=1 --allow-high-load --silent --report-json=%s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($binPath),
        escapeshellarg('http://127.0.0.1:1'),
        escapeshellarg($jsonPath)
    );

    exec($command, $outputLines, $exitCode);
    $output = implode("\n", $outputLines);

    assertSame(1, $exitCode, 'run should proceed and fail only due to unreachable endpoint');
    assertTrue(is_file($jsonPath), 'report should still be generated with allow-high-load');
    assertSame('', trim($output), 'silent mode should suppress output when high-load override is used');
});

