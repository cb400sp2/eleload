<?php

declare(strict_types=1);

namespace Eleload\Report;

use RuntimeException;

/**
 * Generates a Markdown file that summarises the diff between two load-test reports.
 */
final class CompareMarkdownReporter
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
        $lines = [
            '# Eleload Compare Report',
            '',
            '## Inputs',
            '',
            '| Item | Before | After |',
            '|---|---|---|',
            '| URL | ' . $this->escape((string)$comparison['before']['url']) . ' | ' . $this->escape((string)$comparison['after']['url']) . ' |',
            '| Method | ' . $this->escape((string)$comparison['before']['method']) . ' | ' . $this->escape((string)$comparison['after']['method']) . ' |',
            '| Test Name | ' . $this->escape((string)($comparison['before']['test_name'] ?? '')) . ' | ' . $this->escape((string)($comparison['after']['test_name'] ?? '')) . ' |',
            '',
            '## Metric Deltas',
            '',
            '| Metric | Before | After | Delta | Delta % | Better Direction | Result |',
            '|---|---:|---:|---:|---:|---|---|',
        ];

        foreach ($comparison['metrics'] as $metric) {
            $deltaRate = $metric['delta_rate'] === null ? 'n/a' : $this->number((float)$metric['delta_rate']) . '%';
            $direction = $metric['direction'] === 'higher' ? 'Higher is better' : 'Lower is better';

            $lines[] = '| ' . $this->escape((string)$metric['label']) .
                ' | ' . $this->number((float)$metric['before']) .
                ' | ' . $this->number((float)$metric['after']) .
                ' | ' . $this->signed((float)$metric['delta']) .
                ' | ' . $deltaRate .
                ' | ' . $direction .
                ' | ' . strtoupper((string)$metric['status']) . ' |';
        }

        $lines[] = '';
        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = '| Improved | Regressed | Unchanged |';
        $lines[] = '|---:|---:|---:|';
        $lines[] = '| ' . $comparison['summary']['improved'] . ' | ' .
            $comparison['summary']['regressed'] . ' | ' .
            $comparison['summary']['unchanged'] . ' |';

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

