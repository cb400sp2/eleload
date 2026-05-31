<?php

declare(strict_types=1);

namespace Eleload\Logging;

use InvalidArgumentException;

/**
 * Structured JSON-Lines logger.
 *
 * Each log line is a compact JSON object:
 *   {"ts":"2025-01-01T00:00:00.000000Z","level":"info","msg":"...","ctx":{...}}
 *
 * Writes to a file path or to a PHP stream resource (e.g. STDERR).
 */
final class JsonLinesLogger implements LoggerInterface
{
    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_INFO  = 'info';
    public const LEVEL_WARN  = 'warn';
    public const LEVEL_ERROR = 'error';

    private const LEVEL_ORDER = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO  => 1,
        self::LEVEL_WARN  => 2,
        self::LEVEL_ERROR => 3,
    ];

    /** @var resource|null */
    private mixed $handle = null;
    private bool $ownsHandle = false;
    private int $minLevel;

    /**
     * @param string|resource $destination  File path (string) or an already-open stream.
     * @param string $level  Minimum level to emit: debug|info|warn|error
     */
    public function __construct(
        mixed $destination,
        string $level = self::LEVEL_INFO,
    ) {
        if (!isset(self::LEVEL_ORDER[$level])) {
            throw new InvalidArgumentException(
                "Invalid log level '{$level}'. Allowed: " . implode(', ', array_keys(self::LEVEL_ORDER))
            );
        }
        $this->minLevel = self::LEVEL_ORDER[$level];

        if (is_string($destination)) {
            $dir = dirname($destination);
            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }
            $h = fopen($destination, 'ab');
            if ($h === false) {
                throw new \RuntimeException("Failed to open log file: {$destination}");
            }
            $this->handle = $h;
            $this->ownsHandle = true;
        } elseif (is_resource($destination)) {
            $this->handle = $destination;
            $this->ownsHandle = false;
        } else {
            throw new InvalidArgumentException('Destination must be a file path string or a stream resource.');
        }
    }

    public function __destruct()
    {
        if ($this->ownsHandle && $this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warn(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARN, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * @param 'debug'|'info'|'warn'|'error' $level
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context): void
    {
        if (self::LEVEL_ORDER[$level] < $this->minLevel || $this->handle === null) {
            return;
        }

        $entry = ['ts' => $this->now(), 'level' => $level, 'msg' => $message];
        if ($context !== []) {
            $entry['ctx'] = $context;
        }

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        fwrite($this->handle, $line);
    }

    private function now(): string
    {
        $dt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $dt->format('Y-m-d\TH:i:s.u\Z');
    }
}
