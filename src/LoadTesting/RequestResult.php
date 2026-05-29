<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

final class RequestResult
{
    public function __construct(
        public readonly int $requestNumber,
        public readonly float $latencyMs,
        public readonly int $httpCode,
        public readonly float $downloadBytes,
        public readonly int $errorNo,
        public readonly string $error,
        public readonly bool $includedInMetrics = true,
        public readonly ?bool $bodyContainsExpected = null
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
            bodyContainsExpected: is_bool($payload['body_contains_expected']) ? $payload['body_contains_expected'] : null
        );
    }
}
