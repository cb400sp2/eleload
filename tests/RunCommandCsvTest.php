<?php

declare(strict_types=1);

test('run command writes csv report when report-csv option is provided', function (): void {
    $tmpDir = sys_get_temp_dir() . '/eleload-run-csv-' . uniqid('', true);
    assertTrue(mkdir($tmpDir, 0775, true), 'Failed to create temp directory');

    $csvPath = $tmpDir . '/results.csv';

    $binPath = dirname(__DIR__) . '/bin/eleload';
    $command = sprintf(
        '%s %s run %s --requests=1 --concurrency=1 --timeout=1 --report-csv=%s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($binPath),
        escapeshellarg('http://127.0.0.1:1'),
        escapeshellarg($csvPath)
    );

    exec($command, $outputLines, $exitCode);
    $output = implode("\n", $outputLines);

    assertSame(1, $exitCode, 'run command should fail for unreachable local endpoint');
    assertTrue(is_file($csvPath), 'CSV report file was not created');
    assertContains('CSV report:', $output, 'run command output must mention csv output path');

    $csv = (string) file_get_contents($csvPath);
    assertContains('request,included_in_metrics,success,http_code,error_no,latency_ms,download_bytes,body_contains_expected,error', $csv);
});

