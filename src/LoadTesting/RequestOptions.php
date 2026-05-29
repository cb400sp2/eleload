<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

final class RequestOptions
{
    /**
     * @param list<string> $headers
     * @param list<int>|null $successStatusCodes
     */
    public function __construct(
        public readonly string $url,
        public readonly int $requests,
        public readonly int $concurrency,
        public readonly string $method,
        public readonly int $timeout,
        public readonly bool $followRedirects = false,
        public readonly array $headers = [],
        public readonly ?string $bearerToken = null,
        public readonly ?string $basicUser = null,
        public readonly ?string $basicPassword = null,
        public readonly ?string $cookie = null,
        public readonly ?string $body = null,
        public readonly ?string $name = null,
        public readonly ?array $successStatusCodes = null,
        public readonly ?float $durationSec = null,
        public readonly float $warmupSec = 0.0,
        public readonly ?float $targetRps = null,
        public readonly ?float $targetTps = null
    ) {
    }

    /**
     * @return list<string>
     */
    public function resolveHeaders(): array
    {
        $headers = $this->headers;
        $hasAuthorizationHeader = false;
        $hasCookieHeader = false;

        foreach ($headers as $header) {
            $lower = strtolower($header);
            $hasAuthorizationHeader = $hasAuthorizationHeader || str_starts_with($lower, 'authorization:');
            $hasCookieHeader = $hasCookieHeader || str_starts_with($lower, 'cookie:');
        }

        if (!$hasAuthorizationHeader) {
            if ($this->bearerToken !== null && $this->bearerToken !== '') {
                $headers[] = 'Authorization: Bearer ' . $this->bearerToken;
            } elseif ($this->basicUser !== null && $this->basicPassword !== null) {
                $token = base64_encode($this->basicUser . ':' . $this->basicPassword);
                $headers[] = 'Authorization: Basic ' . $token;
            }
        }

        if (!$hasCookieHeader && $this->cookie !== null && $this->cookie !== '') {
            $headers[] = 'Cookie: ' . $this->cookie;
        }

        return $headers;
    }
}
