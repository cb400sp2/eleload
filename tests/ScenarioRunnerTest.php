<?php

declare(strict_types=1);

use Eleload\LoadTesting\ScenarioRunner;

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
