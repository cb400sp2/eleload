<?php

declare(strict_types=1);

test('run command writes partial report when interrupted by SIGINT', function (): void {
    if (!function_exists('pcntl_signal') || !defined('SIGINT')) {
        // Skip gracefully when signal handling is unavailable.
        return;
    }

    $tmpDir = sys_get_temp_dir() . '/eleload-run-partial-' . uniqid('', true);
    assertTrue(mkdir($tmpDir, 0775, true), 'Failed to create temp directory');

    $serverScript = $tmpDir . '/server.php';
    $jsonPath = $tmpDir . '/report.json';

    file_put_contents($serverScript, <<<'PHPSCRIPT'
<?php
usleep(250000);
http_response_code(200);
echo 'ok';
PHPSCRIPT);

    $port = random_int(20000, 29999);
    $serverProc = proc_open(
        sprintf('%s -S 127.0.0.1:%d %s', escapeshellarg(PHP_BINARY), $port, escapeshellarg($serverScript)),
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $serverPipes
    );

    assertTrue(is_resource($serverProc), 'Failed to start test HTTP server');

    $serverReady = false;
    for ($attempt = 0; $attempt < 20; $attempt++) {
        usleep(100_000);
        $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);
        if ($sock !== false) {
            fclose($sock);
            $serverReady = true;
            break;
        }
    }

    assertTrue($serverReady, 'HTTP test server did not become ready');

    $binPath = dirname(__DIR__) . '/bin/eleload';
    $runProc = proc_open(
        [
            PHP_BINARY,
            $binPath,
            'run',
            'http://127.0.0.1:' . $port . '/',
            '--requests=50',
            '--concurrency=1',
            '--timeout=2',
            '--report-json=' . $jsonPath,
        ],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $runPipes
    );

    assertTrue(is_resource($runProc), 'Failed to start eleload run process');

    usleep(300_000);
    proc_terminate($runProc, SIGINT);

    $stdout = stream_get_contents($runPipes[1]);
    $stderr = stream_get_contents($runPipes[2]);

    foreach ($runPipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }

    $exitCode = proc_close($runProc);

    foreach ($serverPipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    proc_terminate($serverProc);
    proc_close($serverProc);

    assertSame(1, $exitCode, 'SIGINT-interrupted run should return non-zero exit code');
    assertTrue(is_file($jsonPath), 'Partial JSON report should be generated');

    $json = (string) file_get_contents($jsonPath);
    $report = json_decode($json, true);

    assertSame(true, is_array($report), 'JSON report must decode into an array');
    assertSame(true, $report['meta']['partial'] ?? false, 'meta.partial should be true for interrupted run');
    assertSame('sigint', $report['meta']['termination_reason'] ?? null, 'termination reason should be sigint');

    $combinedOutput = trim($stdout . "\n" . $stderr);
    assertContains('partial report', strtolower($combinedOutput));
});
