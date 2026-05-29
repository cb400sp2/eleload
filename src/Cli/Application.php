<?php

declare(strict_types=1);

namespace Eleload\Cli;

use Eleload\Cli\Commands\CompareCommand;
use Eleload\Cli\Commands\ReportCommand;
use Eleload\Cli\Commands\RunCommand;
use Eleload\Cli\Commands\ScenarioCommand;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Entry point for the eleload CLI application.
 *
 * Dispatches incoming argv to the appropriate command handler.
 */
final class Application
{
    public const VERSION = '1.0.0';

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
                return (new RunCommand())->execute(array_slice($argv, 2), $output);
            }

            if ($command === 'report') {
                return (new ReportCommand())->execute(array_slice($argv, 2), $output);
            }

            if ($command === 'compare') {
                return (new CompareCommand())->execute(array_slice($argv, 2), $output);
            }

            if ($command === 'scenario') {
                return (new ScenarioCommand())->execute(array_slice($argv, 2), $output);
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
        $output->writeln('  --bearer-token-env=VAR   Read bearer token from environment variable');
        $output->writeln('  --basic-user=USER        Basic auth username');
        $output->writeln('  --basic-user-env=VAR     Read basic auth username from environment variable');
        $output->writeln('  --basic-password=PASS    Basic auth password');
        $output->writeln('  --basic-password-env=VAR Read basic auth password from environment variable');
        $output->writeln('  --cookie=TEXT            Send Cookie header value');
        $output->writeln('  --cookie-env=VAR         Read cookie value from environment variable');
        $output->writeln('  --follow-redirects       Follow HTTP redirects');
        $output->writeln('  --no-follow-redirects    Disable redirect following (default)');
        $output->writeln('  --block-private-networks Reject requests to private/loopback addresses');
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
        $output->writeln('  --memory-buffer-size=N   Max in-memory results before spilling to disk (default: 10000)');
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
}
