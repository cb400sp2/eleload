<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

use RuntimeException;

final class AgentRunner
{
    public function __construct(
        private readonly string $phpBinary,
        private readonly string $entryPoint,
        private readonly int $agents,
    ) {
    }

    public function run(
        ScenarioDefinition $definition,
        int $concurrency,
        ?float $durationSec,
        int $iterations,
        float $warmupSec,
    ): ScenarioResult {
        if ($this->agents <= 1) {
            return (new ScenarioRunner())->run($definition, $concurrency, $durationSec, $iterations, $warmupSec);
        }

        $baseConcurrency = intdiv($concurrency, $this->agents);
        $concurrencyRemainder = $concurrency % $this->agents;
        $baseIterations = intdiv($iterations, $this->agents);
        $iterationRemainder = $iterations % $this->agents;

        $processes = [];
        $startedAt = microtime(true);

        for ($i = 0; $i < $this->agents; $i++) {
            $agentConcurrency = $baseConcurrency + ($i < $concurrencyRemainder ? 1 : 0);
            $agentIterations = $baseIterations + ($i < $iterationRemainder ? 1 : 0);

            if ($agentConcurrency < 1) {
                $agentConcurrency = 1;
            }

            if ($agentIterations < 1) {
                $agentIterations = 1;
            }

            $payload = json_encode([
                'definition' => $this->serializeDefinition($definition),
                'concurrency' => $agentConcurrency,
                'duration_sec' => $durationSec,
                'iterations' => $agentIterations,
                'warmup_sec' => $warmupSec,
            ], JSON_THROW_ON_ERROR);

            $command = escapeshellarg($this->phpBinary) . ' ' . escapeshellarg($this->entryPoint) . ' agent';
            $descriptor = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptor, $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('Failed to spawn agent process ' . $i . '.');
            }

            fwrite($pipes[0], $payload);
            fclose($pipes[0]);

            $processes[] = [
                'process' => $process,
                'stdout' => $pipes[1],
                'stderr' => $pipes[2],
                'id' => $i,
            ];
        }

        $iterationResults = [];
        $durationFromAgents = 0.0;

        foreach ($processes as $processInfo) {
            $raw = stream_get_contents($processInfo['stdout']);
            fclose($processInfo['stdout']);
            fclose($processInfo['stderr']);
            proc_close($processInfo['process']);

            if (!is_string($raw) || $raw === '') {
                throw new RuntimeException('Agent ' . $processInfo['id'] . ' produced no output.');
            }

            $result = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($result) || !($result['success'] ?? false)) {
                $errorVal = is_array($result) ? ($result['error'] ?? null) : null;
                $message = is_string($errorVal) ? $errorVal : (is_array($result) ? 'unknown error' : 'invalid agent output');
                throw new RuntimeException('Agent ' . $processInfo['id'] . ' failed: ' . $message);
            }
            /** @var array<string, mixed> $result */

            $durationFromAgents = max($durationFromAgents, is_numeric($result['duration_sec'] ?? null) ? (float) $result['duration_sec'] : 0.0);

            /** @var list<mixed> $iterationResultsList */
            $iterationResultsList = is_array($result['iteration_results'] ?? null) ? array_values($result['iteration_results']) : [];
            foreach ($iterationResultsList as $iterationData) {
                /** @var array{vu_id: int, iteration_number: int, total_ms: float, elapsed_at_end_sec: float, step_results: list<array{step_index: int, step_name: string, latency_ms: float, http_code: int, error_no: int, error: string, success: bool}>, success: bool} $iterationData */
                $stepResultsData = $iterationData['step_results'];
                $stepResults = array_map(
                    static function (mixed $stepResult): ScenarioStepResult {
                        /** @var array{step_index: int, step_name: string, latency_ms: float, http_code: int, error_no: int, error: string, success: bool} $stepResult */
                        return new ScenarioStepResult(
                            stepIndex: $stepResult['step_index'],
                            stepName: $stepResult['step_name'],
                            latencyMs: $stepResult['latency_ms'],
                            httpCode: $stepResult['http_code'],
                            errorNo: $stepResult['error_no'],
                            error: $stepResult['error'],
                            success: $stepResult['success'],
                        );
                    },
                    $stepResultsData
                );

                $iterationResults[] = new ScenarioIterationResult(
                    vuId: $iterationData['vu_id'],
                    iterationNumber: $iterationData['iteration_number'],
                    totalMs: $iterationData['total_ms'],
                    elapsedAtEndSec: $iterationData['elapsed_at_end_sec'],
                    stepResults: $stepResults,
                    success: $iterationData['success'],
                );
            }
        }

        return new ScenarioResult(
            definition: $definition,
            durationSec: max($durationFromAgents, microtime(true) - $startedAt, 0.000001),
            warmupSec: $warmupSec,
            iterationResults: $iterationResults,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDefinition(ScenarioDefinition $definition): array
    {
        return [
            'name' => $definition->name,
            'variables' => $definition->variables,
            'steps' => array_map([$this, 'serializeStep'], $definition->steps),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeStep(ScenarioStep $step): array
    {
        $data = [
            'url' => $step->url,
            'method' => $step->method,
            'headers' => $step->headers,
            'timeout' => $step->timeout,
            'follow_redirects' => $step->followRedirects,
            'extract' => $step->extract,
        ];

        if ($step->body !== null) {
            $data['body'] = $step->body;
        }

        if ($step->connectTimeout !== null) {
            $data['connect_timeout'] = $step->connectTimeout;
        }

        if ($step->waitMs > 0) {
            $data['wait_ms'] = $step->waitMs;
        }

        if ($step->name !== null) {
            $data['name'] = $step->name;
        }

        if ($step->if !== null) {
            $data['if'] = [
                'field' => $step->if->condition->field,
                'op' => $step->if->condition->op,
                'value' => $step->if->condition->value,
                'then' => array_map([$this, 'serializeStep'], $step->if->then),
                'else' => array_map([$this, 'serializeStep'], $step->if->else),
            ];
        }

        return $data;
    }
}
