<?php

declare(strict_types=1);

use Eleload\Metrics\PrometheusPusher;

// ---- buildLabels ----

test('PrometheusPusher buildLabels returns empty string for empty array', function (): void {
    $pusher = new PrometheusPusher('http://localhost:9091');
    assertSame('', $pusher->buildLabels([]));
});

test('PrometheusPusher buildLabels wraps single label in braces', function (): void {
    $pusher = new PrometheusPusher('http://localhost:9091');
    assertSame('{job="eleload"}', $pusher->buildLabels(['job' => 'eleload']));
});

test('PrometheusPusher buildLabels joins multiple labels with comma', function (): void {
    $pusher = new PrometheusPusher('http://localhost:9091');
    $result = $pusher->buildLabels(['job' => 'eleload', 'test_name' => 'smoke']);
    assertSame('{job="eleload",test_name="smoke"}', $result);
});

test('PrometheusPusher buildLabels escapes double-quote in label value', function (): void {
    $pusher = new PrometheusPusher('http://localhost:9091');
    $result = $pusher->buildLabels(['url' => 'https://example.com/"path"']);
    assertContains('\\"path\\"', $result);
});

test('PrometheusPusher buildLabels escapes backslash in label value', function (): void {
    $pusher = new PrometheusPusher('http://localhost:9091');
    $result = $pusher->buildLabels(['url' => 'C:\\path\\file']);
    assertContains('C:\\\\path\\\\file', $result);
});

test('PrometheusPusher buildLabels escapes newline in label value', function (): void {
    $pusher = new PrometheusPusher('http://localhost:9091');
    $result = $pusher->buildLabels(['test_name' => "line1\nline2"]);
    assertContains('line1\\nline2', $result);
});

// ---- buildBody ----

test('PrometheusPusher buildBody contains all expected metric names', function (): void {
    $pusher  = new PrometheusPusher('http://localhost:9091');
    $report  = makeReport();
    $body    = $pusher->buildBody($report, 'mytest');

    foreach ([
        'eleload_requests_total',
        'eleload_requests_success_total',
        'eleload_error_rate_percent',
        'eleload_rps',
        'eleload_tps',
        'eleload_latency_p50_ms',
        'eleload_latency_p95_ms',
        'eleload_latency_p99_ms',
        'eleload_duration_seconds',
    ] as $metric) {
        assertContains($metric, $body, "Expected metric {$metric} in body");
    }
});

test('PrometheusPusher buildBody uses HELP and TYPE lines for each metric', function (): void {
    $pusher = new PrometheusPusher('http://localhost:9091');
    $body   = $pusher->buildBody(makeReport());

    assertContains('# HELP eleload_rps', $body);
    assertContains('# TYPE eleload_rps gauge', $body);
});

test('PrometheusPusher buildBody embeds numeric values from report', function (): void {
    $pusher = new PrometheusPusher('http://localhost:9091');
    $report = makeReport();
    $body   = $pusher->buildBody($report);

    assertContains('eleload_requests_total{', $body);
    assertContains('} 100', $body);  // total = 100
    assertContains('eleload_rps{', $body);
    assertContains('} 50', $body);    // rps = 50.0
});

test('PrometheusPusher buildBody embeds job label', function (): void {
    $pusher = new PrometheusPusher('http://localhost:9091');
    $body   = $pusher->buildBody(makeReport(), 'myjob');

    assertContains('job="myjob"', $body);
});

test('PrometheusPusher buildBody embeds test_name and url labels', function (): void {
    $pusher = new PrometheusPusher('http://localhost:9091');
    $body   = $pusher->buildBody(makeReport());

    assertContains('test_name="smoke test"', $body);
    assertContains('url="https://example.com"', $body);
});

test('PrometheusPusher buildBody handles missing summary sections gracefully', function (): void {
    $pusher = new PrometheusPusher('http://localhost:9091');
    $body   = $pusher->buildBody([]);  // completely empty report

    assertContains('eleload_requests_total', $body);
    assertContains('} 0', $body);
});

// ---- helper ----

/**
 * @return array<string, mixed>
 */
function makeReport(): array
{
    return [
        'target'  => ['url' => 'https://example.com', 'method' => 'GET'],
        'config'  => ['requests' => 100, 'concurrency' => 10],
        'summary' => [
            'duration_sec' => 2.0,
            'requests'     => ['total' => 100, 'success' => 90, 'failed' => 10, 'success_rate' => 90.0, 'error_rate' => 10.0],
            'throughput'   => ['rps' => 50.0, 'tps' => 45.0, 'tps_rps_rate' => 90.0],
            'latency'      => ['min' => 5.0, 'avg' => 12.0, 'p50' => 10.0, 'p95' => 25.0, 'p99' => 40.0, 'max' => 80.0],
            'status_codes' => ['200' => ['count' => 90, 'rate' => 90.0], '500' => ['count' => 10, 'rate' => 10.0]],
        ],
        'errors'  => [],
        'meta'    => ['tool' => 'eleload', 'version' => '0.1.0', 'test_name' => 'smoke test'],
    ];
}
