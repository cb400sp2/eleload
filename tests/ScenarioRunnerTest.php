<?php

declare(strict_types=1);

use Eleload\LoadTesting\ScenarioDefinition;
use Eleload\LoadTesting\ScenarioRunner;
use Eleload\LoadTesting\ScenarioStep;

// -----------------------------------------------------------------------
// interpolate() – public helper
// -----------------------------------------------------------------------

test('ScenarioRunner interpolates template variables', function (): void {
    $runner = new ScenarioRunner();

    $result = $runner->interpolate(
        'https://{{host}}/api/{{version}}/users',
        ['host' => 'example.com', 'version' => 'v2']
    );

    assertSame('https://example.com/api/v2/users', $result);
});

test('ScenarioRunner interpolate leaves unknown placeholders intact', function (): void {
    $runner = new ScenarioRunner();

    $result = $runner->interpolate('Bearer {{token}}', []);

    assertSame('Bearer {{token}}', $result);
});

test('ScenarioRunner interpolate handles string with no placeholders', function (): void {
    $runner = new ScenarioRunner();

    $result = $runner->interpolate('https://example.com', ['host' => 'ignored']);

    assertSame('https://example.com', $result);
});

test('ScenarioRunner interpolate handles empty string', function (): void {
    $runner = new ScenarioRunner();

    assertSame('', $runner->interpolate('', ['x' => '1']));
});

// -----------------------------------------------------------------------
// interpolate() – ${var} syntax (#83)
// -----------------------------------------------------------------------

test('ScenarioRunner interpolate supports ${varName} syntax', function (): void {
    $runner = new ScenarioRunner();

    $result = $runner->interpolate(
        'https://${host}/api/${version}/users',
        ['host' => 'example.com', 'version' => 'v2']
    );

    assertSame('https://example.com/api/v2/users', $result);
});

test('ScenarioRunner interpolate ${varName} leaves unknown placeholders intact', function (): void {
    $runner = new ScenarioRunner();

    assertSame('Bearer ${token}', $runner->interpolate('Bearer ${token}', []));
});

test('ScenarioRunner interpolate merges global and per-VU variables', function (): void {
    $runner = new ScenarioRunner();

    // per-VU takes precedence over global
    $result = $runner->interpolate(
        '{{a}}-${b}',
        ['a' => 'vu-a', 'b' => 'vu-b'],
        ['a' => 'global-a', 'b' => 'global-b']
    );

    assertSame('vu-a-vu-b', $result);
});

test('ScenarioRunner interpolate falls back to global when per-VU missing', function (): void {
    $runner = new ScenarioRunner();

    $result = $runner->interpolate('${token}', [], ['token' => 'global-token']);

    assertSame('global-token', $result);
});

// -----------------------------------------------------------------------
// run() – argument validation
// -----------------------------------------------------------------------

test('ScenarioRunner run throws for concurrency less than 1', function (): void {
    $def = new ScenarioDefinition('test', [new ScenarioStep('http://127.0.0.1:1')]);

    assertThrows(
        fn () => (new ScenarioRunner())->run($def, 0, null, 1),
        InvalidArgumentException::class,
        'concurrency'
    );
});

test('ScenarioRunner run throws when iterations is zero and no duration', function (): void {
    $def = new ScenarioDefinition('test', [new ScenarioStep('http://127.0.0.1:1')]);

    assertThrows(
        fn () => (new ScenarioRunner())->run($def, 1, null, 0),
        InvalidArgumentException::class
    );
});

// -----------------------------------------------------------------------
// run() – with unreachable URL (fast connection-refused)
// -----------------------------------------------------------------------

test('ScenarioRunner run records failed iteration for unreachable URL', function (): void {
    $def = new ScenarioDefinition('fail', [
        new ScenarioStep(url: 'http://127.0.0.1:1', timeout: 1),
    ]);

    $result = (new ScenarioRunner())->run($def, 1, null, 1);

    assertSame(1, $result->totalIterations());
    assertSame(0, $result->successIterations());
    assertTrue($result->errorRate() > 0.0, 'error rate must be > 0 for failed iteration');
});

test('ScenarioRunner run counts iterations correctly', function (): void {
    $def = new ScenarioDefinition('count', [
        new ScenarioStep(url: 'http://127.0.0.1:1', timeout: 1),
    ]);

    $result = (new ScenarioRunner())->run($def, 1, null, 3);

    assertSame(3, $result->totalIterations());
    assertSame(0, $result->successIterations());
});

test('ScenarioRunner run with multiple VUs completes requested iterations', function (): void {
    $def = new ScenarioDefinition('parallel', [
        new ScenarioStep(url: 'http://127.0.0.1:1', timeout: 1),
    ]);

    // 3 VUs, 3 total iterations; with concurrency the actual count can be >= 3
    $result = (new ScenarioRunner())->run($def, 3, null, 3);

    assertTrue($result->totalIterations() >= 3, 'at least 3 iterations must complete');
});

test('ScenarioRunner run returns positive durationSec', function (): void {
    $def = new ScenarioDefinition('dur', [
        new ScenarioStep(url: 'http://127.0.0.1:1', timeout: 1),
    ]);

    $result = (new ScenarioRunner())->run($def, 1, null, 1);

    assertTrue($result->durationSec > 0.0);
});

test('ScenarioRunner run perStepSummary has one entry per step', function (): void {
    $def = new ScenarioDefinition('steps', [
        new ScenarioStep(url: 'http://127.0.0.1:1', timeout: 1),
        new ScenarioStep(url: 'http://127.0.0.1:1', timeout: 1),
    ]);

    $result = (new ScenarioRunner())->run($def, 1, null, 1);
    $summary = $result->perStepSummary();

    assertSame(2, count($summary));
});

test('ScenarioRunner perStepSummary includes errorRate failedCount p50 p99', function (): void {
    $def = new ScenarioDefinition('metrics', [
        new ScenarioStep(url: 'http://127.0.0.1:1', timeout: 1),
    ]);

    $result = (new ScenarioRunner())->run($def, 1, null, 1);
    $summary = $result->perStepSummary();

    assertSame(1, count($summary));

    $step = $summary[0];
    assertTrue(array_key_exists('errorRate', $step), 'errorRate key must exist');
    assertTrue(array_key_exists('failedCount', $step), 'failedCount key must exist');
    assertTrue(array_key_exists('successRate', $step), 'successRate key must exist');
    assertTrue(array_key_exists('p50Ms', $step), 'p50Ms key must exist');
    assertTrue(array_key_exists('p99Ms', $step), 'p99Ms key must exist');
});
