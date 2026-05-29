<?php

declare(strict_types=1);

use Eleload\LoadTesting\ScenarioLoader;

// -----------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------

function scenarioJson(array $data): string
{
    $file = sys_get_temp_dir() . '/eleload_scenario_' . uniqid() . '.json';
    file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR));
    return $file;
}

// -----------------------------------------------------------------------
// Tests
// -----------------------------------------------------------------------

test('ScenarioLoader loads a minimal scenario', function (): void {
    $file = scenarioJson([
        'name' => 'My Scenario',
        'steps' => [
            ['url' => 'https://example.com/api', 'method' => 'GET'],
        ],
    ]);

    $loader = new ScenarioLoader();
    $def = $loader->load($file);

    assertSame('My Scenario', $def->name);
    assertSame(1, count($def->steps));
    assertSame('https://example.com/api', $def->steps[0]->url);
    assertSame('GET', $def->steps[0]->method);
});

test('ScenarioLoader defaults name to "Unnamed Scenario" when absent', function (): void {
    $file = scenarioJson([
        'steps' => [
            ['url' => 'https://example.com'],
        ],
    ]);

    $def = (new ScenarioLoader())->load($file);
    assertSame('Unnamed Scenario', $def->name);
});

test('ScenarioLoader loads variables', function (): void {
    $file = scenarioJson([
        'name' => 'With Vars',
        'variables' => ['baseUrl' => 'https://example.com', 'token' => 'abc123'],
        'steps' => [
            ['url' => '{{baseUrl}}/api'],
        ],
    ]);

    $def = (new ScenarioLoader())->load($file);
    assertSame('https://example.com', $def->variables['baseUrl']);
    assertSame('abc123', $def->variables['token']);
});

test('ScenarioLoader normalises method to uppercase', function (): void {
    $file = scenarioJson([
        'steps' => [['url' => 'https://example.com', 'method' => 'post']],
    ]);

    $def = (new ScenarioLoader())->load($file);
    assertSame('POST', $def->steps[0]->method);
});

test('ScenarioLoader converts object body to JSON string', function (): void {
    $file = scenarioJson([
        'steps' => [
            [
                'url' => 'https://example.com',
                'method' => 'POST',
                'body' => ['key' => 'value', 'count' => 5],
            ],
        ],
    ]);

    $def = (new ScenarioLoader())->load($file);
    $decoded = json_decode($def->steps[0]->body ?? '', true);
    assertSame('value', $decoded['key']);
    assertSame(5, $decoded['count']);
});

test('ScenarioLoader parses valid extract expressions', function (): void {
    $file = scenarioJson([
        'steps' => [
            [
                'url' => 'https://example.com',
                'extract' => [
                    'userId' => 'json:$.data.id',
                    'token' => 'regex:"token":"([^"]+)"',
                ],
            ],
        ],
    ]);

    $def = (new ScenarioLoader())->load($file);
    assertSame('json:$.data.id', $def->steps[0]->extract['userId']);
    assertSame('regex:"token":"([^"]+)"', $def->steps[0]->extract['token']);
});

test('ScenarioLoader parses step headers array', function (): void {
    $file = scenarioJson([
        'steps' => [
            [
                'url' => 'https://example.com',
                'headers' => ['Content-Type: application/json', 'X-Api-Key: secret'],
            ],
        ],
    ]);

    $def = (new ScenarioLoader())->load($file);
    assertSame(['Content-Type: application/json', 'X-Api-Key: secret'], $def->steps[0]->headers);
});

test('ScenarioLoader throws when file does not exist', function (): void {
    assertThrows(
        fn () => (new ScenarioLoader())->load('/nonexistent/path/scenario.json'),
        RuntimeException::class,
        'not found'
    );
});

test('ScenarioLoader throws when steps are missing', function (): void {
    $file = scenarioJson(['name' => 'No Steps']);

    assertThrows(
        fn () => (new ScenarioLoader())->load($file),
        InvalidArgumentException::class,
        'at least one step'
    );
});

test('ScenarioLoader throws when steps array is empty', function (): void {
    $file = scenarioJson(['steps' => []]);

    assertThrows(
        fn () => (new ScenarioLoader())->load($file),
        InvalidArgumentException::class,
        'at least one step'
    );
});

test('ScenarioLoader throws for invalid method', function (): void {
    $file = scenarioJson([
        'steps' => [['url' => 'https://example.com', 'method' => 'HACK']],
    ]);

    assertThrows(
        fn () => (new ScenarioLoader())->load($file),
        InvalidArgumentException::class,
        'Invalid method'
    );
});

test('ScenarioLoader throws for extract expression without prefix', function (): void {
    $file = scenarioJson([
        'steps' => [
            [
                'url' => 'https://example.com',
                'extract' => ['userId' => '$.data.id'],
            ],
        ],
    ]);

    assertThrows(
        fn () => (new ScenarioLoader())->load($file),
        InvalidArgumentException::class,
        "json:' or 'regex:"
    );
});

test('ScenarioLoader throws when step url is missing', function (): void {
    $file = scenarioJson([
        'steps' => [['method' => 'GET']],
    ]);

    assertThrows(
        fn () => (new ScenarioLoader())->load($file),
        InvalidArgumentException::class,
        "'url' is required"
    );
});

