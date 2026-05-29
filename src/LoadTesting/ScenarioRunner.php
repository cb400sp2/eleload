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

        $stepCount = count($definition->steps);
        $durationMode = $durationSec !== null;
        $startedAt = hrtime(true);

        // Initialize VU states
        $vus = [];
        for ($i = 0; $i < $concurrency; $i++) {
            $vus[$i] = $this->newVuState($i, $definition->variables, $startedAt);
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

                    // If all steps done, record completed iteration
                    if ($vu['step_index'] >= $stepCount) {
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
                                success: $allSuccess
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
                        $vu['step_index'] = 0;
                        $vu['step_results'] = [];
                        $vu['iteration_started_at_ns'] = hrtime(true);
                    }

                    // Check duration before starting a new step (handles mid-iteration expiry)
                    if ($durationMode && $elapsedSec >= (float) $durationSec) {
                        $vu['stopped'] = true;
                        continue;
                    }

                    // Start next step
                    $step = $definition->steps[$vu['step_index']];
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

                    $step = $definition->steps[$vus[$vuId]['step_index']];
                    $stepSuccess = $errorNo === 0 && $httpCode >= 200 && $httpCode < 300;

                    // Extract variables from response body
                    if (!empty($step->extract)) {
                        $extracted = $this->extractVariables($responseBody, $step->extract);
                        $vus[$vuId]['variables'] = array_merge($vus[$vuId]['variables'], $extracted);
                    }

                    $stepName = $step->name ?? 'Step ' . ($vus[$vuId]['step_index'] + 1);
                    $vus[$vuId]['step_results'][] = new ScenarioStepResult(
                        stepIndex: $vus[$vuId]['step_index'],
                        stepName: $stepName,
                        latencyMs: $latencyMs,
                        httpCode: $httpCode,
                        errorNo: $errorNo,
                        error: $error,
                        success: $stepSuccess
                    );

                    // Apply post-step wait
                    if ($step->waitMs > 0) {
                        $vus[$vuId]['wait_until_ns'] = hrtime(true) + (int) ($step->waitMs * self::NANOSECONDS_PER_MILLISECOND);
                    }

                    $vus[$vuId]['step_index']++;
                    $vus[$vuId]['in_flight'] = false;
                    $vus[$vuId]['handle'] = null;

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
     * Interpolate {{varName}} placeholders in a string.
     *
     * @param array<string, string> $variables
     */
    public function interpolate(string $template, array $variables): string
    {
        return (string) preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            static function (array $m) use ($variables): string {
                return $variables[$m[1]] ?? $m[0];
            },
            $template
        );
    }

    /**
     * Extract variables from a response body using json: or regex: expressions.
     *
     * @param array<string, string> $extractConfig
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
     * @return array<string, mixed>
     */
    private function newVuState(int $vuId, array $variables, int $startedAt): array
    {
        return [
            'id' => $vuId,
            'iteration' => 1,
            'step_index' => 0,
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
        $url = $this->interpolate($step->url, $variables);

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
            $headers[] = $this->interpolate($header, $variables);
        }

        if (!empty($headers)) {
            $curlOptions[CURLOPT_HTTPHEADER] = $headers;
        }

        if ($step->body !== null) {
            $curlOptions[CURLOPT_POSTFIELDS] = $this->interpolate($step->body, $variables);
        }

        // @phpstan-ignore-next-line (CURLOPT_CUSTOMREQUEST accepts any non-empty string; method is validated at parse time)
        curl_setopt_array($ch, $curlOptions);

        return $ch;
    }
}
