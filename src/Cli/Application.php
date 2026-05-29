<?php

declare(strict_types=1);

namespace Eleload\Cli;

use Eleload\Compare\ReportComparator;
use Eleload\LoadTesting\CurlMultiRunner;
use Eleload\LoadTesting\RequestOptions;
use Eleload\LoadTesting\ScenarioLoader;
use Eleload\LoadTesting\ScenarioRunner;
use Eleload\Metrics\FailureEvaluator;
use Eleload\Metrics\StatisticsCalculator;
use Eleload\Report\CompareMarkdownReporter;
use Eleload\Report\ConsoleReporter;
use Eleload\Report\CsvReporter;
use Eleload\Report\HtmlReporter;
use Eleload\Report\JsonReporter;
use Eleload\Report\MarkdownReporter;
use Eleload\Report\ReportPathGenerator;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Entry point for the eleload CLI application.
 *
 * Dispatches to sub-commands (run, report, compare) and handles top-level
 * error reporting.
 */
final class Application
{
    public const VERSION = '1.0.0';
    private const HIGH_LOAD_REQUESTS_MAX = 10_000;
    private const HIGH_LOAD_CONCURRENCY_MAX = 500;

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $output = new ConsoleOutput();

        $command = $argv[1] ?? 'help';

        try {
            if ($command === 'help' || $command === '--help' || $command === '-h') {
                $this->printHelp($output);
                return 0;
            }

            if ($command === 'version' || $command === '--version' || $command === '-V') {
                $output->writeln('eleload ' . self::VERSION);
                return 0;
            }

            if ($command === 'run') {
                return $this->runLoadTest(array_slice($argv, 2), $output);
            }

            if ($command === 'report') {
                return $this->runReportCommand(array_slice($argv, 2), $output);
            }

            if ($command === 'compare') {
                return $this->runCompareCommand(array_slice($argv, 2), $output);
            }

            if ($command === 'scenario') {
                return $this->runScenarioCommand(array_slice($argv, 2), $output);
            }

            $output->errorln("Unknown command: {$command}");
            $output->writeln();
            $this->printHelp($output);
            return 1;
        } catch (InvalidArgumentException $e) {
            $output->errorln('Argument error: ' . $e->getMessage());
            return 1;
        } catch (RuntimeException $e) {
            $output->errorln('Runtime error: ' . $e->getMessage());
            return 1;
        } catch (JsonException $e) {
            $output->errorln('JSON error: ' . $e->getMessage());
            return 1;
        } catch (Throwable $e) {
            $output->errorln('Unexpected error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * @param list<string> $args
     */
    private function runLoadTest(array $args, ConsoleOutput $output): int
    {
        $parser = new ArgvParser();
        $options = $parser->parseRun($args);
        $this->printDebugRunContext($options, $output);
        $this->enforceHighLoadGuard($options, $output);

        $runner = new CurlMultiRunner();
        $stats = new StatisticsCalculator();
        $failureEvaluator = new FailureEvaluator();
        $consoleReporter = new ConsoleReporter();
        $jsonReporter = new JsonReporter();
        $htmlReporter = new HtmlReporter(__DIR__ . '/../../templates/report.php');
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

        return $report['summary']['requests']['failed'] > 0 || $report['thresholds']['failed'] ? 1 : 0;
    }

    /**
     * @param list<string> $args
     */
    private function runReportCommand(array $args, ConsoleOutput $output): int
    {
        $parser = new ArgvParser();
        $options = $parser->parseReport($args);
        $htmlReporter = new HtmlReporter(__DIR__ . '/../../templates/report.php');

        $report = $this->readJsonObjectFile($options->jsonPath);

        $htmlReporter->write($report, $options->htmlPath);
        $output->writeln('HTML report: ' . $options->htmlPath);

        return 0;
    }

    /**
     * @param list<string> $args
     */
    private function runCompareCommand(array $args, ConsoleOutput $output): int
    {
        $parser = new ArgvParser();
        $options = $parser->parseCompare($args);

        $beforeReport = $this->readJsonObjectFile($options->beforeJsonPath);
        $afterReport = $this->readJsonObjectFile($options->afterJsonPath);

        $comparison = (new ReportComparator())->compare($beforeReport, $afterReport);

        if ($options->htmlPath !== null) {
            $htmlReporter = new HtmlReporter(__DIR__ . '/../../templates/compare.php');
            $htmlReporter->write($comparison, $options->htmlPath);
            $output->writeln('HTML comparison report: ' . $options->htmlPath);
        }

        if ($options->markdownPath !== null) {
            $markdownReporter = new CompareMarkdownReporter();
            $markdownReporter->write($comparison, $options->markdownPath);
            $output->writeln('Markdown comparison report: ' . $options->markdownPath);
        }

        return 0;
    }

    /**
     * @param list<string> $args
     */
    private function runScenarioCommand(array $args, ConsoleOutput $output): int
    {
        $parser = new ArgvParser();
        $options = $parser->parseScenario($args);

        $loader = new ScenarioLoader();
        $definition = $loader->load($options->scenarioPath);

        // Allow name override
        if ($options->name !== null) {
            $definition = new \Eleload\LoadTesting\ScenarioDefinition(
                name: $options->name,
                steps: $definition->steps,
                variables: $definition->variables
            );
        }

        if ($options->debug) {
            $this->printDebugScenarioContext($definition, $options, $output);
        }

        $this->enforceScenarioHighLoadGuard($options, $output);

        $runner = new ScenarioRunner();
        $result = $runner->run(
            definition: $definition,
            concurrency: $options->concurrency,
            durationSec: $options->durationSec,
            iterations: $options->iterations,
            warmupSec: $options->warmupSec
        );

        if (!$options->silent) {
            $this->printScenarioSummary($result, $output, $options->verbose);
        }

        // JSON report
        $report = $this->buildScenarioReport($result);

        if ($options->reportJsonPath !== null) {
            $jsonReporter = new JsonReporter();
            $jsonReporter->write($report, $options->reportJsonPath);
            if (!$options->silent) {
                $output->writeln('JSON report: ' . $options->reportJsonPath);
            }
        }

        if ($options->outputDir !== null) {
            $pathGenerator = new ReportPathGenerator();
            $paths = $pathGenerator->generate($options->outputDir);
            $jsonReporter = new JsonReporter();
            $jsonReporter->write($report, $paths['json']);
            if (!$options->silent) {
                $output->writeln('JSON report: ' . $paths['json']);
            }
        }

        return $result->errorRate() > 0.0 ? 1 : 0;
    }

    private function printScenarioSummary(
        \Eleload\LoadTesting\ScenarioResult $result,
        ConsoleOutput $output,
        bool $verbose
    ): void {
        $output->writeln('');
        $output->writeln('Scenario: ' . $result->definition->name);
        $output->writeln(sprintf('Duration: %.2fs', $result->durationSec));
        $output->writeln('');

        $total = $result->totalIterations();
        $success = $result->successIterations();
        $failed = $total - $success;
        $tps = $result->scenarioTps();
        $errorRate = $result->errorRate();

        $output->writeln('Scenario Iterations:');
        $output->writeln(sprintf('  Total:   %d', $total));
        $output->writeln(sprintf('  Success: %d', $success));
        $output->writeln(sprintf('  Failed:  %d', $failed));
        $output->writeln(sprintf('  TPS:     %.2f', $tps));
        $output->writeln(sprintf('  Error %%: %.1f%%', $errorRate));
        $output->writeln('');

        $output->writeln('Per-Step Summary:');
        foreach ($result->perStepSummary() as $step) {
            $output->writeln(sprintf(
                '  [%d] %-30s  n=%-6d  rps=%-8.2f  avg=%6.1fms  p95=%6.1fms',
                $step['index'],
                $step['name'],
                $step['count'],
                $step['rps'],
                $step['avgMs'],
                $step['p95Ms']
            ));
        }

        if ($verbose && $result->totalIterations() > 0) {
            $output->writeln('');
            $output->writeln('Failed Iterations:');
            $printed = 0;
            foreach ($result->iterationResults as $iter) {
                if ($iter->success || $printed >= 5) {
                    continue;
                }
                foreach ($iter->stepResults as $sr) {
                    if (!$sr->success) {
                        $output->writeln(sprintf(
                            '  VU %d iter %d step %d "%s": HTTP %d  err=%s',
                            $iter->vuId,
                            $iter->iterationNumber,
                            $sr->stepIndex,
                            $sr->stepName,
                            $sr->httpCode,
                            $sr->error !== '' ? $sr->error : '(none)'
                        ));
                    }
                }
                $printed++;
            }

            if ($printed === 0) {
                $output->writeln('  (none)');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildScenarioReport(\Eleload\LoadTesting\ScenarioResult $result): array
    {
        return [
            'scenario' => [
                'name' => $result->definition->name,
                'steps' => array_map(static fn ($s) => [
                    'name' => $s->name,
                    'url' => $s->url,
                    'method' => $s->method,
                ], $result->definition->steps),
            ],
            'summary' => [
                'duration_sec' => round($result->durationSec, 3),
                'warmup_sec' => $result->warmupSec,
                'total_iterations' => $result->totalIterations(),
                'success_iterations' => $result->successIterations(),
                'failed_iterations' => $result->totalIterations() - $result->successIterations(),
                'tps' => round($result->scenarioTps(), 4),
                'error_rate' => round($result->errorRate(), 2),
            ],
            'steps' => $result->perStepSummary(),
        ];
    }

    private function printDebugScenarioContext(
        \Eleload\LoadTesting\ScenarioDefinition $definition,
        ScenarioOptions $options,
        ConsoleOutput $output
    ): void {
        $output->writeln('[DEBUG] Scenario Options:');
        $output->writeln(json_encode([
            'scenario_path' => $options->scenarioPath,
            'concurrency' => $options->concurrency,
            'duration' => $options->durationSec,
            'iterations' => $options->iterations,
            'warmup' => $options->warmupSec,
            'silent' => $options->silent,
            'verbose' => $options->verbose,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
        $output->writeln('[DEBUG] Scenario Definition:');
        $output->writeln(sprintf('  name: %s', $definition->name));
        $output->writeln(sprintf('  steps: %d', count($definition->steps)));
        $output->writeln(sprintf('  variables: %d', count($definition->variables)));
        $output->writeln('');
    }

    private function enforceScenarioHighLoadGuard(ScenarioOptions $options, ConsoleOutput $output): void
    {
        $warningParts = [];
        if ($options->concurrency > self::HIGH_LOAD_CONCURRENCY_MAX) {
            $warningParts[] = sprintf(
                'concurrency=%d exceeds default max %d',
                $options->concurrency,
                self::HIGH_LOAD_CONCURRENCY_MAX
            );
        }

        if ($warningParts === [] || $options->allowHighLoad || $options->yes) {
            return;
        }

        $detail = implode('; ', $warningParts);
        $message = 'High-load settings detected (' . $detail . ').';

        if (!$this->isInteractiveInput()) {
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

    /**
     * @return array<string, mixed>
     */
    private function readJsonObjectFile(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("JSON report file not found: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Failed to read JSON report: {$path}");
        }

        $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($report)) {
            throw new RuntimeException('Invalid JSON report format: root must be an object');
        }

        return $report;
    }

    /**
 * Prints help text to STDOUT and routes commands.
 */
    private function printHelp(ConsoleOutput $output): void
    {
        $output->writeln('eleload ' . self::VERSION);
        $output->writeln();
        $output->writeln('Usage:');
        $output->writeln('  eleload run <url> [options]');
        $output->writeln('  eleload scenario <scenario.json> [options]');
        $output->writeln('  eleload report <report.json> --html=<output.html>');
        $output->writeln('  eleload compare <before.json> <after.json> [--html=<output.html>] [--md=<output.md>]');
        $output->writeln('  eleload help');
        $output->writeln('  eleload version');
        $output->writeln();
        $output->writeln('Options for run:');
        $output->writeln('  --requests=100           Total requests');
        $output->writeln('  --concurrency=10         Concurrent requests');
        $output->writeln('  --method=GET             HTTP method');
        $output->writeln('  --header="K: V"          Repeatable HTTP header');
        $output->writeln('  --bearer-token=TOKEN     Send Authorization: Bearer TOKEN');
        $output->writeln('  --basic-user=USER        Basic auth username');
        $output->writeln('  --basic-password=PASS    Basic auth password');
        $output->writeln('  --cookie=TEXT            Send Cookie header value');
        $output->writeln('  --follow-redirects       Follow HTTP redirects');
        $output->writeln('  --no-follow-redirects    Disable redirect following (default)');
        $output->writeln('  --body="..."             Request body');
        $output->writeln('  --timeout=10             Timeout seconds');
        $output->writeln('  --connect-timeout=NUM    Connection timeout seconds (default: min(--timeout, 5))');
        $output->writeln('  --silent                 Suppress normal run output');
        $output->writeln('  --verbose                Show richer error and slowest request details');
        $output->writeln('  --debug                  Print parsed options and execution plan');
        $output->writeln('  --yes                    Skip high-load confirmation prompt');
        $output->writeln('  --allow-high-load        Explicitly allow high-load settings');
        $output->writeln('  --success-status=LIST    Comma-separated success status codes (e.g. 200,201,204)');
        $output->writeln('  --expect-status=LIST     Comma-separated expected status codes');
        $output->writeln('  --expect-body-contains=T Validate response body contains text');
        $output->writeln('  --duration=SECONDS       Run for a fixed duration instead of request count');
        $output->writeln('  --warmup=SECONDS         Exclude initial seconds from metrics');
        $output->writeln('  --report-json=FILE       Write JSON report');
        $output->writeln('  --report-html=FILE       Write HTML report');
        $output->writeln('  --report-md=FILE         Write Markdown report');
        $output->writeln('  --report-csv=FILE        Write CSV report');
        $output->writeln('  --output-dir=DIR         Write timestamped JSON/HTML/Markdown reports');
        $output->writeln('  --name=TEXT              Test name shown in reports');
        $output->writeln('  --rate=NUM               Fixed request rate (RPS), requires --duration');
        $output->writeln('  --target-rps=NUM         Target RPS');
        $output->writeln('  --target-tps=NUM         Target TPS');
        $output->writeln('  --ramp-up=SECONDS        Linearly increase concurrency over this duration (0 = no ramp)');
        $output->writeln('  --fail-on-p95=MS         Fail if p95 exceeds this latency');
        $output->writeln('  --fail-on-p99=MS         Fail if p99 exceeds this latency');
        $output->writeln('  --fail-on-error-rate=PCT Fail if error rate exceeds this percent');
        $output->writeln('  --fail-on-rps-below=NUM  Fail if RPS is below this value');
        $output->writeln('  --fail-on-tps-below=NUM  Fail if TPS is below this value');
        $output->writeln();
        $output->writeln('Options for scenario:');
        $output->writeln('  --concurrency=10         Concurrent virtual users');
        $output->writeln('  --duration=SECONDS       Run for a fixed duration');
        $output->writeln('  --iterations=100         Scenario iterations (used when --duration not set)');
        $output->writeln('  --warmup=SECONDS         Exclude initial seconds from metrics');
        $output->writeln('  --silent                 Suppress output');
        $output->writeln('  --verbose                Show failed step details');
        $output->writeln('  --debug                  Print parsed options and scenario definition');
        $output->writeln('  --yes                    Skip high-load confirmation');
        $output->writeln('  --allow-high-load        Explicitly allow high-load settings');
        $output->writeln('  --report-json=FILE       Write JSON summary report');
        $output->writeln('  --output-dir=DIR         Write timestamped JSON report to directory');
        $output->writeln('  --name=TEXT              Override scenario name in reports');
        $output->writeln();
        $output->writeln('Options for report:');
        $output->writeln('  --html=FILE              Output HTML path');
        $output->writeln();
        $output->writeln('Options for compare:');
        $output->writeln('  --html=FILE              Output HTML comparison path');
        $output->writeln('  --md=FILE                Output Markdown comparison path');
    }

    /**
 * Warns the user (or throws) when request / concurrency counts exceed safe defaults.
 */
    private function enforceHighLoadGuard(RunOptions $options, ConsoleOutput $output): void
    {
        $warningParts = [];
        if ($options->requests > self::HIGH_LOAD_REQUESTS_MAX) {
            $warningParts[] = sprintf(
                'requests=%d exceeds default max %d',
                $options->requests,
                self::HIGH_LOAD_REQUESTS_MAX
            );
        }
        if ($options->concurrency > self::HIGH_LOAD_CONCURRENCY_MAX) {
            $warningParts[] = sprintf(
                'concurrency=%d exceeds default max %d',
                $options->concurrency,
                self::HIGH_LOAD_CONCURRENCY_MAX
            );
        }

        if ($warningParts === [] || $options->allowHighLoad || $options->yes) {
            return;
        }

        $detail = implode('; ', $warningParts);
        $message = 'High-load settings detected (' . $detail . ').';

        if (!$this->isInteractiveInput()) {
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

    /**
 * Returns true when STDIN is connected to an interactive TTY.
 */
    private function isInteractiveInput(): bool
    {
        return function_exists('stream_isatty') && stream_isatty(STDIN);
    }

    /**
 * Dumps parsed options and the execution plan to STDOUT when --debug is set.
 */
    private function printDebugRunContext(RunOptions $options, ConsoleOutput $output): void
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
    }
}
