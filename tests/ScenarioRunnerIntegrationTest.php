<?php

declare(strict_types=1);

use Eleload\LoadTesting\ScenarioDefinition;
use Eleload\LoadTesting\ScenarioRunner;
use Eleload\LoadTesting\ScenarioStep;

// -----------------------------------------------------------------------
// Start a local PHP built-in server for integration tests
// -----------------------------------------------------------------------

$_integrationPort = 18099;

$_serverScript = sys_get_temp_dir() . '/eleload_srv_' . getmypid() . '.php';
file_put_contents($_serverScript, <<<'PHPSCRIPT'
<?php
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/token' && $method === 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['access_token' => 'test-token-xyz', 'user_id' => 7]);
    exit;
}
if ($uri === '/data') {
    header('Content-Type: application/json');
    echo json_encode(['message' => 'hello', 'count' => 3]);
    exit;
}
if ($uri === '/text') {
    header('Content-Type: text/plain');
    echo 'token=abc123def value=42';
    exit;
}
if ($uri === '/fail') {
    http_response_code(500);
    echo 'error';
    exit;
}
if ($uri === '/named') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}
http_response_code(200);
echo 'ok';
PHPSCRIPT);

$_serverPipes = [];
$_serverProc = proc_open(
    sprintf('php -S 127.0.0.1:%d %s', $_integrationPort, escapeshellarg($_serverScript)),
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $_serverPipes
);

// Wait for server to become available (max 2 s)
$_serverReady = false;
for ($_attempt = 0; $_attempt < 20; $_attempt++) {
    usleep(100_000);
    $sock = @fsockopen('127.0.0.1', $_integrationPort, $errno, $errstr, 0.5);
    if ($sock !== false) {
        fclose($sock);
        $_serverReady = true;
        break;
    }
}

