<?php

declare(strict_types=1);

use Eleload\Cli\Support\TerminationSignalWatcher;

test('TerminationSignalWatcher captures SIGINT', function (): void {
    if (!function_exists('pcntl_signal') || !function_exists('posix_kill') || !defined('SIGINT')) {
        // Skip gracefully when pcntl/posix is unavailable.
        return;
    }

    $watcher = new TerminationSignalWatcher();
    $watcher->install();

    posix_kill(getmypid(), SIGINT);
    $watcher->dispatchPendingSignals();

    assertSame('sigint', $watcher->stopReason());
});

test('TerminationSignalWatcher captures SIGTERM', function (): void {
    if (!function_exists('pcntl_signal') || !function_exists('posix_kill') || !defined('SIGTERM')) {
        // Skip gracefully when pcntl/posix is unavailable.
        return;
    }

    $watcher = new TerminationSignalWatcher();
    $watcher->install();

    posix_kill(getmypid(), SIGTERM);
    $watcher->dispatchPendingSignals();

    assertSame('sigterm', $watcher->stopReason());
});
