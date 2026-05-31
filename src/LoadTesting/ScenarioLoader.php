<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class ScenarioLoader
{
    private const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
    private const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

    public function load(string $path): ScenarioDefinition
    {
        // Path traversal guard: resolve and validate
        $real = realpath($path);
        if ($real === false) {
            throw new RuntimeException("Scenario file not found: {$path}");
        }

        if (!is_file($real)) {
            throw new RuntimeException("Scenario file not found: {$path}");
        }

        $size = filesize($real);
        if ($size === false || $size > self::MAX_FILE_SIZE_BYTES) {
            throw new RuntimeException(
                'Scenario file exceeds maximum allowed size (' . (self::MAX_FILE_SIZE_BYTES / 1024 / 1024) . " MB): {$path}"
            );
        }

        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));

        if ($ext === 'json') {
            return $this->loadJson($real);
        }

        if ($ext === 'yaml' || $ext === 'yml') {
            return $this->loadYaml($real);
        }

        throw new InvalidArgumentException(
            "Unsupported scenario file extension '.{$ext}'. Supported: .json, .yaml, .yml"
        );
    }

    private function loadJson(string $path): ScenarioDefinition
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read scenario file: {$path}");
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON in scenario file: ' . $e->getMessage());
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('Scenario file must be a JSON object.');
        }

        return $this->parseDefinition($data);
    }

    private function loadYaml(string $path): ScenarioDefinition
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read scenario file: {$path}");
        }

        if (extension_loaded('yaml')) {
            /** @var mixed $data */
            $data = yaml_parse($content);
            if ($data === false) {
                throw new InvalidArgumentException("Failed to parse YAML scenario file: {$path}");
            }
        } elseif (class_exists('Symfony\Component\Yaml\Yaml')) {
            /** @var mixed $data */
            $data = \Symfony\Component\Yaml\Yaml::parse($content);
        } else {
            throw new RuntimeException(
                'YAML scenario files require either the ext-yaml PHP extension or the symfony/yaml package. ' .
                'Install one: `pecl install yaml` or `composer require symfony/yaml`.'
            );
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('Scenario YAML file must be a mapping (object).');
        }

        return $this->parseDefinition($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseDefinition(array $data): ScenarioDefinition
    {
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

        // --- variants (optional) ---
        $variants = [];
        if (isset($data['variants'])) {
            if (!is_array($data['variants']) || count($data['variants']) === 0) {
                throw new InvalidArgumentException("Scenario 'variants' must be a non-empty array.");
            }
            foreach ($data['variants'] as $vi => $variantData) {
                $variants[] = $this->parseVariant($variantData, (int) $vi);
            }
        }

        // --- steps: required when no variants provided ---
        if ($variants === [] && (!isset($data['steps']) || !is_array($data['steps']) || count($data['steps']) === 0)) {
            throw new InvalidArgumentException('Scenario must have at least one step (or define variants).');
        }

        $steps = [];
        if (isset($data['steps']) && is_array($data['steps'])) {
            foreach ($data['steps'] as $i => $stepData) {
                $steps[] = $this->parseStep($stepData, (int) $i);
            }
        }

        return new ScenarioDefinition(name: $name, steps: $steps, variables: $variables, variants: $variants);
    }

    private function parseVariant(mixed $data, int $index): ScenarioVariant
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException("Variant {$index} must be a JSON object.");
        }

        $label = 'Variant ' . ($index + 1);

        if (!isset($data['name']) || !is_string($data['name']) || $data['name'] === '') {
            throw new InvalidArgumentException("{$label}: 'name' is required.");
        }

        $weight = 1.0;
        if (isset($data['weight'])) {
            if ((!is_int($data['weight']) && !is_float($data['weight'])) || $data['weight'] <= 0) {
                throw new InvalidArgumentException("{$label}: 'weight' must be a positive number.");
            }
            $weight = (float) $data['weight'];
        }

        if (!isset($data['steps']) || !is_array($data['steps']) || count($data['steps']) === 0) {
            throw new InvalidArgumentException("{$label}: 'steps' must be a non-empty array.");
        }

        $steps = [];
        foreach ($data['steps'] as $i => $stepData) {
            $steps[] = $this->parseStep($stepData, (int) $i);
        }

        return new ScenarioVariant(name: $data['name'], weight: $weight, steps: $steps);
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

            foreach ($data['extract'] as $varName => $entry) {
                if (!is_string($varName)) {
                    throw new InvalidArgumentException("{$label}: Extract variable names must be strings.");
                }

                // Shorthand: "varName": "json:$.path"  → scope defaults to 'vu'
                if (is_string($entry)) {
                    $expr = $entry;
                    $scope = 'vu';
                } elseif (is_array($entry)) {
                    // Long form: "varName": {"expr": "json:$.path", "scope": "global"}
                    if (!isset($entry['expr']) || !is_string($entry['expr'])) {
                        throw new InvalidArgumentException(
                            "{$label}: Extract entry for '{$varName}' must have a string 'expr' key."
                        );
                    }
                    $expr = $entry['expr'];
                    $scope = 'vu';
                    if (isset($entry['scope'])) {
                        if ($entry['scope'] !== 'vu' && $entry['scope'] !== 'global') {
                            throw new InvalidArgumentException(
                                "{$label}: Extract scope for '{$varName}' must be 'vu' or 'global'."
                            );
                        }
                        $scope = $entry['scope'];
                    }
                } else {
                    throw new InvalidArgumentException(
                        "{$label}: Extract entry for '{$varName}' must be a string or object."
                    );
                }

                if (!str_starts_with($expr, 'json:') && !str_starts_with($expr, 'regex:')) {
                    throw new InvalidArgumentException(
                        "{$label}: Extract expression for '{$varName}' must start with 'json:' or 'regex:'."
                    );
                }
                $extract[$varName] = ['expr' => $expr, 'scope' => $scope];
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
            extract: $extract,
            if: $this->parseIf($data['if'] ?? null, $label),
            thinkTime: $this->parseThinkTime($data['think_time'] ?? null, $label),
        );
    }

    private function parseThinkTime(mixed $data, string $parentLabel): ?ThinkTime
    {
        if ($data === null) {
            return null;
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException("{$parentLabel}: 'think_time' must be an object.");
        }

        $dist = $data['distribution'] ?? ThinkTime::DISTRIBUTION_FIXED;
        if (!is_string($dist) || !in_array($dist, ThinkTime::ALLOWED_DISTRIBUTIONS, true)) {
            throw new InvalidArgumentException(
                "{$parentLabel}: 'think_time.distribution' must be one of: "
                . implode(', ', ThinkTime::ALLOWED_DISTRIBUTIONS)
            );
        }

        switch ($dist) {
            case ThinkTime::DISTRIBUTION_FIXED:
                if (!isset($data['ms']) || (!is_int($data['ms']) && !is_float($data['ms']))) {
                    throw new InvalidArgumentException(
                        "{$parentLabel}: 'think_time' with distribution 'fixed' requires a numeric 'ms'."
                    );
                }
                return new ThinkTime(ThinkTime::DISTRIBUTION_FIXED, (float) $data['ms']);

            case ThinkTime::DISTRIBUTION_RANDOM:
                if (!isset($data['min_ms']) || (!is_int($data['min_ms']) && !is_float($data['min_ms']))) {
                    throw new InvalidArgumentException(
                        "{$parentLabel}: 'think_time' with distribution 'random' requires a numeric 'min_ms'."
                    );
                }
                if (!isset($data['max_ms']) || (!is_int($data['max_ms']) && !is_float($data['max_ms']))) {
                    throw new InvalidArgumentException(
                        "{$parentLabel}: 'think_time' with distribution 'random' requires a numeric 'max_ms'."
                    );
                }
                return new ThinkTime(ThinkTime::DISTRIBUTION_RANDOM, (float) $data['min_ms'], (float) $data['max_ms']);

            case ThinkTime::DISTRIBUTION_EXPONENTIAL:
                if (!isset($data['mean_ms']) || (!is_int($data['mean_ms']) && !is_float($data['mean_ms']))) {
                    throw new InvalidArgumentException(
                        "{$parentLabel}: 'think_time' with distribution 'exponential' requires a numeric 'mean_ms'."
                    );
                }
                return new ThinkTime(ThinkTime::DISTRIBUTION_EXPONENTIAL, (float) $data['mean_ms']);

            default:
                return null; // unreachable
        }
    }

    private function parseIf(mixed $data, string $parentLabel): ?ScenarioBranch
    {
        if ($data === null) {
            return null;
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException("{$parentLabel}: 'if' must be an object.");
        }

        // Parse condition
        if (!isset($data['field']) || !is_string($data['field'])) {
            throw new InvalidArgumentException("{$parentLabel}: 'if.field' is required and must be a string.");
        }
        if (!in_array($data['field'], ScenarioCondition::ALLOWED_FIELDS, true)) {
            throw new InvalidArgumentException(
                "{$parentLabel}: 'if.field' must be one of: " . implode(', ', ScenarioCondition::ALLOWED_FIELDS)
            );
        }
        /** @var 'status'|'body' $field */
        $field = $data['field'];

        if (!isset($data['op']) || !is_string($data['op'])) {
            throw new InvalidArgumentException("{$parentLabel}: 'if.op' is required and must be a string.");
        }
        if (!in_array($data['op'], ScenarioCondition::ALLOWED_OPS, true)) {
            throw new InvalidArgumentException(
                "{$parentLabel}: 'if.op' must be one of: " . implode(', ', ScenarioCondition::ALLOWED_OPS)
            );
        }
        /** @var '=='|'!='|'<'|'>'|'contains'|'regex_match' $op */
        $op = $data['op'];

        if (!isset($data['value']) || (!is_string($data['value']) && !is_int($data['value']))) {
            throw new InvalidArgumentException("{$parentLabel}: 'if.value' is required and must be a string or integer.");
        }

        $condition = new ScenarioCondition(
            field: $field,
            op:    $op,
            value: $data['value']
        );

        // Parse then/else branch steps
        $thenSteps = [];
        if (isset($data['then'])) {
            if (!is_array($data['then'])) {
                throw new InvalidArgumentException("{$parentLabel}: 'if.then' must be an array of steps.");
            }
            foreach ($data['then'] as $i => $stepData) {
                $thenSteps[] = $this->parseStep($stepData, (int) $i);
            }
        }

        $elseSteps = [];
        if (isset($data['else'])) {
            if (!is_array($data['else'])) {
                throw new InvalidArgumentException("{$parentLabel}: 'if.else' must be an array of steps.");
            }
            foreach ($data['else'] as $i => $stepData) {
                $elseSteps[] = $this->parseStep($stepData, (int) $i);
            }
        }

        return new ScenarioBranch(condition: $condition, then: $thenSteps, else: $elseSteps);
    }
}
