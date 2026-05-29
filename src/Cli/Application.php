<?php

declare(strict_types=1);

namespace Eleload\Cli;

use Eleload\Compare\ReportComparator;
use Eleload\LoadTesting\CurlMultiRunner;
use Eleload\LoadTesting\RequestOptions;
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

final class Application
{
    public const VERSION = '0.1.0';

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
            targetRps: $options->targetRps,
            targetTps: $options->targetTps
        ));

        $report = $stats->summarize($result);
        $report['thresholds'] = $failureEvaluator->evaluate($report, $options);

        $consoleReporter->render($report, $output);

        if ($options->reportJsonPath !== null) {
            $jsonReporter->write($report, $options->reportJsonPath);
            $output->writeln('JSON report: ' . $options->reportJsonPath);
        }

        if ($options->reportHtmlPath !== null) {
            $htmlReporter->write($report, $options->reportHtmlPath);
            $output->writeln('HTML report: ' . $options->reportHtmlPath);
        }

        if ($options->reportMdPath !== null) {
            $markdownReporter->write($report, $options->reportMdPath);
            $output->writeln('Markdown report: ' . $options->reportMdPath);
        }

        if ($options->reportCsvPath !== null) {
            $csvReporter->write($result, $options->reportCsvPath);
            $output->writeln('CSV report: ' . $options->reportCsvPath);
        }

        if ($options->outputDir !== null) {
            $paths = $pathGenerator->generate($options->outputDir);
            $jsonReporter->write($report, $paths['json']);
            $htmlReporter->write($report, $paths['html']);
            $markdownReporter->write($report, $paths['md']);
            $output->writeln('JSON report: ' . $paths['json']);
            $output->writeln('HTML report: ' . $paths['html']);
            $output->writeln('Markdown report: ' . $paths['md']);
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

    private function printHelp(ConsoleOutput $output): void
    {
        $output->writeln('eleload ' . self::VERSION);
        $output->writeln();
        $output->writeln('Usage:');
        $output->writeln('  eleload run <url> [options]');
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
        $output->writeln('  --target-rps=NUM         Target RPS');
        $output->writeln('  --target-tps=NUM         Target TPS');
        $output->writeln('  --fail-on-p95=MS         Fail if p95 exceeds this latency');
        $output->writeln('  --fail-on-p99=MS         Fail if p99 exceeds this latency');
        $output->writeln('  --fail-on-error-rate=PCT Fail if error rate exceeds this percent');
        $output->writeln('  --fail-on-rps-below=NUM  Fail if RPS is below this value');
        $output->writeln('  --fail-on-tps-below=NUM  Fail if TPS is below this value');
        $output->writeln();
        $output->writeln('Options for report:');
        $output->writeln('  --html=FILE              Output HTML path');
        $output->writeln();
        $output->writeln('Options for compare:');
        $output->writeln('  --html=FILE              Output HTML comparison path');
        $output->writeln('  --md=FILE                Output Markdown comparison path');
    }
}
