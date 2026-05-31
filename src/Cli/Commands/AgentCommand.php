<?php

declare(strict_types=1);

namespace Eleload\Cli\Commands;

use Eleload\Cli\ConsoleOutput;
use Eleload\LoadTesting\ScenarioLoader;
use Eleload\LoadTesting\ScenarioRunner;
use JsonException;
use RuntimeException;

final class AgentCommand
{
    /**
     * @param list<string> $args
     */
    public function execute(array $args, ConsoleOutput $output): int
    {
        $input = stream_get_contents(STDIN);
        if ($input === false || trim($input) === '') {
            $output->errorln('agent command requires JSON payload on stdin');
            return 1;
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $output->errorln('agent command: invalid JSON payload: ' . $e->getMessage());
            return 1;
        }

        try {
            return $this->runPayload($payload);
        } catch (RuntimeException $e) {
            $output->errorln($e->getMessage());
            return 1;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function runPayload(array $payload): int
    {
        $definitionData = is_array($payload['definition'] ?? null) ? $payload['definition'] : null;
        if (!is_array($definitionData)) {
            throw new RuntimeException('agent command: payload.definition must be an object');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'eleload_agent_');
        if ($tmpFile === false) {
            throw new RuntimeException('agent command: failed to create temporary file');
        }

        $json = json_encode($definitionData, JSON_THROW_ON_ERROR);
        file_put_contents($tmpFile, $json);

        try {
            $definition = (new ScenarioLoader())->load($tmpFile);
        } finally {
            @unlink($tmpFile);
        }

        $runner = new ScenarioRunner();
        $result = $runner->run(
            definition: $definition,
            concurrency: is_int($payload['concurrency'] ?? null) ? $payload['concurrency'] : 1,
            durationSec: isset($payload['duration_sec']) ? (float) $payload['duration_sec'] : null,
            iterations: is_int($payload['iterations'] ?? null) ? $payload['iterations'] : 1,
            warmupSec: isset($payload['warmup_sec']) ? (float) $payload['warmup_sec'] : 0.0,
        );

        $iterationResults = array_map(
            static fn ($iteration) => [
                'vu_id' => $iteration->vuId,
                'iteration_number' => $iteration->iterationNumber,
                'total_ms' => $iteration->totalMs,
                'elapsed_at_end_sec' => $iteration->elapsedAtEndSec,
                'success' => $iteration->success,
                'step_results' => array_map(
                    static fn ($stepResult) => [
                        'step_index' => $stepResult->stepIndex,
                        'step_name' => $stepResult->stepName,
                        'latency_ms' => $stepResult->latencyMs,
                        'http_code' => $stepResult->httpCode,
                        'error_no' => $stepResult->errorNo,
                        'error' => $stepResult->error,
                        'success' => $stepResult->success,
                    ],
                    $iteration->stepResults
                ),
            ],
            $result->iterationResults
        );

        echo json_encode([
            'success' => true,
            'duration_sec' => $result->durationSec,
            'warmup_sec' => $result->warmupSec,
            'iteration_results' => $iterationResults,
        ], JSON_THROW_ON_ERROR);

        return 0;
    }
}
