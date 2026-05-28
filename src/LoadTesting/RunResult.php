<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

final class RunResult
{
    /**
     * @param list<RequestResult> $requestResults
     */
    public function __construct(
        public readonly RequestOptions $options,
        public readonly float $durationSec,
        public readonly array $requestResults
    ) {
    }
}

