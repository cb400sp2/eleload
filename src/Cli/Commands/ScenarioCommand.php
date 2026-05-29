<?php

declare(strict_types=1);

namespace Eleload\Cli\Commands;

use Eleload\Cli\ArgvParser;
use Eleload\Cli\ConsoleOutput;
use Eleload\Cli\ScenarioOptions;
use Eleload\Cli\Support\HighLoadGuard;
use Eleload\LoadTesting\ScenarioDefinition;
use Eleload\LoadTesting\ScenarioLoader;
use Eleload\LoadTesting\ScenarioResult;
use Eleload\LoadTesting\ScenarioRunner;
use Eleload\Report\JsonReporter;
use Eleload\Report\ReportPathGenerator;

final class ScenarioCommand
{
    /**
     * @param list<string> $args
     */
    public function execute(array $args, ConsoleOutput $output): int
    {
        $parser = new ArgvParser();
        $options = $parser->parseScenario($args);

        $loader = new ScenarioLoader();
        $definition = $loader->load($options->scenarioPath);

        if ($options->name !== null) {
            $definition = new ScenarioDefinition(
                name: $options->name,
                steps: $definition->steps,
                variables: $definition->variables
            );
        }

        if ($options->debug) {
            $this->printDebugContext($definition, $options, $output);
        }

        HighLoadGuard::enforceScenario(
            $options->concurrency,
            $options->allowHighLoad,
            $options->yes,
            $output
        );

        $runner = new ScenarioRunner();
        $result = $runner->run(
            definition: $definition,
            concurrency: $options->concurrency,
            durationSec: $options->durationSec,
            iterations: $options->iterations,
            warmupSec: $options->warmupSec
        );

        if (!$options->silent) {
            $this->printSummary($result, $output, $options->verbose);
        }

        $report = $this->buildReport($result);

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

    private function printSummary(ScenarioResult $result, ConsoleOutput $output, bool $verbose): void
    {
        $output->writeln('');
        $output->writeln('Scenario: ' . $result->definition->name);
        $output->writeln(sprintf('Duration: %.2fs', $result->durationSec));
        $output->writeln('');

        $total = $result->totalIterations();
        $success = $result->successIterations();
        $failed = $total - $success;

        $output->writeln('Scenario Iterations:');
        $output->writeln(sprintf('  Total:   %d', $total));
        $output->writeln(sprintf('  Success: %d', $success));
        $output->writeln(sprintf('  Failed:  %d', $failed));
        $output->writeln(sprintf('  TPS:     %.2f', $result->scenarioTps()));
        $output->writeln(sprintf('  Error %%: %.1f%%', $result->errorRate()));
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
    private function buildReport(ScenarioResult $result): array
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

    private function printDebugContext(
        ScenarioDefinition $definition,
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
}
