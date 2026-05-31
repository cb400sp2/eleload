<?php

declare(strict_types=1);

namespace Eleload\Telemetry;

/**
 * A single trace span.
 */
interface SpanInterface
{
    /**
     * Record an exception on the span.
     */
    public function recordException(\Throwable $e): void;

    /**
     * Add or overwrite an attribute value.
     *
     * @param mixed $value
     */
    public function setAttribute(string $key, mixed $value): void;

    /**
     * End the span (records endTimeUnixNano).
     */
    public function end(): void;
}
