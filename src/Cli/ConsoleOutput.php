<?php

declare(strict_types=1);

namespace Eleload\Cli;

/**
 * Writes a message to STDOUT and STDERR respectively.
 */
final class ConsoleOutput
{
    /**
 * Writes a line to STDOUT.
 */
public function writeln(string $message = ''): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    /**
 * Writes a line to STDERR.
 */
public function errorln(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
    }
}

