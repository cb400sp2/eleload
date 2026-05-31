<?php

declare(strict_types=1);

use Eleload\LoadTesting\ThinkTime;
use Eleload\LoadTesting\ScenarioLoader;
use Eleload\LoadTesting\ScenarioStep;

// ---- ThinkTime: fixed distribution ----
test('ThinkTime fixed returns constant value', function (): void {
    $tt = new ThinkTime(ThinkTime::DISTRIBUTION_FIXED, 500.0);
    assertSame(500.0, $tt->sampleMs());
    assertSame(500.0, $tt->sampleMs()); // deterministic
});

test('ThinkTime fixed with zero ms returns zero', function (): void {
    $tt = new ThinkTime(ThinkTime::DISTRIBUTION_FIXED, 0.0);
    assertSame(0.0, $tt->sampleMs());
});

// ---- ThinkTime: random distribution ----
test('ThinkTime random stays within [min, max]', function (): void {
    $tt = new ThinkTime(ThinkTime::DISTRIBUTION_RANDOM, 100.0, 500.0);
    for ($i = 0; $i < 50; $i++) {
        $v = $tt->sampleMs();
        assertTrue($v >= 100.0, "random sample {$v} < min 100");
        assertTrue($v <= 500.0, "random sample {$v} > max 500");
    }
});

test('ThinkTime random with equal min and max returns that value', function (): void {
    $tt = new ThinkTime(ThinkTime::DISTRIBUTION_RANDOM, 200.0, 200.0);
    assertSame(200.0, $tt->sampleMs());
});

// ---- ThinkTime: exponential distribution ----
test('ThinkTime exponential returns non-negative values', function (): void {
    $tt = new ThinkTime(ThinkTime::DISTRIBUTION_EXPONENTIAL, 300.0);
    for ($i = 0; $i < 20; $i++) {
        assertTrue($tt->sampleMs() >= 0.0, 'exponential sample must be non-negative');
    }
});

test('ThinkTime exponential with zero mean returns zero', function (): void {
    $tt = new ThinkTime(ThinkTime::DISTRIBUTION_EXPONENTIAL, 0.0);
    assertSame(0.0, $tt->sampleMs());
});

// ---- ThinkTime: constructor validation ----
test('ThinkTime throws for unknown distribution', function (): void {
    assertThrows(
        fn () => new ThinkTime('uniform', 100.0),
        InvalidArgumentException::class,
        "uniform"
    );
});

test('ThinkTime throws when random max < min', function (): void {
    assertThrows(
        fn () => new ThinkTime(ThinkTime::DISTRIBUTION_RANDOM, 500.0, 200.0),
        InvalidArgumentException::class,
        'max'
    );
});

test('ThinkTime throws for negative value', function (): void {
    assertThrows(
        fn () => new ThinkTime(ThinkTime::DISTRIBUTION_FIXED, -1.0),
        InvalidArgumentException::class,
        'non-negative'
    );
});

// ---- ScenarioLoader: think_time parsing ----
test('ScenarioLoader parses think_time fixed', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'sc') . '.json';
    file_put_contents($tmp, json_encode([
        'steps' => [[
            'url' => 'https://example.com/',
            'think_time' => ['distribution' => 'fixed', 'ms' => 250],
        ]],
    ]));
    $def = (new ScenarioLoader())->load($tmp);
    unlink($tmp);

    $tt = $def->steps[0]->thinkTime;
    assertNotNull($tt);
    assertSame(ThinkTime::DISTRIBUTION_FIXED, $tt->distribution);
    assertSame(250.0, $tt->valueMsA);
});

test('ScenarioLoader parses think_time random', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'sc') . '.json';
    file_put_contents($tmp, json_encode([
        'steps' => [[
            'url' => 'https://example.com/',
            'think_time' => ['distribution' => 'random', 'min_ms' => 100, 'max_ms' => 500],
        ]],
    ]));
    $def = (new ScenarioLoader())->load($tmp);
    unlink($tmp);

    $tt = $def->steps[0]->thinkTime;
    assertNotNull($tt);
    assertSame(ThinkTime::DISTRIBUTION_RANDOM, $tt->distribution);
    assertSame(100.0, $tt->valueMsA);
    assertSame(500.0, $tt->valueMsB);
});

test('ScenarioLoader parses think_time exponential', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'sc') . '.json';
    file_put_contents($tmp, json_encode([
        'steps' => [[
            'url' => 'https://example.com/',
            'think_time' => ['distribution' => 'exponential', 'mean_ms' => 300],
        ]],
    ]));
    $def = (new ScenarioLoader())->load($tmp);
    unlink($tmp);

    $tt = $def->steps[0]->thinkTime;
    assertNotNull($tt);
    assertSame(ThinkTime::DISTRIBUTION_EXPONENTIAL, $tt->distribution);
    assertSame(300.0, $tt->valueMsA);
});

test('ScenarioLoader throws for think_time with unknown distribution', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'sc') . '.json';
    file_put_contents($tmp, json_encode([
        'steps' => [[
            'url' => 'https://example.com/',
            'think_time' => ['distribution' => 'gaussian', 'ms' => 100],
        ]],
    ]));
    assertThrows(
        fn () => (new ScenarioLoader())->load($tmp),
        InvalidArgumentException::class,
        'distribution'
    );
    unlink($tmp);
});

test('ScenarioLoader throws when think_time fixed missing ms', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'sc') . '.json';
    file_put_contents($tmp, json_encode([
        'steps' => [[
            'url' => 'https://example.com/',
            'think_time' => ['distribution' => 'fixed'],
        ]],
    ]));
    assertThrows(
        fn () => (new ScenarioLoader())->load($tmp),
        InvalidArgumentException::class,
        "'ms'"
    );
    unlink($tmp);
});

test('ScenarioStep thinkTime defaults to null', function (): void {
    $step = new ScenarioStep(url: 'https://example.com/');
    assertSame(null, $step->thinkTime);
});
