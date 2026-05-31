<?php

declare(strict_types=1);

namespace Eleload\Telemetry;

/**
 * No-op tracer — used when no OTLP endpoint is configured.
 * All spans are NullSpan instances that discard data.
 */
final class NullTracer implements TracerInterface
{
    /** @param array<string, mixed> $attributes */
    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        return new NullSpan();
    }
}
