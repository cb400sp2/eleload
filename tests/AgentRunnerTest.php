<?php

declare(strict_types=1);

use Eleload\LoadTesting\AgentRunner;
use Eleload\LoadTesting\ScenarioDefinition;
use Eleload\LoadTesting\ScenarioStep;

test('AgentRunner merges results from local agent processes', function (): void {
    $agentScript = tempnam(sys_get_temp_dir(), 'eleload_agent_script_');
    assertTrue($agentScript !== false);
    file_put_contents($agentScript, <<<'PHP'
<?php

declare(strict_types=1);

stream_get_contents(STDIN);

echo json_encode([
    'success' => true,
    'duration_sec' => 0.123,
    'warmup_sec' => 0.0,
    'iteration_results' => [
        [
            'vu_id' => 1,
            'iteration_number' => 1,
            'total_ms' => 12.3,
            'elapsed_at_end_sec' => 0.1,
            'success' => true,
            'step_results' => [
                [
                    'step_index' => 0,
                    'step_name' => 'stub',
                    'latency_ms' => 12.3,
                    'http_code' => 200,
                    'error_no' => 0,
                    'error' => '',
                    'success' => true,
                ],
            ],
        ],
    ],
], JSON_THROW_ON_ERROR);
PHP
    );

    try {
        $definition = new ScenarioDefinition(
            name: 'Agent Runner Scenario',
            steps: [
                new ScenarioStep(url: 'http://example.test'),
            ],
        );

        $runner = new AgentRunner(PHP_BINARY, $agentScript, 2);
        $result = $runner->run(
            definition: $definition,
            concurrency: 2,
            durationSec: null,
            iterations: 2,
            warmupSec: 0.0,
        );

        assertSame('Agent Runner Scenario', $result->definition->name);
        assertSame(2, count($result->iterationResults));
        assertTrue($result->durationSec > 0.0);
    } finally {
        @unlink($agentScript);
    }
});
