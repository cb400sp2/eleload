<?php

declare(strict_types=1);

namespace Eleload\Logging;

/**
 * Minimal structured logger interface.
 *
 * Implementations must be safe to call from any context (no exceptions thrown
 * for recoverable I/O errors).
 */
interface LoggerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void;

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void;

    /**
     * @param array<string, mixed> $context
     */
    public function warn(string $message, array $context = []): void;

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void;
}
