<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

use CurlHandle;
use CurlMultiHandle;
use InvalidArgumentException;
use RuntimeException;

final class ScenarioRunner
{
    private const MULTI_SELECT_TIMEOUT_SEC = 0.1;

    /** @var array<string, string> Variables shared across all VUs (global scope) */
    private array $globalVariables = [];
    private const IDLE_SLEEP_USEC = 5_000;
    private const NANOSECONDS_PER_MILLISECOND = 1_000_000;

    /**
     * Run a scenario definition with the given concurrency and duration settings.
     *
     * @param int $concurrency
     */
    public function run(
        ScenarioDefinition $definition,
        int $concurrency,
        ?float $durationSec,
        int $iterations,
        float $warmupSec = 0.0
    ): ScenarioResult {
        if ($concurrency < 1) {
            throw new InvalidArgumentException('concurrency must be >= 1.');
        }

        if ($durationSec === null && $iterations < 1) {
            throw new InvalidArgumentException('Either durationSec or iterations >= 1 must be provided.');
        }

        $multi = curl_multi_init();
        // @phpstan-ignore-next-line (curl_multi_init always returns CurlMultiHandle in PHP 8+)
        if (!$multi instanceof CurlMultiHandle) {
            throw new RuntimeException('Failed to initialize curl multi handle.');
        }

        $this->globalVariables = [];
        $durationMode = $durationSec !== null;
        $startedAt = hrtime(true);

        // Resolve variant assignment map (vuId => ScenarioVariant|null)
        $vuVariants = [];
        if ($definition->variants !== []) {
            $totalWeight = array_sum(array_map(fn ($v) => $v->weight, $definition->variants));
            $cumulative = 0.0;
            $thresholds = [];
            foreach ($definition->variants as $variant) {
                $cumulative += $variant->weight / $totalWeight;
                $thresholds[] = [$cumulative, $variant];
            }
            for ($i = 0; $i < $concurrency; $i++) {
                $p = $i / $concurrency;
                $assigned = $thresholds[count($thresholds) - 1][1]; // fallback to last
                foreach ($thresholds as [$threshold, $variant]) {
                    if ($p < $threshold) {
                        $assigned = $variant;
                        break;
                    }
                }
                $vuVariants[$i] = $assigned;
            }
        }

        // Initialize VU states
        $vus = [];
        for ($i = 0; $i < $concurrency; $i++) {
            $steps = isset($vuVariants[$i]) ? $vuVariants[$i]->steps : $definition->steps;
            $vus[$i] = $this->newVuState($i, $definition->variables, $steps, $startedAt);
            if (isset($vuVariants[$i])) {
                $vus[$i]['variant_name'] = $vuVariants[$i]->name;
            }
        }

        /** @var array<int, int> handleObjectId => vuId */
        $handleToVu = [];

        /** @var list<ScenarioIterationResult> */
        $iterationResults = [];
        $totalCompletedIterations = 0;

        try {
            while (true) {
                $now = hrtime(true);
                $elapsedSec = ($now - $startedAt) / 1_000_000_000;

                // Start requests for ready VUs
                foreach ($vus as $vuId => &$vu) {
                    if ($vu['in_flight'] || $vu['stopped']) {
                        continue;
                    }

                    if ($vu['wait_until_ns'] > 0 && $now < $vu['wait_until_ns']) {
                        continue;
                    }
                    $vu['wait_until_ns'] = 0;

                    // If step queue empty, record completed iteration
                    if (count($vu['step_queue']) === 0) {
                        $iterDurationMs = ($now - $vu['iteration_started_at_ns']) / 1_000_000;
                        $allSuccess = true;
                        foreach ($vu['step_results'] as $sr) {
                            if (!$sr->success) {
                                $allSuccess = false;
                                break;
                            }
                        }

                        $totalCompletedIterations++;

                        if ($elapsedSec >= $warmupSec) {
                            $iterationResults[] = new ScenarioIterationResult(
                                vuId: $vuId,
                                iterationNumber: $vu['iteration'],
                                totalMs: $iterDurationMs,
                                elapsedAtEndSec: $elapsedSec,
                                stepResults: $vu['step_results'],
                                success: $allSuccess,
                                variantName: $vu['variant_name'] ?? null,
                            );
                        }

                        // Decide whether this VU should continue
                        if ($durationMode) {
                            if ($elapsedSec >= (float) $durationSec) {
                                $vu['stopped'] = true;
                                continue;
                            }
                        } else {
                            if ($totalCompletedIterations >= $iterations) {
                                $vu['stopped'] = true;
                                continue;
                            }
                        }

                        // Reset VU for next iteration (variables carry over for auth token reuse)
                        $vu['iteration']++;
                        $vu['step_queue'] = isset($vuVariants[$vuId]) ? $vuVariants[$vuId]->steps : $definition->steps;
                        $vu['step_counter'] = 0;
                        $vu['step_results'] = [];
                        $vu['iteration_started_at_ns'] = hrtime(true);
                    }

                    // Check duration before starting a new step (handles mid-iteration expiry)
                    if ($durationMode && $elapsedSec >= (float) $durationSec) {
                        $vu['stopped'] = true;
                        continue;
                    }

                    // Pop next step from queue and start it
                    $step = array_shift($vu['step_queue']);
                    $vu['current_step'] = $step;
                    $handle = $this->createHandle($step, $vu['variables']);
                    $handleId = spl_object_id($handle);

                    $vu['handle'] = $handle;
                    $vu['in_flight'] = true;
                    $vu['step_started_at_ns'] = hrtime(true);
                    $handleToVu[$handleId] = $vuId;

                    curl_multi_add_handle($multi, $handle);
                }
                unset($vu);

                // Execute pending transfers
                do {
                    $mStatus = curl_multi_exec($multi, $running);
                } while ($mStatus === CURLM_CALL_MULTI_PERFORM);

                // Process completions
                while ($info = curl_multi_info_read($multi)) {
                    $handle = $info['handle'];
                    if (!$handle instanceof CurlHandle) {
                        continue;
                    }

                    $handleId = spl_object_id($handle);
                    $vuId = $handleToVu[$handleId] ?? null;

                    if ($vuId === null) {
                        curl_multi_remove_handle($multi, $handle);
                        continue;
                    }

                    $endedAt = hrtime(true);
                    $latencyMs = ($endedAt - $vus[$vuId]['step_started_at_ns']) / 1_000_000;
                    $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
                    $errorNo = curl_errno($handle);
                    $error = curl_error($handle);
                    $responseBody = (string) curl_multi_getcontent($handle);

                    /** @var ScenarioStep $step */
                    $step = $vus[$vuId]['current_step'];
                    $stepSuccess = $errorNo === 0 && $httpCode >= 200 && $httpCode < 300;

                    // Extract variables from response body
                    if (!empty($step->extract)) {
                        foreach ($step->extract as $varName => $config) {
                            $extracted = $this->extractVariables($responseBody, [$varName => $config['expr']]);
                            if (!array_key_exists($varName, $extracted)) {
                                continue;
                            }
                            if ($config['scope'] === 'global') {
                                $this->globalVariables[$varName] = $extracted[$varName];
                            } else {
                                $vus[$vuId]['variables'][$varName] = $extracted[$varName];
                            }
                        }
                    }

                    $stepIndex = $vus[$vuId]['step_counter'];
                    $stepName = $step->name ?? 'Step ' . ($stepIndex + 1);
                    $vus[$vuId]['step_results'][] = new ScenarioStepResult(
                        stepIndex: $stepIndex,
                        stepName: $stepName,
                        latencyMs: $latencyMs,
                        httpCode: $httpCode,
                        errorNo: $errorNo,
                        error: $error,
                        success: $stepSuccess
                    );
                    $vus[$vuId]['step_counter']++;

                    // Evaluate if/then/else branch and prepend branch steps to queue
                    if ($step->if !== null) {
                        $branch = $step->if->condition->evaluate($httpCode, $responseBody)
                            ? $step->if->then
                            : $step->if->else;
                        $vus[$vuId]['step_queue'] = array_merge($branch, $vus[$vuId]['step_queue']);
                    }

                    // Apply post-step wait
                    if ($step->waitMs > 0) {
                        $vus[$vuId]['wait_until_ns'] = hrtime(true) + (int) ($step->waitMs * self::NANOSECONDS_PER_MILLISECOND);
                    }

                    $vus[$vuId]['in_flight'] = false;
                    $vus[$vuId]['handle'] = null;
                    $vus[$vuId]['current_step'] = null;

                    unset($handleToVu[$handleId]);
                    curl_multi_remove_handle($multi, $handle);
                }

                // Check if all done
                $allDone = true;
                $hasInFlight = false;
                foreach ($vus as $vu) {
                    if ($vu['in_flight']) {
                        $hasInFlight = true;
                        $allDone = false;
                    } elseif (!$vu['stopped']) {
                        $allDone = false;
                    }
                }

                if ($allDone) {
                    break;
                }

                if ($hasInFlight) {
                    $selected = curl_multi_select($multi, self::MULTI_SELECT_TIMEOUT_SEC);
                    if ($selected === -1) {
                        usleep(self::IDLE_SLEEP_USEC);
                    }
                } else {
                    usleep(self::IDLE_SLEEP_USEC);
                }
            }
        } finally {
            curl_multi_close($multi);
        }

        $durationActual = (hrtime(true) - $startedAt) / 1_000_000_000;

        return new ScenarioResult(
            definition: $definition,
            durationSec: max($durationActual, 0.000_001),
            warmupSec: $warmupSec,
            iterationResults: $iterationResults
        );
    }

