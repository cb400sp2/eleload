<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

use JsonException;
use RuntimeException;

/**
 * Holds the aggregated outcome of a complete load-test run, including per-request results.
 *
 * When the number of results exceeds the in-memory limit they are spilled to a temporary
 * file; {@see iterateRequestResults()} transparently handles both cases.
 */
final class RunResult
{
    /**
     * @param list<RequestResult> $requestResults
     */
    public function __construct(
        public readonly RequestOptions $options,
        public readonly float $durationSec,
        public readonly array $requestResults,
        private readonly ?string $requestResultsPath = null,
        private readonly ?int $requestResultCount = null
    ) {
    }

    /**
     * Returns the total number of request results (including spilled results).
     */
    public function countRequestResults(): int
    {
        return $this->requestResultCount ?? count($this->requestResults);
    }

    /**
     * Returns true when results were spilled to a temporary file on disk.
     */
    public function hasSpilledRequestResults(): bool
    {
        return $this->requestResultsPath !== null;
    }

    /**
     * @return iterable<RequestResult>
     * @throws JsonException
     */
    public function iterateRequestResults(): iterable
    {
        if ($this->requestResultsPath === null) {
            yield from $this->requestResults;
            return;
        }

        $handle = fopen($this->requestResultsPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Failed to open spilled request results.');
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $payload = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($payload)) {
                    throw new RuntimeException('Invalid spilled request result payload.');
                }

                yield RequestResult::fromArray($payload);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Removes the temporary spill file, if one was created.
     */
    public function __destruct()
    {
        if ($this->requestResultsPath !== null && is_file($this->requestResultsPath)) {
            @unlink($this->requestResultsPath);
        }
    }
}
