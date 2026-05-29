<?php

declare(strict_types=1);

test('run command with verbose flag prints detailed errors and slowest requests', function (): void {
    $binPath = dirname(__DIR__) . '/bin/eleload';
    $command = sprintf(
        '%s %s run %s --requests=3 --concurrency=1 --timeout=1 --verbose 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($binPath),
        escapeshellarg('http://127.0.0.1:1')
    );

    exec($command, $outputLines, $exitCode);
    $output = implode("\n", $outputLines);

    assertSame(1, $exitCode, 'run command should fail for unreachable local endpoint');
    assertContains('Errors (detailed)', $output);
    assertContains('success=no', $output);
    assertContains('bytes=', $output);
    assertContains('body_match=n/a', $output);
    assertContains('Slowest Requests', $output);
});