    /**
     * Interpolate {{varName}} and ${varName} placeholders in a string.
     * Per-VU variables take precedence over globals.
     *
     * @param array<string, string> $variables
     * @param array<string, string> $globals
     */
    public function interpolate(string $template, array $variables, array $globals = []): string
    {
        return (string) preg_replace_callback(
            '/\{\{(\w+)\}\}|\$\{(\w+)\}/',
            static function (array $m) use ($variables, $globals): string {
                $key = $m[1] !== '' ? $m[1] : $m[2];
                return $variables[$key] ?? $globals[$key] ?? $m[0];
            },
            $template
        );
    }

    /**
     * Extract variables from a response body using json: or regex: expressions.
     *
     * @param array<string, string> $extractConfig  map of varName => expression
     * @return array<string, string>
     */
    private function extractVariables(string $body, array $extractConfig): array
    {
        $result = [];

        foreach ($extractConfig as $varName => $expression) {
            if (str_starts_with($expression, 'json:')) {
                $path = ltrim(substr($expression, 5), '$.');
                $decoded = json_decode($body, true);

                if (!is_array($decoded)) {
                    continue;
                }

                $value = $decoded;
                foreach (explode('.', $path) as $part) {
                    if (is_array($value) && array_key_exists($part, $value)) {
                        $value = $value[$part];
                    } else {
                        $value = null;
                        break;
                    }
                }

                if (is_scalar($value)) {
                    $result[$varName] = (string) $value;
                }
            } elseif (str_starts_with($expression, 'regex:')) {
                $pattern = substr($expression, 6);

                if (@preg_match('/' . $pattern . '/', $body, $matches) === 1) {
                    $result[$varName] = $matches[1] ?? $matches[0];
                }
            }
        }

        return $result;
    }

