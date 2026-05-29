<?php

declare(strict_types=1);

namespace Eleload\Report;

/**
 * Generates timestamped output file paths for JSON, HTML, and Markdown reports.
 */
final class ReportPathGenerator
{
    /**
     * @return array{json:string, html:string, md:string}
     */
    public function generate(string $outputDir, ?int $timestamp = null): array
    {
        $timestamp ??= time();
        $prefix = rtrim($outputDir, '/\\') . '/eleload-' . date('Ymd-His', $timestamp);

        return [
            'json' => $prefix . '.json',
            'html' => $prefix . '.html',
            'md' => $prefix . '.md',
        ];
    }
}

