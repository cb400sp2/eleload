<?php

declare(strict_types=1);

namespace Eleload\Telemetry;

/**
 * Minimal OpenTelemetry tracer that exports spans via OTLP/HTTP+JSON.
 *
 * This is a lightweight, zero-dependency implementation of the OTLP/HTTP JSON
 * export protocol (https://opentelemetry.io/docs/specs/otlp/#otlphttp-request).
 *
 * Spans are batched in memory and flushed synchronously when flush() is called
 * or when the batch size limit is reached.
 *
 * Usage:
 *   $tracer = new OtelTracer('http://localhost:4318');
 *   $span   = $tracer->startSpan('scenario.run', ['url' => 'https://...']);
 *   // ... do work ...
 *   $span->end();
 *   $tracer->flush();
 */
final class OtelTracer implements TracerInterface
{
    private const OTLP_TRACES_PATH = '/v1/traces';
    private const DEFAULT_BATCH_SIZE = 100;
    private const SERVICE_NAME = 'eleload';

    private string $traceId;
    /** @var list<OtelSpan> */
    private array $pendingSpans = [];
    private bool $exportErrors = false;

    public function __construct(
        private readonly string $endpoint,
        private readonly int $batchSize = self::DEFAULT_BATCH_SIZE,
    ) {
        $this->traceId = $this->generateId(16);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $span = new OtelSpan(
            name: $name,
            traceId: $this->traceId,
            spanId: $this->generateId(8),
            attributes: $attributes,
            tracer: $this,
        );
        return $span;
    }

    /**
     * Called by OtelSpan::end() to queue the span for export.
     */
    public function exportSpan(OtelSpan $span): void
    {
        $this->pendingSpans[] = $span;
        if (count($this->pendingSpans) >= $this->batchSize) {
            $this->flush();
        }
    }

    /**
     * Export all pending spans to the OTLP endpoint.
     * Silently swallows export errors to avoid disrupting the load test.
     */
    public function flush(): void
    {
        if ($this->pendingSpans === []) {
            return;
        }

        $batch = $this->pendingSpans;
        $this->pendingSpans = [];

        try {
            $payload = $this->buildPayload($batch);
            $this->sendHttp($payload);
        } catch (\Throwable) {
            // Do not propagate: tracing must never break the primary workload
            $this->exportErrors = true;
        }
    }

    /** Returns true if any export attempt failed (useful for diagnostics). */
    public function hadExportErrors(): bool
    {
        return $this->exportErrors;
    }

    /**
     * @param list<OtelSpan> $spans
     * @return array<string, mixed>
     */
    private function buildPayload(array $spans): array
    {
        $protoSpans = [];
        foreach ($spans as $span) {
            $attrs = [];
            foreach ($span->attributes as $key => $value) {
                $attrs[] = ['key' => $key, 'value' => $this->encodeValue($value)];
            }

            $events = [];
            foreach ($span->events as $event) {
                $events[] = [
                    'timeUnixNano' => (string) $span->startTimeUnixNano,
                    'name' => $event['type'],
                    'attributes' => [
                        ['key' => 'exception.message', 'value' => ['stringValue' => $event['message']]],
                    ],
                ];
            }

            $protoSpans[] = [
                'traceId' => $span->traceId,
                'spanId'  => $span->spanId,
                'name'    => $span->name,
                'kind'    => 3, // SPAN_KIND_CLIENT
                'startTimeUnixNano' => (string) $span->startTimeUnixNano,
                'endTimeUnixNano'   => (string) $span->endTimeUnixNano,
                'attributes' => $attrs,
                'events'     => $events,
                'status'     => ['code' => 1], // STATUS_CODE_OK
            ];
        }

        return [
            'resourceSpans' => [[
                'resource' => [
                    'attributes' => [[
                        'key'   => 'service.name',
                        'value' => ['stringValue' => self::SERVICE_NAME],
                    ]],
                ],
                'scopeSpans' => [[
                    'scope' => ['name' => 'eleload', 'version' => '1.0.0'],
                    'spans' => $protoSpans,
                ]],
            ]],
        ];
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function encodeValue(mixed $value): array
    {
        if (is_bool($value)) {
            return ['boolValue' => $value];
        }
        if (is_int($value)) {
            return ['intValue' => (string) $value];
        }
        if (is_float($value)) {
            return ['doubleValue' => $value];
        }
        return ['stringValue' => (string) $value];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendHttp(array $payload): void
    {
        $url = rtrim($this->endpoint, '/') . self::OTLP_TRACES_PATH;
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n",
                'content' => $body,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        // Suppress any PHP warnings; a return value of false means failure
        $result = @file_get_contents($url, false, $ctx);
        if ($result === false) {
            throw new \RuntimeException('OTLP export failed (connection refused or network error).');
        }
    }

    /**
     * Generate a random hex-encoded ID of the given byte length.
     *
     * 16 bytes => 32 hex chars (traceId)
     * 8 bytes => 16 hex chars (spanId)
     */
    private function generateId(int $bytes): string
    {
        if ($bytes < 1) {
            throw new \InvalidArgumentException('bytes must be >= 1');
        }
        return bin2hex(random_bytes($bytes));
    }
}
