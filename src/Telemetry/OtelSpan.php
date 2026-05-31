<?php

declare(strict_types=1);

namespace Eleload\Telemetry;

/**
 * In-memory span used by OtelTracer.
 * Exported to the OTLP endpoint when end() is called.
 */
final class OtelSpan implements SpanInterface
{
    public readonly int $startTimeUnixNano;
    public int $endTimeUnixNano = 0;
    /** @var array<string, mixed> */
    public array $attributes;
    /** @var list<array{type: string, message: string}> */
    public array $events = [];
    public bool $ended = false;

    private OtelTracer $tracer;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly string $name,
        public readonly string $traceId,
        public readonly string $spanId,
        array $attributes,
        OtelTracer $tracer,
    ) {
        $this->attributes = $attributes;
        $this->tracer = $tracer;
        $this->startTimeUnixNano = (int) (microtime(true) * 1_000_000_000);
    }

    public function recordException(\Throwable $e): void
    {
        $this->events[] = ['type' => 'exception', 'message' => $e->getMessage()];
    }

    /** @param mixed $value */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function end(): void
    {
        if ($this->ended) {
            return;
        }
        $this->ended = true;
        $this->endTimeUnixNano = (int) (microtime(true) * 1_000_000_000);
        $this->tracer->exportSpan($this);
    }
}