test('ScenarioLoader throws when JSON is invalid', function (): void {
    $file = sys_get_temp_dir() . '/eleload_invalid_' . uniqid() . '.json';
    file_put_contents($file, '{invalid json}');

    assertThrows(
        fn () => (new ScenarioLoader())->load($file),
        InvalidArgumentException::class,
        'Invalid JSON'
    );
});

// ---------------------------------------------------------------------------
// YAML / extension detection
// ---------------------------------------------------------------------------

test('ScenarioLoader rejects unsupported file extension', function (): void {
    $file = sys_get_temp_dir() . '/eleload_test_' . uniqid() . '.toml';
    file_put_contents($file, '[scenario]');

    assertThrows(
        fn () => (new ScenarioLoader())->load($file),
        InvalidArgumentException::class,
        'Unsupported scenario file extension'
    );

    @unlink($file);
});

test('ScenarioLoader rejects file that does not exist', function (): void {
    assertThrows(
        fn () => (new ScenarioLoader())->load('/nonexistent/path/scenario.json'),
        RuntimeException::class,
        'not found'
    );
});

test('ScenarioLoader loads JSON via examples/scenarios/simple-get.json', function (): void {
    $loader = new ScenarioLoader();
    $def = $loader->load(__DIR__ . '/../examples/scenarios/simple-get.json');

    assertSame('Simple GET', $def->name);
    assertSame(1, count($def->steps));
    assertSame('GET', $def->steps[0]->method);
});

test('ScenarioLoader loads JSON via examples/scenarios/login-then-fetch.json', function (): void {
    $loader = new ScenarioLoader();
    $def = $loader->load(__DIR__ . '/../examples/scenarios/login-then-fetch.json');

    assertSame('Login Then Fetch', $def->name);
    assertSame(2, count($def->steps));
    assertSame('POST', $def->steps[0]->method);
    assertSame('GET', $def->steps[1]->method);
});

// -----------------------------------------------------------------------
// Additional field parsing
// -----------------------------------------------------------------------

test('ScenarioLoader parses step name', function (): void {
    $file = scenarioJson([
        'steps' => [['url' => 'https://example.com', 'name' => 'login step']],
    ]);

    $def = (new ScenarioLoader())->load($file);
    assertSame('login step', $def->steps[0]->name);
});

test('ScenarioLoader parses step timeout', function (): void {
    $file = scenarioJson([
        'steps' => [['url' => 'https://example.com', 'timeout' => 30]],
    ]);

    $def = (new ScenarioLoader())->load($file);
    assertSame(30, $def->steps[0]->timeout);
});

test('ScenarioLoader parses connect_timeout', function (): void {
    $file = scenarioJson([
        'steps' => [['url' => 'https://example.com', 'connect_timeout' => 3]],
    ]);

    $def = (new ScenarioLoader())->load($file);
    assertSame(3, $def->steps[0]->connectTimeout);
});

test('ScenarioLoader parses wait_ms', function (): void {
    $file = scenarioJson([
        'steps' => [['url' => 'https://example.com', 'wait_ms' => 500]],
    ]);

    $def = (new ScenarioLoader())->load($file);
    assertSame(500, $def->steps[0]->waitMs);
});

test('ScenarioLoader parses follow_redirects true', function (): void {
    $file = scenarioJson([
        'steps' => [['url' => 'https://example.com', 'follow_redirects' => true]],
    ]);

    $def = (new ScenarioLoader())->load($file);
    assertTrue($def->steps[0]->followRedirects);
});

test('ScenarioLoader throws for non-integer timeout', function (): void {
    $file = scenarioJson([
        'steps' => [['url' => 'https://example.com', 'timeout' => 'fast']],
    ]);

    assertThrows(
        fn () => (new ScenarioLoader())->load($file),
        InvalidArgumentException::class,
        'timeout'
    );
});

test('ScenarioLoader throws for invalid connect_timeout', function (): void {
    $file = scenarioJson([
        'steps' => [['url' => 'https://example.com', 'connect_timeout' => 0]],
    ]);

    assertThrows(
        fn () => (new ScenarioLoader())->load($file),
        InvalidArgumentException::class,
        'connect_timeout'
    );
});

test('ScenarioLoader throws for negative wait_ms', function (): void {
    $file = scenarioJson([
        'steps' => [['url' => 'https://example.com', 'wait_ms' => -1]],
    ]);

    assertThrows(
        fn () => (new ScenarioLoader())->load($file),
        InvalidArgumentException::class,
        'wait_ms'
    );
});

test('ScenarioLoader throws for non-boolean follow_redirects', function (): void {
    $file = scenarioJson([
        'steps' => [['url' => 'https://example.com', 'follow_redirects' => 1]],
    ]);

    assertThrows(
        fn () => (new ScenarioLoader())->load($file),
        InvalidArgumentException::class,
        'follow_redirects'
    );
});

test('ScenarioLoader loads multiple steps', function (): void {
    $file = scenarioJson([
        'steps' => [
            ['url' => 'https://example.com/a'],
            ['url' => 'https://example.com/b', 'method' => 'POST'],
            ['url' => 'https://example.com/c', 'method' => 'DELETE'],
        ],
    ]);

    $def = (new ScenarioLoader())->load($file);
    assertSame(3, count($def->steps));
    assertSame('GET', $def->steps[0]->method);
    assertSame('POST', $def->steps[1]->method);
    assertSame('DELETE', $def->steps[2]->method);
});
