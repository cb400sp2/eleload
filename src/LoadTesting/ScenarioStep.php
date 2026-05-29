<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

final class ScenarioStep
{
    /**
     * @param list<string> $headers
     * @param array<string, string> $extract  varName => "json:$.path" | "regex:pattern"
     */
    public function __construct(
        public readonly string $url,
        public readonly string $method = 'GET',
        public readonly array $headers = [],
        public readonly ?string $body = null,
        public readonly int $timeout = 10,
        public readonly ?int $connectTimeout = null,
        public readonly int $waitMs = 0,
        public readonly ?string $name = null,
        public readonly bool $followRedirects = false,
        public readonly array $extract = [],
        public readonly ?ScenarioBranch $if = null,
    ) {
    }
}