    /**
     * @param array<string, string> $variables
     * @param list<ScenarioStep> $steps
     * @return array<string, mixed>
     */
    private function newVuState(int $vuId, array $variables, array $steps, int $startedAt): array
    {
        return [
            'id' => $vuId,
            'iteration' => 1,
            'step_queue' => $steps,
            'step_counter' => 0,
            'current_step' => null,
            'variables' => $variables,
            'handle' => null,
            'in_flight' => false,
            'step_started_at_ns' => 0,
            'iteration_started_at_ns' => $startedAt,
            'wait_until_ns' => 0,
            'step_results' => [],
            'stopped' => false,
        ];
    }

    /**
     * @param array<string, string> $variables
     */
    private function createHandle(ScenarioStep $step, array $variables): CurlHandle
    {
        $url = $this->interpolate($step->url, $variables, $this->globalVariables);

        $ch = curl_init($url);
        if (!$ch instanceof CurlHandle) {
            throw new RuntimeException("Failed to initialize curl handle for URL: {$url}");
        }

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $step->method,
            CURLOPT_TIMEOUT => $step->timeout,
            CURLOPT_CONNECTTIMEOUT => $step->connectTimeout ?? min($step->timeout, 5),
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => $step->followRedirects,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TCP_KEEPALIVE => 1,
        ];

        $headers = [];
        foreach ($step->headers as $header) {
            $headers[] = $this->interpolate($header, $variables, $this->globalVariables);
        }

        if (!empty($headers)) {
            $curlOptions[CURLOPT_HTTPHEADER] = $headers;
        }

        if ($step->body !== null) {
            $curlOptions[CURLOPT_POSTFIELDS] = $this->interpolate($step->body, $variables, $this->globalVariables);
        }

        // @phpstan-ignore-next-line (CURLOPT_CUSTOMREQUEST accepts any non-empty string; method is validated at parse time)
        curl_setopt_array($ch, $curlOptions);

        return $ch;
    }
}
