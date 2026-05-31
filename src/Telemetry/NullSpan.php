<?php

declare(strict_types=1);

namespace Eleload\Telemetry;

/** No-op span — used by NullTracer and when tracing is disabled. */
final class NullSpan implements SpanInterface
{
    public function recordException(\Throwable $e): void {}

    /** @param mixed $value */
    public function setAttribute(string $key, mixed $value): void {}

    public function end(): void {}
}
