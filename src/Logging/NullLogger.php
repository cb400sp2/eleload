<?php

declare(strict_types=1);

namespace Eleload\Logging;

/**
 * No-op logger suitable for tests and production contexts where logging is disabled.
 */
final class NullLogger implements LoggerInterface
{
    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
    }

    /** @param array<string, mixed> $context */
    public function warn(string $message, array $context = []): void
    {
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
    }
}
