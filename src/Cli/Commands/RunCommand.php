<?php

declare(strict_types=1);

namespace Eleload\Cli\Commands;

use Eleload\Cli\ArgvParser;
use Eleload\Cli\ConsoleOutput;
use Eleload\Cli\RunOptions;
use Eleload\Cli\Support\HighLoadGuard;
use Eleload\LoadTesting\CurlMultiRunner;
use Eleload\LoadTesting\RequestOptions;
use Eleload\Metrics\FailureEvaluator;
use Eleload\Metrics\StatisticsCalculator;
use Eleload\Report\ConsoleReporter;
use Eleload\Report\CsvReporter;
use Eleload\Report\HtmlReporter;
use Eleload\Report\JsonReporter;
use Eleload\Report\MarkdownReporter;
use Eleload\Report\ReportPathGenerator;

final class RunCommand
{
    private const HIGH_LOAD_REQUESTS_MAX = 10_000;
    private const HIGH_LOAD_CONCURRENCY_MAX = 500;

    /**
     * @param list<string> $args
     */
    public function execute(array $args, ConsoleOutput $output): int
    {
        $parser = new ArgvParser();
        $options = $parser->parseRun($args);
        $this->printDebugContext($options, $output);
        HighLoadGuard::enforceRun(
            $options->requests,
            $options->concurrency,
            $options->allowHighLoad,
            $options->yes,
            $output
        );

        $runner = new CurlMultiRunner($options->memoryBufferSize);
        $stats = new StatisticsCalculator();
        $failureEvaluator = new FailureEvaluator();
        $consoleReporter = new ConsoleReporter();
        $jsonReporter = new JsonReporter();
        $htmlReporter = new HtmlReporter(__DIR__ . '/../../../templates/report.php');
        $markdownReporter = new MarkdownReporter();
        $csvReporter = new CsvReporter();
        $pathGenerator = new ReportPathGenerator();

        $result = $runner->run(new RequestOptions(
            url: $options->url,
            requests: $options->requests,
            concurrency: $options->concurrency,
            method: $options->method,
            timeout: $options->timeout,
            connectTimeout: $options->connectTimeout,
            followRedirects: $options->followRedirects,
            headers: $options->headers,
            bearerToken: $options->bearerToken,
            basicUser: $options->basicUser,
            basicPassword: $options->basicPassword,
            cookie: $options->cookie,
            body: $options->body,
            name: $options->name,
            successStatusCodes: $options->successStatusCodes,
            expectStatusCodes: $options->expectStatusCodes,
            expectBodyContains: $options->expectBodyContains,
            durationSec: $options->durationSec,
            warmupSec: $options->warmupSec,
            rate: $options->rate,
            targetRps: $options->targetRps,
            targetTps: $options->targetTps,
            rampUpSec: $options->rampUpSec
        ));

        $report = $stats->summarize($result);
        $report['thresholds'] = $failureEvaluator->evaluate($report, $options);

        if (!$options->silent) {
            $consoleReporter->render($report, $output, $options->verbose);
        }

        if ($options->reportJsonPath !== null) {
            $jsonReporter->write($report, $options->reportJsonPath);
            if (!$options->silent) {
                $output->writeln('JSON report: ' . $options->reportJsonPath);
            }
        }

        if ($options->reportHtmlPath !== null) {
            $htmlReporter->write($report, $options->reportHtmlPath);
            if (!$options->silent) {
                $output->writeln('HTML report: ' . $options->reportHtmlPath);
            }
        }

        if ($options->reportMdPath !== null) {
            $markdownReporter->write($report, $options->reportMdPath);
            if (!$options->silent) {
                $output->writeln('Markdown report: ' . $options->reportMdPath);
            }
        }

        if ($options->reportCsvPath !== null) {
            $csvReporter->write($result, $options->reportCsvPath);
            if (!$options->silent) {
                $output->writeln('CSV report: ' . $options->reportCsvPath);
            }
        }

        if ($options->outputDir !== null) {
            $paths = $pathGenerator->generate($options->outputDir);
            $jsonReporter->write($report, $paths['json']);
            $htmlReporter->write($report, $paths['html']);
            $markdownReporter->write($report, $paths['md']);
            if (!$options->silent) {
                $output->writeln('JSON report: ' . $paths['json']);
                $output->writeln('HTML report: ' . $paths['html']);
                $output->writeln('Markdown report: ' . $paths['md']);
            }
        }

        $exitCode = $report['summary']['requests']['failed'] > 0 || $report['thresholds']['failed'] ? 1 : 0;

        if ($options->debug) {
            $output->writeln(sprintf('[debug] peak_memory_after_run=%d bytes', memory_get_peak_usage(true)));
        }

        return $exitCode;
    }

