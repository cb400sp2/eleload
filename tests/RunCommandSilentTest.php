<?php

declare(strict_types=1);

test('run command with silent flag suppresses normal output and still writes reports', function (): void {
    $tmpDir = sys_get_temp_dir() . '/eleload-run-silent-' . uniqid('', true);
    assertTrue(mkdir($tmpDir, 0775, true), 'Failed to create temp directory');

    $jsonPath = $tmpDir . '/report.json';

    $binPath = dirname(__DIR__) . '/bin/eleload';
    $command = sprintf(
        '%s %s run %s --requests=1 --concurrency=1 --timeout=1 --silent --report-json=%s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($binPath),
        escapeshellarg('http://127.0.0.1:1'),
        escapeshellarg($jsonPath)
    );

    exec($command, $outputLines, $exitCode);
    $output = implode("\n", $outputLines);

    assertSame(1, $exitCode, 'run command should fail for unreachable local endpoint');
    assertTrue(is_file($jsonPath), 'JSON report should be generated in silent mode');
    assertSame('', trim($output), 'silent mode should suppress normal output');
});
