<?php

declare(strict_types=1);

namespace Eleload\Cli;

use Eleload\LoadTesting\CurlMultiRunner;
use Eleload\LoadTesting\RequestOptions;
use Eleload\Metrics\StatisticsCalculator;
use Eleload\Report\ConsoleReporter;
use Eleload\Report\HtmlReporter;
use Eleload\Report\JsonReporter;
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
        $consoleReporter = new ConsoleReporter();
        $jsonReporter = new JsonReporter();
        $htmlReporter = new HtmlReporter(__DIR__ . '/../../templates/report.php');

        $result = $runner->run(new RequestOptions(
            url: $options->url,
            requests: $options->requests,
            concurrency: $options->concurrency,
            method: $options->method,
            timeout: $options->timeout,
            headers: $options->headers,
            body: $options->body,
            targetRps: $options->targetRps,
            targetTps: $options->targetTps
        ));

        $report = $stats->summarize($result);

        $consoleReporter->render($report, $output);

        if ($options->reportJsonPath !== null) {
            $jsonReporter->write($report, $options->reportJsonPath);
            $output->writeln('JSON report: ' . $options->reportJsonPath);
        }

        if ($options->reportHtmlPath !== null) {
            $htmlReporter->write($report, $options->reportHtmlPath);
            $output->writeln('HTML report: ' . $options->reportHtmlPath);
        }

        return $report['summary']['requests']['failed'] > 0 ? 1 : 0;
    }

    /**
     * @param list<string> $args
     */
    private function runReportCommand(array $args, ConsoleOutput $output): int
    {
        $parser = new ArgvParser();
        $options = $parser->parseReport($args);
        $htmlReporter = new HtmlReporter(__DIR__ . '/../../templates/report.php');

        if (!is_file($options->jsonPath)) {
            throw new RuntimeException("JSON report file not found: {$options->jsonPath}");
        }

        $json = file_get_contents($options->jsonPath);
        if ($json === false) {
            throw new RuntimeException("Failed to read JSON report: {$options->jsonPath}");
        }

        $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($report)) {
            throw new RuntimeException('Invalid JSON report format: root must be an object');
        }

        $htmlReporter->write($report, $options->htmlPath);
        $output->writeln('HTML report: ' . $options->htmlPath);

        return 0;
    }

    private function printHelp(ConsoleOutput $output): void
    {
        $output->writeln('eleload ' . self::VERSION);
        $output->writeln();
        $output->writeln('Usage:');
        $output->writeln('  phpload run <url> [options]');
        $output->writeln('  phpload report <report.json> --html=<output.html>');
        $output->writeln('  phpload help');
        $output->writeln('  phpload version');
        $output->writeln();
        $output->writeln('Options for run:');
        $output->writeln('  --requests=100           Total requests');
        $output->writeln('  --concurrency=10         Concurrent requests');
        $output->writeln('  --method=GET             HTTP method');
        $output->writeln('  --header="K: V"          Repeatable HTTP header');
        $output->writeln('  --body="..."             Request body');
        $output->writeln('  --timeout=10             Timeout seconds');
        $output->writeln('  --report-json=FILE       Write JSON report');
        $output->writeln('  --report-html=FILE       Write HTML report');
        $output->writeln('  --target-rps=NUM         Target RPS');
        $output->writeln('  --target-tps=NUM         Target TPS');
        $output->writeln();
        $output->writeln('Options for report:');
        $output->writeln('  --html=FILE              Output HTML path');
    }
}
