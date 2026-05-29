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
}
