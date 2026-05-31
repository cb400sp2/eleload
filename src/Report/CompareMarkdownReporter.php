<?php

declare(strict_types=1);

namespace Eleload\Report;

use RuntimeException;

/**
 * Generates a Markdown file that summarises the diff between two load-test reports.
 */
final class CompareMarkdownReporter implements ReportWriterInterface
{
    /**
     * @param array<string, mixed> $comparison
     */
    public function write(array $comparison, string $path): void
    {
        $this->ensureParentDirectory($path);

        if (file_put_contents($path, $this->render($comparison)) === false) {
            throw new RuntimeException("Failed to write Markdown comparison report: {$path}");
        }
    }

    /**
     * @param array<string, mixed> $comparison
     */
    public function render(array $comparison): string
    {
        /** @var array{url: string, method: string, test_name?: string} $before */
        $before = $comparison['before'];
        /** @var array{url: string, method: string, test_name?: string} $after */
        $after = $comparison['after'];
        /** @var list<array{label: string, before: float, after: float, delta: float, delta_rate: float|null, direction: string, status: string}> $metrics */
        $metrics = is_array($comparison['metrics'] ?? null) ? array_values($comparison['metrics']) : [];
        /** @var array{improved: int, regressed: int, unchanged: int} $comparisonSummary */
        $comparisonSummary = $comparison['summary'];

        $lines = [
            '# Eleload Compare Report',
            '',
            '## Inputs',
            '',
            '| Item | Before | After |',
            '|---|---|---|',
            '| URL | ' . $this->escape($before['url']) . ' | ' . $this->escape($after['url']) . ' |',
            '| Method | ' . $this->escape($before['method']) . ' | ' . $this->escape($after['method']) . ' |',
            '| Test Name | ' . $this->escape($before['test_name'] ?? '') . ' | ' . $this->escape($after['test_name'] ?? '') . ' |',
            '',
            '## Metric Deltas',
            '',
            '| Metric | Before | After | Delta | Delta % | Better Direction | Result |',
            '|---|---:|---:|---:|---:|---|---|',
        ];

        foreach ($metrics as $metric) {
            $deltaRate = $metric['delta_rate'] === null ? 'n/a' : $this->number($metric['delta_rate']) . '%';
            $direction = $metric['direction'] === 'higher' ? 'Higher is better' : 'Lower is better';

            $lines[] = '| ' . $this->escape($metric['label']) .
                ' | ' . $this->number($metric['before']) .
                ' | ' . $this->number($metric['after']) .
                ' | ' . $this->signed($metric['delta']) .
                ' | ' . $deltaRate .
                ' | ' . $direction .
                ' | ' . strtoupper($metric['status']) . ' |';
        }

        $lines[] = '';
        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = '| Improved | Regressed | Unchanged |';
        $lines[] = '|---:|---:|---:|';
        $lines[] = '| ' . $comparisonSummary['improved'] . ' | ' .
            $comparisonSummary['regressed'] . ' | ' .
            $comparisonSummary['unchanged'] . ' |';

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * Creates parent directories for $path if they do not already exist.
     *
     * @throws \RuntimeException
     */
    private function ensureParentDirectory(string $path): void
    {
        $dir = dirname($path);
        if ($dir === '.' || $dir === '') {
            return;
        }

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create directory: {$dir}");
        }
    }

    /**
     * Escapes pipe characters in Markdown table cells.
     */
    private function escape(string $value): string
    {
        return str_replace('|', '\\|', $value);
    }

    /**
     * Formats a float as a fixed-precision decimal string.
     */
    private function number(float $value): string
    {
        return number_format($value, 2);
    }

    /**
     * Formats a float with an explicit leading `+` for positive values.
     */
    private function signed(float $value): string
    {
        if ($value > 0.0) {
            return '+' . $this->number($value);
        }
        if ($value < 0.0) {
            return $this->number($value);
        }

        return '0.00';
    }
}