    private function printDebugContext(RunOptions $options, ConsoleOutput $output): void
    {
        if (!$options->debug) {
            return;
        }

        $parsedOptions = [
            'url' => $options->url,
            'method' => $options->method,
            'requests' => $options->requests,
            'concurrency' => $options->concurrency,
            'timeout' => $options->timeout,
            'connect_timeout' => $options->connectTimeout,
            'follow_redirects' => $options->followRedirects,
            'silent' => $options->silent,
            'verbose' => $options->verbose,
            'debug' => $options->debug,
            'yes' => $options->yes,
            'allow_high_load' => $options->allowHighLoad,
            'headers' => $options->headers,
            'bearer_token_set' => $options->bearerToken !== null && $options->bearerToken !== '',
            'basic_auth_set' => $options->basicUser !== null && $options->basicPassword !== null,
            'cookie_set' => $options->cookie !== null && $options->cookie !== '',
            'body_length' => $options->body === null ? null : strlen($options->body),
            'success_status' => $options->successStatusCodes,
            'expect_status' => $options->expectStatusCodes,
            'expect_body_contains' => $options->expectBodyContains,
            'duration' => $options->durationSec,
            'warmup' => $options->warmupSec,
            'report_json' => $options->reportJsonPath,
            'report_html' => $options->reportHtmlPath,
            'report_md' => $options->reportMdPath,
            'report_csv' => $options->reportCsvPath,
            'output_dir' => $options->outputDir,
            'name' => $options->name,
            'rate' => $options->rate,
            'target_rps' => $options->targetRps,
            'target_tps' => $options->targetTps,
            'ramp_up' => $options->rampUpSec > 0.0 ? $options->rampUpSec : null,
            'fail_on_p95' => $options->failOnP95,
            'fail_on_p99' => $options->failOnP99,
            'fail_on_error_rate' => $options->failOnErrorRate,
            'fail_on_rps_below' => $options->failOnRpsBelow,
            'fail_on_tps_below' => $options->failOnTpsBelow,
            'memory_buffer_size' => $options->memoryBufferSize,
        ];

        $executionPlan = [
            'mode' => $options->durationSec !== null ? 'duration' : 'requests',
            'planned_requests' => $options->durationSec === null ? $options->requests : null,
            'planned_duration_sec' => $options->durationSec,
            'fixed_rate_rps' => $options->rate,
            'concurrency' => $options->concurrency,
            'timeout_sec' => $options->timeout,
            'connect_timeout_sec' => $options->connectTimeout ?? min($options->timeout, 5),
            'high_load_thresholds' => [
                'max_requests' => self::HIGH_LOAD_REQUESTS_MAX,
                'max_concurrency' => self::HIGH_LOAD_CONCURRENCY_MAX,
            ],
            'high_load_triggered' => (
                $options->requests > self::HIGH_LOAD_REQUESTS_MAX ||
                $options->concurrency > self::HIGH_LOAD_CONCURRENCY_MAX
            ),
            'report_targets' => [
                'json' => $options->reportJsonPath,
                'html' => $options->reportHtmlPath,
                'md' => $options->reportMdPath,
                'csv' => $options->reportCsvPath,
                'output_dir' => $options->outputDir,
            ],
        ];

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
        $output->writeln('[debug] parsed_options=' . json_encode($parsedOptions, $flags));
        $output->writeln('[debug] execution_plan=' . json_encode($executionPlan, $flags));
        $output->writeln(sprintf('[debug] peak_memory_before_run=%d bytes', memory_get_peak_usage(true)));
    }
}
