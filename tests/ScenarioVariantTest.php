<?php

declare(strict_types=1);

use Eleload\LoadTesting\ScenarioDefinition;
use Eleload\LoadTesting\ScenarioLoader;
use Eleload\LoadTesting\ScenarioRunner;
use Eleload\LoadTesting\ScenarioStep;
use Eleload\LoadTesting\ScenarioVariant;

$stepA = new ScenarioStep(url: 'http://127.0.0.1:9999/a');
$stepB = new ScenarioStep(url: 'http://127.0.0.1:9999/b');

// ---- ScenarioVariant constructor ----
test('ScenarioVariant stores name, weight, and steps', function () use ($stepA): void {
    $v = new ScenarioVariant(name: 'control', weight: 1.0, steps: [$stepA]);
    assertSame('control', $v->name);
    assertSame(1.0, $v->weight);
    assertSame(1, count($v->steps));
});

test('ScenarioVariant throws for zero weight', function () use ($stepA): void {
    assertThrows(
        fn () => new ScenarioVariant(name: 'bad', weight: 0.0, steps: [$stepA]),
        InvalidArgumentException::class,
        'weight'
    );
});

test('ScenarioVariant throws for empty steps', function (): void {
    assertThrows(
        fn () => new ScenarioVariant(name: 'empty', weight: 1.0, steps: []),
        InvalidArgumentException::class,
        'steps'
    );
});

// ---- ScenarioDefinition with variants ----
test('ScenarioDefinition stores variants', function () use ($stepA, $stepB): void {
    $variants = [
        new ScenarioVariant('A', 2.0, [$stepA]),
        new ScenarioVariant('B', 1.0, [$stepB]),
    ];
    $def = new ScenarioDefinition(name: 'test', steps: [], variants: $variants);
    assertSame(2, count($def->variants));
    assertSame('A', $def->variants[0]->name);
});

// ---- ScenarioLoader: parse variants ----
test('ScenarioLoader parses variants from JSON', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'sc') . '.json';
    file_put_contents($tmp, json_encode([
        'variants' => [
            ['name' => 'control', 'weight' => 2, 'steps' => [['url' => 'https://a.example/']]],
            ['name' => 'experiment', 'weight' => 1, 'steps' => [['url' => 'https://b.example/']]],
        ],
    ]));
    $def = (new ScenarioLoader())->load($tmp);
    unlink($tmp);

    assertSame(2, count($def->variants));
    assertSame('control', $def->variants[0]->name);
    assertSame(2.0, $def->variants[0]->weight);
    assertSame('experiment', $def->variants[1]->name);
    assertSame(1.0, $def->variants[1]->weight);
    // steps is empty when only variants provided
    assertSame(0, count($def->steps));
});

test('ScenarioLoader defaults variant weight to 1.0', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'sc') . '.json';
    file_put_contents($tmp, json_encode([
        'variants' => [
            ['name' => 'default', 'steps' => [['url' => 'https://example.com/']]],
        ],
    ]));
    $def = (new ScenarioLoader())->load($tmp);
    unlink($tmp);
    assertSame(1.0, $def->variants[0]->weight);
});

test('ScenarioLoader throws for variants with missing name', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'sc') . '.json';
    file_put_contents($tmp, json_encode([
        'variants' => [
            ['weight' => 1, 'steps' => [['url' => 'https://example.com/']]],
        ],
    ]));
    assertThrows(
        fn () => (new ScenarioLoader())->load($tmp),
        InvalidArgumentException::class,
        "'name'"
    );
    unlink($tmp);
});

test('ScenarioLoader throws for variants with negative weight', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'sc') . '.json';
    file_put_contents($tmp, json_encode([
        'variants' => [
            ['name' => 'bad', 'weight' => -1, 'steps' => [['url' => 'https://example.com/']]],
        ],
    ]));
    assertThrows(
        fn () => (new ScenarioLoader())->load($tmp),
        InvalidArgumentException::class,
        'weight'
    );
    unlink($tmp);
});

test('ScenarioLoader throws when no steps and no variants', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'sc') . '.json';
    file_put_contents($tmp, json_encode(['name' => 'empty']));
    assertThrows(
        fn () => (new ScenarioLoader())->load($tmp),
        InvalidArgumentException::class,
        'step'
    );
    unlink($tmp);
});

// ---- ScenarioRunner: variant assignment ----
test('ScenarioRunner assigns variants proportionally by weight', function (): void {
    $def = new ScenarioDefinition(
        name: 'variant test',
        steps: [],
        variants: [
            new ScenarioVariant('A', 2.0, [new ScenarioStep('http://127.0.0.1:9999/a')]),
            new ScenarioVariant('B', 1.0, [new ScenarioStep('http://127.0.0.1:9999/b')]),
        ]
    );

    $runner = new ScenarioRunner();
    $result = $runner->run($def, concurrency: 3, durationSec: null, iterations: 3);

    // We expect variant names to be set on iteration results
    $variantNames = array_map(fn ($ir) => $ir->variantName, $result->iterationResults);
    // With 3 VUs and 2:1 weight: VU0(p=0.0<0.667)→A, VU1(p=0.333<0.667)→A, VU2(p=0.667>=0.667)→B
    assertTrue(in_array('A', $variantNames, true));
    assertTrue(in_array('B', $variantNames, true));
});

test('ScenarioResult iterationResults has variantName set when variants used', function (): void {
    $def = new ScenarioDefinition(
        name: 'single variant',
        steps: [],
        variants: [
            new ScenarioVariant('only', 1.0, [new ScenarioStep('http://127.0.0.1:9999/only')]),
        ]
    );

    $runner = new ScenarioRunner();
    $result = $runner->run($def, concurrency: 1, durationSec: null, iterations: 1);

    assertSame(1, count($result->iterationResults));
    assertSame('only', $result->iterationResults[0]->variantName);
});

test('ScenarioIterationResult variantName defaults to null without variants', function (): void {
    $def = new ScenarioDefinition(
        name: 'no variant',
        steps: [new ScenarioStep('http://127.0.0.1:9999/plain')],
    );

    $runner = new ScenarioRunner();
    $result = $runner->run($def, concurrency: 1, durationSec: null, iterations: 1);

    assertSame(1, count($result->iterationResults));
    assertSame(null, $result->iterationResults[0]->variantName);
});
