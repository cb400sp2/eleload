<?php

declare(strict_types=1);

namespace Eleload\Telemetry;

/**
 * Minimal OpenTelemetry tracer interface (subset of the OTel API).
 * Implementations must be safe to call from hot paths.
 */
interface TracerInterface
{
    /**
     * Start a new span.
     *
     * @param array<string, mixed> $attributes
     */
    public function startSpan(string $name, array $attributes = []): SpanInterface;
}
