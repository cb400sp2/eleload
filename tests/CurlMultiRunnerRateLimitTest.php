<?php

declare(strict_types=1);

use Eleload\LoadTesting\CurlMultiRunner;
use Eleload\LoadTesting\RequestOptions;

test('CurlMultiRunner applies target rps pacing', function (): void {
    $result = (new CurlMultiRunner())->run(new RequestOptions(
        url: 'http://127.0.0.1:1',
        requests: 4,
        concurrency: 4,
        method: 'GET',
        timeout: 1,
        targetRps: 4.0
    ));

    assertSame(4, count($result->requestResults));
    assertTrue($result->durationSec >= 0.5, 'target-rps pacing should slow the run duration');
});

test('CurlMultiRunner applies target tps pacing', function (): void {
    $result = (new CurlMultiRunner())->run(new RequestOptions(
        url: 'http://127.0.0.1:1',
        requests: 4,
        concurrency: 4,
        method: 'GET',
        timeout: 1,
        targetTps: 4.0
    ));

    assertSame(4, count($result->requestResults));
    assertTrue($result->durationSec >= 0.5, 'target-tps pacing should slow the run duration');
});

test('CurlMultiRunner uses the stricter rate target when both are set', function (): void {
    $result = (new CurlMultiRunner())->run(new RequestOptions(
        url: 'http://127.0.0.1:1',
        requests: 4,
        concurrency: 4,
        method: 'GET',
        timeout: 1,
        targetRps: 10.0,
        targetTps: 4.0
    ));

    assertSame(4, count($result->requestResults));
    assertTrue($result->durationSec >= 0.5, 'the stricter target rate should control pacing');
});

test('CurlMultiRunner gradually increases concurrency during ramp-up window', function (): void {
    $runner = new CurlMultiRunner();
    $options = new RequestOptions(
        url: 'http://127.0.0.1:1',
        requests: 10,
        concurrency: 10,
        method: 'GET',
        timeout: 1,
        rampUpSec: 0.2
    );

    $method = new ReflectionMethod(CurlMultiRunner::class, 'effectiveConcurrency');
    $startedAt = hrtime(true);

    $initial = $method->invoke($runner, $options, $startedAt);

    usleep(120_000);
    $mid = $method->invoke($runner, $options, $startedAt);

    usleep(120_000);
    $after = $method->invoke($runner, $options, $startedAt);

    assertSame(1, $initial);
    assertTrue($mid > $initial && $mid < 10, 'concurrency should increase before ramp-up completion');
    assertSame(10, $after);
});
