<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class ScenarioLoader
{
    private const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    public function load(string $path): ScenarioDefinition
    {
        if (!is_file($path)) {
            throw new RuntimeException("Scenario file not found: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Failed to read scenario file: {$path}");
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON in scenario file: ' . $e->getMessage());
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('Scenario file must be a JSON object.');
        }

        $name = isset($data['name']) && is_string($data['name']) ? $data['name'] : 'Unnamed Scenario';

        $variables = [];
        if (isset($data['variables'])) {
            if (!is_array($data['variables'])) {
                throw new InvalidArgumentException("Scenario 'variables' must be an object.");
            }

            foreach ($data['variables'] as $k => $v) {
                if (!is_string($k) || !is_scalar($v)) {
                    throw new InvalidArgumentException(
                        "Scenario variable '{$k}' must have a scalar value."
                    );
                }
                $variables[$k] = (string) $v;
            }
        }

        if (!isset($data['steps']) || !is_array($data['steps']) || count($data['steps']) === 0) {
            throw new InvalidArgumentException('Scenario must have at least one step.');
        }

        $steps = [];
        foreach ($data['steps'] as $i => $stepData) {
            $steps[] = $this->parseStep($stepData, (int) $i);
        }

        return new ScenarioDefinition(name: $name, steps: $steps, variables: $variables);
    }

    private function parseStep(mixed $data, int $index): ScenarioStep
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException("Step {$index} must be a JSON object.");
        }

        $label = 'Step ' . ($index + 1);

        if (!isset($data['url']) || !is_string($data['url']) || $data['url'] === '') {
            throw new InvalidArgumentException("{$label}: 'url' is required.");
        }
        $url = $data['url'];

        $method = 'GET';
        if (isset($data['method'])) {
            if (!is_string($data['method'])) {
                throw new InvalidArgumentException("{$label}: 'method' must be a string.");
            }

            $method = strtoupper($data['method']);
            if (!in_array($method, self::ALLOWED_METHODS, true)) {
                throw new InvalidArgumentException(
                    "{$label}: Invalid method '{$method}'. Allowed: " . implode(', ', self::ALLOWED_METHODS)
                );
            }
        }

        $headers = [];
        if (isset($data['headers'])) {
            if (!is_array($data['headers'])) {
                throw new InvalidArgumentException("{$label}: 'headers' must be an array.");
            }

            foreach ($data['headers'] as $h) {
                if (!is_string($h)) {
                    throw new InvalidArgumentException("{$label}: Each header must be a string.");
                }
                $headers[] = $h;
            }
        }

        $body = null;
        if (isset($data['body'])) {
            if (is_array($data['body'])) {
                $body = json_encode(
                    $data['body'],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
            } elseif (is_string($data['body'])) {
                $body = $data['body'];
            } else {
                throw new InvalidArgumentException("{$label}: 'body' must be a string or object.");
            }
        }

        $timeout = 10;
        if (isset($data['timeout'])) {
            if (!is_int($data['timeout']) || $data['timeout'] < 1) {
                throw new InvalidArgumentException("{$label}: 'timeout' must be a positive integer.");
            }
            $timeout = $data['timeout'];
        }

        $connectTimeout = null;
        if (isset($data['connect_timeout'])) {
            if (!is_int($data['connect_timeout']) || $data['connect_timeout'] < 1) {
                throw new InvalidArgumentException(
                    "{$label}: 'connect_timeout' must be a positive integer."
                );
            }
            $connectTimeout = $data['connect_timeout'];
        }

        $waitMs = 0;
        if (isset($data['wait_ms'])) {
            if (!is_int($data['wait_ms']) || $data['wait_ms'] < 0) {
                throw new InvalidArgumentException("{$label}: 'wait_ms' must be a non-negative integer.");
            }
            $waitMs = $data['wait_ms'];
        }

        $stepName = null;
        if (isset($data['name'])) {
            if (!is_string($data['name'])) {
                throw new InvalidArgumentException("{$label}: 'name' must be a string.");
            }
            $stepName = $data['name'];
        }

        $followRedirects = false;
        if (isset($data['follow_redirects'])) {
            if (!is_bool($data['follow_redirects'])) {
                throw new InvalidArgumentException("{$label}: 'follow_redirects' must be a boolean.");
            }
            $followRedirects = $data['follow_redirects'];
        }

        $extract = [];
        if (isset($data['extract'])) {
            if (!is_array($data['extract'])) {
                throw new InvalidArgumentException("{$label}: 'extract' must be an object.");
            }

            foreach ($data['extract'] as $varName => $expression) {
                if (!is_string($varName) || !is_string($expression)) {
                    throw new InvalidArgumentException(
                        "{$label}: Extract entries must be string key-value pairs."
                    );
                }

                if (!str_starts_with($expression, 'json:') && !str_starts_with($expression, 'regex:')) {
                    throw new InvalidArgumentException(
                        "{$label}: Extract expression for '{$varName}' must start with 'json:' or 'regex:'."
                    );
                }
                $extract[$varName] = $expression;
            }
        }

        return new ScenarioStep(
            url: $url,
            method: $method,
            headers: $headers,
            body: $body,
            timeout: $timeout,
            connectTimeout: $connectTimeout,
            waitMs: $waitMs,
            name: $stepName,
            followRedirects: $followRedirects,
            extract: $extract
        );
    }
}
