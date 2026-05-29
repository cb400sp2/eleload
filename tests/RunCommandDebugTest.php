<?php

declare(strict_types=1);

test('run command with debug flag prints parsed options and execution plan even in silent mode', function (): void {
    $tmpDir = sys_get_temp_dir() . '/eleload-run-debug-' . uniqid('', true);
    assertTrue(mkdir($tmpDir, 0775, true), 'Failed to create temp directory');

    $jsonPath = $tmpDir . '/report.json';

    $binPath = dirname(__DIR__) . '/bin/eleload';
    $command = sprintf(
        '%s %s run %s --requests=1 --concurrency=1 --timeout=1 --silent --debug --report-json=%s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($binPath),
        escapeshellarg('http://127.0.0.1:1'),
        escapeshellarg($jsonPath)
    );

    exec($command, $outputLines, $exitCode);
    $output = implode("\n", $outputLines);

    assertSame(1, $exitCode, 'run command should fail for unreachable local endpoint');
    assertTrue(is_file($jsonPath), 'JSON report should still be generated in debug mode');
    assertContains('Parsed Options:', $output, 'debug mode must print parsed options');
    assertContains('"debug": true', $output, 'debug mode must show the parsed debug flag');
    assertContains('Execution Plan:', $output, 'debug mode must print the execution plan');
    assertContains('Request Mode', $output, 'debug mode must describe the request mode');
    assertContains('Output Targets', $output, 'debug mode must describe output targets');
    assertTrue(!str_contains($output, 'HTTP Load Test Result'), 'silent mode should still suppress the normal report');
});