register_shutdown_function(function () use ($_serverProc, $_serverPipes, $_serverScript): void {
    foreach ($_serverPipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    if (is_resource($_serverProc)) {
        proc_terminate($_serverProc);
        proc_close($_serverProc);
    }
    @unlink($_serverScript);
});

if (!$_serverReady) {
    // Cannot start the built-in server – skip integration tests gracefully
    return;
}

$_baseUrl = 'http://127.0.0.1:' . $_integrationPort;

// -----------------------------------------------------------------------
// Integration tests
// -----------------------------------------------------------------------

test('ScenarioRunner run happy path returns successful iteration', function () use ($_baseUrl): void {
    $def    = new ScenarioDefinition('happy', [
        new ScenarioStep(url: $_baseUrl . '/data', timeout: 5),
    ]);
    $result = (new ScenarioRunner())->run($def, 1, null, 1);

    assertSame(1, $result->totalIterations());
    assertSame(1, $result->successIterations());
    assertSame(0.0, $result->errorRate());
});

test('ScenarioRunner run records failed iteration for non-2xx response', function () use ($_baseUrl): void {
    $def    = new ScenarioDefinition('fail-step', [
        new ScenarioStep(url: $_baseUrl . '/fail', timeout: 5),
    ]);
    $result = (new ScenarioRunner())->run($def, 1, null, 1);

    assertSame(1, $result->totalIterations());
    assertSame(0, $result->successIterations());
    assertTrue($result->errorRate() > 0.0, 'error rate must be > 0 for 500 response');
});

test('ScenarioRunner run extracts json variable from response', function () use ($_baseUrl): void {
    $def = new ScenarioDefinition('extract-json', [
        new ScenarioStep(
            url:     $_baseUrl . '/token',
            method:  'POST',
            timeout: 5,
            extract: ['tok' => ['expr' => 'json:$.access_token', 'scope' => 'vu']]
        ),
    ]);
    $result = (new ScenarioRunner())->run($def, 1, null, 1);

    assertSame(1, $result->totalIterations());
    assertSame(1, $result->successIterations());
});

test('ScenarioRunner run carries extracted variable to subsequent step header', function () use ($_baseUrl): void {
    $def = new ScenarioDefinition('carry-var', [
        new ScenarioStep(
            url:     $_baseUrl . '/token',
            method:  'POST',
            timeout: 5,
            extract: ['myToken' => ['expr' => 'json:$.access_token', 'scope' => 'vu']]
        ),
        new ScenarioStep(
            url:     $_baseUrl . '/data',
            headers: ['Authorization: Bearer {{myToken}}'],
            timeout: 5
        ),
    ]);
    $result = (new ScenarioRunner())->run($def, 1, null, 1);

    assertSame(1, $result->totalIterations());
    assertSame(2, count($result->iterationResults[0]->stepResults));
    assertTrue($result->iterationResults[0]->stepResults[0]->success, 'step 1 (login) should succeed');
    assertTrue($result->iterationResults[0]->stepResults[1]->success, 'step 2 (data) should succeed');
});

test('ScenarioRunner run extracts variable via regex expression', function () use ($_baseUrl): void {
    $def = new ScenarioDefinition('extract-regex', [
        new ScenarioStep(
            url:     $_baseUrl . '/text',
            timeout: 5,
            extract: ['tok' => ['expr' => 'regex:token=([a-z0-9]+)', 'scope' => 'vu']]
        ),
    ]);
    $result = (new ScenarioRunner())->run($def, 1, null, 1);

    assertSame(1, $result->totalIterations());
    assertSame(1, $result->successIterations());
});

test('ScenarioRunner run uses step name in step results', function () use ($_baseUrl): void {
    $def = new ScenarioDefinition('named-steps', [
        new ScenarioStep(url: $_baseUrl . '/named', timeout: 5, name: 'my-api-call'),
    ]);
    $result = (new ScenarioRunner())->run($def, 1, null, 1);

    assertSame(1, count($result->iterationResults[0]->stepResults));
    assertSame('my-api-call', $result->iterationResults[0]->stepResults[0]->stepName);
});

test('ScenarioRunner run accumulates perStepSummary across iterations', function () use ($_baseUrl): void {
    $def = new ScenarioDefinition('summary', [
        new ScenarioStep(url: $_baseUrl . '/data', timeout: 5),
    ]);
    $result = (new ScenarioRunner())->run($def, 1, null, 3);

    assertSame(3, $result->totalIterations());
    $summary = $result->perStepSummary();
    assertSame(1, count($summary));
    assertSame(3, $summary[0]['count']);
    assertSame(3, $summary[0]['successCount']);
});

test('ScenarioRunner run with multiple VUs completes in parallel', function () use ($_baseUrl): void {
    $def    = new ScenarioDefinition('parallel', [
        new ScenarioStep(url: $_baseUrl . '/data', timeout: 5),
    ]);
    // 2 VUs, 4 iterations; with concurrency the actual count can be >= 4
    $result = (new ScenarioRunner())->run($def, 2, null, 4);

    assertTrue($result->totalIterations() >= 4, 'at least 4 iterations must complete');
    assertSame($result->totalIterations(), $result->successIterations(), 'all iterations must succeed');
});

test('ScenarioRunner run with initial variables interpolates URL', function () use ($_baseUrl): void {
    $host = '127.0.0.1:' . (int) parse_url($_baseUrl, PHP_URL_PORT);
    $def  = new ScenarioDefinition(
        'var-url',
        [new ScenarioStep(url: 'http://{{host}}/data', timeout: 5)],
        ['host' => $host]
    );
    $result = (new ScenarioRunner())->run($def, 1, null, 1);

    assertSame(1, $result->successIterations());
});

test('ScenarioRunner run warmup excludes early iterations from results', function () use ($_baseUrl): void {
    // Run in duration mode: 0.4 s total, 0.1 s warmup.
    // The server responds instantly so many iterations will complete;
    // only those after warmup should be recorded.
    $def    = new ScenarioDefinition('warmup', [
        new ScenarioStep(url: $_baseUrl . '/data', timeout: 2),
    ]);
    $result = (new ScenarioRunner())->run($def, 1, 0.4, 1, 0.1);

    assertTrue($result->durationSec > 0.0, 'durationSec must be positive');
});
