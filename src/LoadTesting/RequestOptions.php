<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

final class RequestOptions
{
    /**
     * @param list<string> $headers
     */
    public function __construct(
        public readonly string $url,
        public readonly int $requests,
        public readonly int $concurrency,
        public readonly string $method,
        public readonly int $timeout,
        public readonly array $headers = [],
        public readonly ?string $body = null,
        public readonly ?string $name = null,
        public readonly ?float $durationSec = null,
        public readonly float $warmupSec = 0.0,
        public readonly ?float $targetRps = null,
        public readonly ?float $targetTps = null
    ) {
    }
}
