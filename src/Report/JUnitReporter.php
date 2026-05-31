<?php

declare(strict_types=1);

namespace Eleload\Report;

use RuntimeException;

/**
 * Writes a JUnit XML report for CI integration (GitHub Actions, Jenkins, etc.).
 *
 * Each configured threshold check becomes one <testcase>.
 * A failed threshold produces a <failure> element.
 * When no thresholds are configured a single synthetic "no failures" testcase
 * is emitted based on the overall request failure count.
 */
final class JUnitReporter implements ReportWriterInterface
{
    /**
     * @param array<string, mixed> $report
     */
    public function write(array $report, string $path): void
    {
        $this->ensureParentDirectory($path);

        $xml = $this->buildXml($report);

        if (file_put_contents($path, $xml) === false) {
            throw new RuntimeException("Failed to write JUnit report: {$path}");
        }
    }

    /**
     * @param array<string, mixed> $report
     */
    private function buildXml(array $report): string
    {
        $testName  = (string)($report['meta']['test_name'] ?? 'eleload');
        $durationSec = (float)($report['summary']['duration_sec'] ?? 0.0);

        /** @var list<array{name:string,actual:float,threshold:float,operator:string,passed:bool}> $checks */
        $checks = $report['thresholds']['checks'] ?? [];

        // If no threshold checks exist, synthesise one from the failure count.
        if ($checks === []) {
            $failed = (int)($report['summary']['requests']['failed'] ?? 0);
            $checks = [
                [
                    'name'      => 'requests_succeeded',
                    'actual'    => (float)$failed,
                    'threshold' => 0.0,
                    'operator'  => '<=',
                    'passed'    => $failed === 0,
                ],
            ];
        }

        $failures   = 0;
        $testcases  = '';

        foreach ($checks as $check) {
            $passed    = (bool)$check['passed'];
            $caseName  = $this->esc((string)$check['name']);
            $timeAttr  = number_format($durationSec, 3);

            if (!$passed) {
                $failures++;
                $actual    = number_format((float)$check['actual'], 2);
                $threshold = number_format((float)$check['threshold'], 2);
                $op        = (string)$check['operator'];
                $message   = $this->esc(
                    "Threshold violated: {$check['name']} {$actual} not {$op} {$threshold}"
                );
                $testcases .= sprintf(
                    '    <testcase name="%s" classname="eleload.thresholds" time="%s">%s      <failure message="%s">%s</failure>%s    </testcase>%s',
                    $caseName,
                    $timeAttr,
                    "\n",
                    $message,
                    "\n",
                    "\n",
                    "\n"
                );
            } else {
                $testcases .= sprintf(
                    '    <testcase name="%s" classname="eleload.thresholds" time="%s"/>%s',
                    $caseName,
                    $timeAttr,
                    "\n"
                );
            }
        }

        $tests = count($checks);
        $suiteName = $this->esc($testName);
        $time      = number_format($durationSec, 3);

        return implode("\n", [
            '<?xml version="1.0" encoding="UTF-8"?>',
            sprintf(
                '<testsuites name="%s" tests="%d" failures="%d" errors="0" time="%s">',
                $suiteName,
                $tests,
                $failures,
                $time
            ),
            sprintf(
                '  <testsuite name="%s" tests="%d" failures="%d" errors="0" time="%s">',
                $suiteName,
                $tests,
                $failures,
                $time
            ),
            rtrim($testcases),
            '  </testsuite>',
            '</testsuites>',
            '',
        ]);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

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
}
