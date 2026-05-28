<?php

declare(strict_types=1);

namespace Eleload\Cli;

final class ConsoleOutput
{
    public function writeln(string $message = ''): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    public function errorln(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
    }
}

