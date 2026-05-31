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
                $message = is_array($result) ? (string) ($result['error'] ?? 'unknown error') : 'invalid agent output';
                throw new RuntimeException('Agent ' . $processInfo['id'] . ' failed: ' . $message);
            }

            $durationFromAgents = max($durationFromAgents, (float) ($result['duration_sec'] ?? 0.0));

            foreach ($result['iteration_results'] ?? [] as $iterationData) {
                $stepResults = array_map(
                    static fn (array $stepResult) => new ScenarioStepResult(
                        stepIndex: (int) ($stepResult['step_index'] ?? 0),
                        stepName: (string) ($stepResult['step_name'] ?? ''),
                        latencyMs: (float) ($stepResult['latency_ms'] ?? 0.0),
                        httpCode: (int) ($stepResult['http_code'] ?? 0),
                        errorNo: (int) ($stepResult['error_no'] ?? 0),
                        error: (string) ($stepResult['error'] ?? ''),
                        success: (bool) ($stepResult['success'] ?? false),
                    ),
                    is_array($iterationData['step_results'] ?? null)
                        ? array_values($iterationData['step_results'])
                        : []
                );

                $iterationResults[] = new ScenarioIterationResult(
                    vuId: (int) ($iterationData['vu_id'] ?? 0),
                    iterationNumber: (int) ($iterationData['iteration_number'] ?? 0),
                    totalMs: (float) ($iterationData['total_ms'] ?? 0.0),
                    elapsedAtEndSec: (float) ($iterationData['elapsed_at_end_sec'] ?? 0.0),
                    stepResults: $stepResults,
                    success: (bool) ($iterationData['success'] ?? false),
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
