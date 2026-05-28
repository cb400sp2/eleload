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
        public readonly string $error
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->errorNo === 0 && $this->httpCode >= 200 && $this->httpCode < 400;
    }
}

