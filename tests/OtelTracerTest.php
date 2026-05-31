<?php

declare(strict_types=1);

use Eleload\Cli\ArgvParser;
use Eleload\Telemetry\NullSpan;
use Eleload\Telemetry\NullTracer;
use Eleload\Telemetry\OtelSpan;
use Eleload\Telemetry\OtelTracer;
use Eleload\Telemetry\SpanInterface;
use Eleload\Telemetry\TracerInterface;

// ---- NullTracer / NullSpan ----
test('NullTracer implements TracerInterface', function (): void {
    assertTrue((new NullTracer()) instanceof TracerInterface);
});

test('NullTracer returns NullSpan', function (): void {
    $span = (new NullTracer())->startSpan('test');
    assertTrue($span instanceof NullSpan);
    assertTrue($span instanceof SpanInterface);
});

test('NullSpan end/setAttribute/recordException are no-ops', function (): void {
    $span = new NullSpan();
    $span->setAttribute('k', 'v');
    $span->recordException(new RuntimeException('boom'));
    $span->end();
    assertTrue(true);
});

// ---- OtelTracer / OtelSpan ----
test('OtelTracer implements TracerInterface', function (): void {
    assertTrue((new OtelTracer('http://localhost:4318')) instanceof TracerInterface);
});

test('OtelTracer returns OtelSpan', function (): void {
    $tracer = new OtelTracer('http://localhost:4318');
    $span = $tracer->startSpan('step.execute', ['url' => 'https://example.com/']);
    assertTrue($span instanceof OtelSpan);
});

test('OtelSpan records start and end times after end()', function (): void {
    $tracer = new OtelTracer('http://localhost:4318');
    $span = $tracer->startSpan('iteration');
    assertTrue($span instanceof OtelSpan);
    assertFalse($span->ended);
    assertTrue($span->startTimeUnixNano > 0);
    // Do not actually send — swap out sendHttp by using localhost (connection refused = exportError)
    $span->end();
    assertTrue($span->ended);
    assertTrue($span->endTimeUnixNano >= $span->startTimeUnixNano);
});

test('OtelSpan end() is idempotent', function (): void {
    $tracer = new OtelTracer('http://localhost:4318');
    $span = $tracer->startSpan('iter');
    assertTrue($span instanceof OtelSpan);
    $span->end();
    $first = $span->endTimeUnixNano;
    $span->end();
    // Second call should not change endTimeUnixNano
    assertSame($first, $span->endTimeUnixNano);
});

test('OtelSpan setAttribute updates attribute', function (): void {
    $tracer = new OtelTracer('http://localhost:4318');
    $span = $tracer->startSpan('s', ['k' => 'old']);
    assertTrue($span instanceof OtelSpan);
    $span->setAttribute('k', 'new');
    assertSame('new', $span->attributes['k']);
});

test('OtelSpan recordException adds event', function (): void {
    $tracer = new OtelTracer('http://localhost:4318');
    $span = $tracer->startSpan('s');
    assertTrue($span instanceof OtelSpan);
    $span->recordException(new RuntimeException('fail'));
    assertSame(1, count($span->events));
    assertSame('exception', $span->events[0]['type']);
    assertContains('fail', $span->events[0]['message']);
});

test('OtelTracer flush on empty buffer does not throw', function (): void {
    $tracer = new OtelTracer('http://localhost:4318');
    $tracer->flush(); // no spans buffered
    assertTrue(true);
});

test('OtelTracer gracefully handles connection failure', function (): void {
    // Port 1 will always refuse the connection — export error must be silently swallowed
    $tracer = new OtelTracer('http://127.0.0.1:1');
    $span = $tracer->startSpan('s');
    $span->end();
    $tracer->flush();
    assertTrue($tracer->hadExportErrors()); // failed but did not throw
});

test('OtelTracer traceId is 32 hex chars (16 bytes)', function (): void {
    $tracer = new OtelTracer('http://localhost:4318');
    $span = $tracer->startSpan('s');
    assertTrue($span instanceof OtelSpan);
    assertTrue((bool) preg_match('/^[0-9a-f]{32}$/', $span->traceId), 'traceId must be 32 hex chars');
});

test('OtelTracer spanId is 16 hex chars (8 bytes)', function (): void {
    $tracer = new OtelTracer('http://localhost:4318');
    $span = $tracer->startSpan('s');
    assertTrue($span instanceof OtelSpan);
    assertTrue((bool) preg_match('/^[0-9a-f]{16}$/', $span->spanId), 'spanId must be 16 hex chars');
});

// ---- ArgvParser: --otel-endpoint ----
test('ArgvParser parses --otel-endpoint', function (): void {
    $parser = new ArgvParser();
    $opts = $parser->parseRun(['https://example.com/', '--otel-endpoint=http://otel:4318']);
    assertSame('http://otel:4318', $opts->otelEndpoint);
});

test('ArgvParser otelEndpoint defaults to null', function (): void {
    $parser = new ArgvParser();
    $opts = $parser->parseRun(['https://example.com/']);
    assertSame(null, $opts->otelEndpoint);
});
