<?php

declare(strict_types=1);

use Eleload\LoadTesting\CurlMultiRunner;
use Eleload\LoadTesting\RequestOptions;
use Eleload\Metrics\StatisticsCalculator;

$_gracefulPort = 18109;
$_gracefulServerScript = sys_get_temp_dir() . '/eleload_graceful_srv_' . getmypid() . '.php';

file_put_contents($_gracefulServerScript, <<<'PHPSCRIPT'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/slow') {
    usleep(200000);
}
http_response_code(200);
echo 'ok';
PHPSCRIPT);

$_gracefulPipes = [];
$_gracefulProc = proc_open(
    sprintf('%s -S 127.0.0.1:%d %s', escapeshellarg(PHP_BINARY), $_gracefulPort, escapeshellarg($_gracefulServerScript)),
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $_gracefulPipes
);

$_gracefulReady = false;
for ($_attempt = 0; $_attempt < 20; $_attempt++) {
    usleep(100_000);
    $sock = @fsockopen('127.0.0.1', $_gracefulPort, $errno, $errstr, 0.5);
    if ($sock !== false) {
        fclose($sock);
        $_gracefulReady = true;
        break;
    }
}

register_shutdown_function(function () use ($_gracefulProc, $_gracefulPipes, $_gracefulServerScript): void {
    foreach ($_gracefulPipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    if (is_resource($_gracefulProc)) {
        proc_terminate($_gracefulProc);
        proc_close($_gracefulProc);
    }
    @unlink($_gracefulServerScript);
});

if (!$_gracefulReady) {
    // Skip gracefully when local integration server cannot start.
    return;
}

$_gracefulBaseUrl = 'http://127.0.0.1:' . $_gracefulPort;

test('CurlMultiRunner returns partial result when external stop is requested', function () use ($_gracefulBaseUrl): void {
    $options = new RequestOptions(
        url: $_gracefulBaseUrl . '/slow',
        requests: 50,
        concurrency: 1,
        method: 'GET',
        timeout: 2
    );

    $runner = new CurlMultiRunner(1);
    $stopAtNs = hrtime(true) + 220_000_000;

    $runResult = $runner->run(
        $options,
        null,
        static function () use ($stopAtNs): ?string {
            return hrtime(true) >= $stopAtNs ? 'sigint' : null;
        }
    );

    assertSame(true, $runResult->isPartial());
    assertSame('sigint', $runResult->terminationReason());
    assertSame(true, $runResult->countRequestResults() >= 1);
    assertSame(true, $runResult->countRequestResults() < 50);
    assertSame(true, $runResult->hasSpilledRequestResults());

    $report = (new StatisticsCalculator())->summarize($runResult);
    assertSame(true, $report['meta']['partial']);
    assertSame('sigint', $report['meta']['termination_reason']);
});

test('CurlMultiRunner stops early under memory pressure threshold', function () use ($_gracefulBaseUrl): void {
    $options = new RequestOptions(
        url: $_gracefulBaseUrl . '/slow',
        requests: 10,
        concurrency: 1,
        method: 'GET',
        timeout: 2
    );

    // Set threshold to 1 byte so memory pressure guard triggers immediately.
    $runner = new CurlMultiRunner(10_000, 1);
    $runResult = $runner->run($options);

    assertSame(true, $runResult->isPartial());
    assertSame('memory_pressure', $runResult->terminationReason());

    $report = (new StatisticsCalculator())->summarize($runResult);
    assertSame(true, $report['meta']['partial']);
    assertSame('memory_pressure', $report['meta']['termination_reason']);
});
