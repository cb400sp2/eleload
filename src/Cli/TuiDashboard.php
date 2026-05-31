<?php

declare(strict_types=1);

namespace Eleload\Cli;

/**
 * Renders a real-time ANSI TUI progress dashboard during a load-test run.
 *
 * When stdout is not a TTY the dashboard is suppressed to avoid polluting
 * piped output.
 */
final class TuiDashboard
{
    private const SPARKLINE_CHARS = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];
    private const SPARKLINE_LEN = 20;

    /** @var list<float> */
    private array $rpsHistory = [];

    private int $lineCount = 0;

    public function start(): void
    {
        if (!$this->isTty()) {
            return;
        }

        // Hide cursor
        echo "\033[?25l";
        $this->lineCount = 0;
    }

    /**
     * Redraws the dashboard with current stats.
     *
     * @param int   $completed  Number of requests completed so far.
     * @param int   $total      Target total (0 in duration mode).
     * @param float $rps        Current RPS.
     * @param float $errorRate  Error rate in percent (0–100).
     * @param float $elapsedSec Seconds elapsed since test start.
     */
    public function update(int $completed, int $total, float $rps, float $errorRate, float $elapsedSec): void
    {
        if (!$this->isTty()) {
            return;
        }

        // Scroll sparkline history
        $this->rpsHistory[] = $rps;
        if (count($this->rpsHistory) > self::SPARKLINE_LEN) {
            array_shift($this->rpsHistory);
        }

        // Move cursor up to overwrite previous output
        if ($this->lineCount > 0) {
            echo "\033[" . $this->lineCount . "A\033[0J";
        }

        $progress = $total > 0
            ? sprintf(' (%s%%)', number_format($completed / $total * 100, 1))
            : '';

        $bar = $total > 0 ? $this->progressBar($completed, $total) : '';

        $lines = [
            sprintf(
                "\033[1;36m eleload\033[0m  \033[2m%s\033[0m",
                $this->formatElapsed($elapsedSec)
            ),
            '',
            sprintf('  Completed  \033[1m%d\033[0m%s%s', $completed, $progress, $bar !== '' ? ' ' . $bar : ''),
            sprintf(
                '  RPS        \033[1m%s\033[0m  %s',
                number_format($rps, 1),
                $this->sparkline()
            ),
            sprintf(
                '  Error Rate \033[1m%s\033[0m%%',
                number_format($errorRate, 2)
            ),
            '',
        ];

        echo implode("\n", $lines);
        $this->lineCount = count($lines);
    }

    public function finish(): void
    {
        if (!$this->isTty()) {
            return;
        }

        // Restore cursor
        echo "\033[?25h";
    }

    private function isTty(): bool
    {
        return function_exists('posix_isatty') && posix_isatty(STDOUT);
    }

    private function sparkline(): string
    {
        if ($this->rpsHistory === []) {
            return '';
        }

        $max = max($this->rpsHistory);
        if ($max <= 0) {
            return str_repeat(self::SPARKLINE_CHARS[0], count($this->rpsHistory));
        }

        $chars = self::SPARKLINE_CHARS;
        $last = count($chars) - 1;
        $result = '';
        foreach ($this->rpsHistory as $value) {
            $idx = (int) round($value / $max * $last);
            $result .= $chars[min($idx, $last)];
        }

        return $result;
    }

    private function progressBar(int $completed, int $total): string
    {
        $width = 20;
        $ratio = min($completed / $total, 1.0);
        $filled = (int) round($ratio * $width);
        $empty = $width - $filled;

        return '[' . str_repeat('█', $filled) . str_repeat('░', $empty) . ']';
    }

    private function formatElapsed(float $sec): string
    {
        $s = (int) $sec;
        $m = intdiv($s, 60);
        $s %= 60;

        return sprintf('%d:%02d', $m, $s);
    }
}
