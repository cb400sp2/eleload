<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

use CurlHandle;
use CurlMultiHandle;
use RuntimeException;

final class CurlMultiRunner
{
    public function run(RequestOptions $options): RunResult
    {
        $multi = curl_multi_init();
        if (!$multi instanceof CurlMultiHandle) {
            throw new RuntimeException('Failed to initialize curl multi handle.');
        }

        $nextRequest = 1;
        $completed = 0;
        $running = 0;
        $inFlight = [];
        $results = [];
        $startedAt = hrtime(true);

        while ($completed < $options->requests) {
            while ($nextRequest <= $options->requests && count($inFlight) < $options->concurrency) {
                $handle = $this->createHandle($options);
                $handleId = spl_object_id($handle);

                $inFlight[$handleId] = [
                    'request_number' => $nextRequest,
                    'started_at' => hrtime(true),
                    'handle' => $handle,
                ];

                curl_multi_add_handle($multi, $handle);
                $nextRequest++;
            }

            do {
                $status = curl_multi_exec($multi, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            while ($info = curl_multi_info_read($multi)) {
                $handle = $info['handle'];
                if (!$handle instanceof CurlHandle) {
                    continue;
                }

                $handleId = spl_object_id($handle);
                $meta = $inFlight[$handleId] ?? null;
                if ($meta === null) {
                    curl_multi_remove_handle($multi, $handle);
                    continue;
                }

                $latencyMs = (hrtime(true) - $meta['started_at']) / 1_000_000;
                $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
                $downloadBytes = (float) curl_getinfo($handle, CURLINFO_SIZE_DOWNLOAD);
                $errorNo = curl_errno($handle);
                $error = curl_error($handle);

                $results[] = new RequestResult(
                    requestNumber: (int)$meta['request_number'],
                    latencyMs: $latencyMs,
                    httpCode: $httpCode,
                    downloadBytes: $downloadBytes,
                    errorNo: $errorNo,
                    error: $error
                );

                unset($inFlight[$handleId]);
                curl_multi_remove_handle($multi, $handle);
                $completed++;
            }

            if ($running > 0) {
                $selected = curl_multi_select($multi, 1.0);
                if ($selected === -1) {
                    usleep(1_000);
                }
            }
        }

        $durationSec = (hrtime(true) - $startedAt) / 1_000_000_000;
        curl_multi_close($multi);

        return new RunResult(
            options: $options,
            durationSec: max($durationSec, 0.000_001),
            requestResults: $results
        );
    }

    private function createHandle(RequestOptions $options): CurlHandle
    {
        $ch = curl_init($options->url);
        if (!$ch instanceof CurlHandle) {
            throw new RuntimeException('Failed to initialize curl handle.');
        }

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $options->method,
            CURLOPT_TIMEOUT => $options->timeout,
            CURLOPT_CONNECTTIMEOUT => min($options->timeout, 5),
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if (!empty($options->headers)) {
            $curlOptions[CURLOPT_HTTPHEADER] = $options->headers;
        }

        if ($options->body !== null) {
            $curlOptions[CURLOPT_POSTFIELDS] = $options->body;
        }

        curl_setopt_array($ch, $curlOptions);

        return $ch;
    }
}
