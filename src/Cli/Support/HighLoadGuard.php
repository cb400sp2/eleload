<?php

declare(strict_types=1);

namespace Eleload\Cli\Support;

use Eleload\Cli\ConsoleOutput;
use RuntimeException;

final class HighLoadGuard
{
    private const HIGH_LOAD_REQUESTS_MAX = 10_000;
    private const HIGH_LOAD_CONCURRENCY_MAX = 500;

    public static function enforceRun(
        int $requests,
        int $concurrency,
        bool $allowHighLoad,
        bool $yes,
        ConsoleOutput $output
    ): void {
        $warningParts = [];
        if ($requests > self::HIGH_LOAD_REQUESTS_MAX) {
            $warningParts[] = sprintf(
                'requests=%d exceeds default max %d',
                $requests,
                self::HIGH_LOAD_REQUESTS_MAX
            );
        }
        if ($concurrency > self::HIGH_LOAD_CONCURRENCY_MAX) {
            $warningParts[] = sprintf(
                'concurrency=%d exceeds default max %d',
                $concurrency,
                self::HIGH_LOAD_CONCURRENCY_MAX
            );
        }

        self::handleWarnings($warningParts, $allowHighLoad, $yes, $output);
    }

    public static function enforceScenario(
        int $concurrency,
        bool $allowHighLoad,
        bool $yes,
        ConsoleOutput $output
    ): void {
        $warningParts = [];
        if ($concurrency > self::HIGH_LOAD_CONCURRENCY_MAX) {
            $warningParts[] = sprintf(
                'concurrency=%d exceeds default max %d',
                $concurrency,
                self::HIGH_LOAD_CONCURRENCY_MAX
            );
        }

        self::handleWarnings($warningParts, $allowHighLoad, $yes, $output);
    }

    /**
     * @param list<string> $warningParts
     */
    private static function handleWarnings(
        array $warningParts,
        bool $allowHighLoad,
        bool $yes,
        ConsoleOutput $output
    ): void {
        if ($warningParts === [] || $allowHighLoad || $yes) {
            return;
        }

        $detail = implode('; ', $warningParts);
        $message = 'High-load settings detected (' . $detail . ').';

        if (!self::isInteractiveInput()) {
            throw new RuntimeException(
                $message . ' Re-run with --yes to confirm or --allow-high-load to explicitly override.'
            );
        }

        $output->writeln($message);
        $output->writeln('Continue? [y/N]');

        $line = fgets(STDIN);
        $answer = strtolower(trim($line === false ? '' : $line));
        if ($answer !== 'y' && $answer !== 'yes') {
            throw new RuntimeException('Aborted by user.');
        }
    }

    private static function isInteractiveInput(): bool
    {
        return function_exists('stream_isatty') && stream_isatty(STDIN);
    }
}
