<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

/**
 * Immutable value object that stores the outcome of a single HTTP request.
 */
final class RequestResult
{
    /**
     * @param int    $requestNumber        Sequential request number (1-based).
     * @param float  $latencyMs            Round-trip latency in milliseconds.
     * @param int    $httpCode             HTTP response status code (0 on curl error).
     * @param float  $downloadBytes        Number of bytes downloaded.
     * @param int    $errorNo              cURL error number (0 on success).
     * @param string $error                cURL error message (empty string on success).
     * @param bool   $includedInMetrics    False for requests excluded by the warmup window.
     * @param bool|null $bodyContainsExpected Whether the response body matched the expected string, or null if not checked.
     * @param float  $elapsedSec           Seconds elapsed from test start when this request completed.
     */
    public function __construct(
        public readonly int $requestNumber,
        public readonly float $latencyMs,
        public readonly int $httpCode,
        public readonly float $downloadBytes,
        public readonly int $errorNo,
        public readonly string $error,
        public readonly bool $includedInMetrics = true,
        public readonly ?bool $bodyContainsExpected = null,
        public readonly float $elapsedSec = 0.0
    ) {
    }

    /**
     * @param list<int>|null $successStatusCodes
     */
    public function isSuccess(?array $successStatusCodes = null): bool
    {
        if ($this->errorNo !== 0) {
            return false;
        }

        if ($successStatusCodes === null) {
            return $this->httpCode >= 200 && $this->httpCode < 400;
        }

        return in_array($this->httpCode, $successStatusCodes, true);
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'request_number' => $this->requestNumber,
            'latency_ms' => $this->latencyMs,
            'http_code' => $this->httpCode,
            'download_bytes' => $this->downloadBytes,
            'error_no' => $this->errorNo,
            'error' => $this->error,
            'included_in_metrics' => $this->includedInMetrics,
            'body_contains_expected' => $this->bodyContainsExpected,
            'elapsed_sec' => $this->elapsedSec,
        ];
    }

    /**
     * @param array<string, bool|float|int|string|null> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            requestNumber: (int) $payload['request_number'],
            latencyMs: (float) $payload['latency_ms'],
            httpCode: (int) $payload['http_code'],
            downloadBytes: (float) $payload['download_bytes'],
            errorNo: (int) $payload['error_no'],
            error: (string) $payload['error'],
            includedInMetrics: (bool) $payload['included_in_metrics'],
            bodyContainsExpected: is_bool($payload['body_contains_expected']) ? $payload['body_contains_expected'] : null,
            elapsedSec: (float) ($payload['elapsed_sec'] ?? 0.0)
        );
    }
}
