<?php

declare(strict_types=1);

namespace Eleload\Cli\Support;

use RuntimeException;

final class JsonFileReader
{
    /**
     * Reads a JSON file and returns its contents as an associative array.
     *
     * @return array<string, mixed>
     * @throws RuntimeException if the file cannot be read or is invalid JSON.
     */
    public static function readObject(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("JSON report file not found: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Failed to read JSON report: {$path}");
        }

        $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($report)) {
            throw new RuntimeException('Invalid JSON report format: root must be an object');
        }

        return $report;
    }
}
