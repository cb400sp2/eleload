<?php

declare(strict_types=1);

namespace Eleload\Cli\Support;

/**
 * Tracks graceful termination requests from POSIX signals.
 */
final class TerminationSignalWatcher
{
    private ?string $stopReason = null;
    private bool $signalHandlersInstalled = false;

    /**
     * Installs SIGINT/SIGTERM handlers when pcntl is available.
     */
    public function install(): void
    {
        if (
            !function_exists('pcntl_signal') ||
            !function_exists('pcntl_signal_dispatch') ||
            !function_exists('pcntl_async_signals') ||
            !defined('SIGINT') ||
            !defined('SIGTERM')
        ) {
            return;
        }

        pcntl_async_signals(false);

        pcntl_signal(SIGINT, function (): void {
            $this->requestStop('sigint');
        });

        pcntl_signal(SIGTERM, function (): void {
            $this->requestStop('sigterm');
        });

        $this->signalHandlersInstalled = true;
    }

    /**
     * Dispatches pending signals when handlers are installed.
     */
    public function dispatchPendingSignals(): void
    {
        if (!$this->signalHandlersInstalled) {
            return;
        }

        pcntl_signal_dispatch();
    }

    /**
     * Records the first stop reason and ignores subsequent requests.
     */
    public function requestStop(string $reason): void
    {
        if ($this->stopReason !== null || $reason === '') {
            return;
        }

        $this->stopReason = $reason;
    }

    /**
     * Returns the requested stop reason when one is available.
     */
    public function stopReason(): ?string
    {
        return $this->stopReason;
    }
}
