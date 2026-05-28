<?php

declare(strict_types=1);

namespace Eleload\Cli;

use InvalidArgumentException;

final class ArgvParser
{
    private const DEFAULT_REQUESTS = 100;
    private const DEFAULT_CONCURRENCY = 10;
    private const DEFAULT_TIMEOUT = 10;

    /**
     * @param list<string> $args
     */
    public function parseRun(array $args): RunOptions
    {
        $url = null;
        $method = 'GET';
        $requests = self::DEFAULT_REQUESTS;
        $concurrency = self::DEFAULT_CONCURRENCY;
        $timeout = self::DEFAULT_TIMEOUT;
        $headers = [];
        $body = null;
        $reportJsonPath = null;
        $reportHtmlPath = null;
        $targetRps = null;
        $targetTps = null;

        $i = 0;
        while ($i < count($args)) {
            $token = $args[$i];

            if ($this->isOption($token)) {
                [$name, $value, $i] = $this->parseOptionToken($args, $i);

                switch ($name) {
                    case 'requests':
                        $requests = $this->parsePositiveInt($name, $value);
                        break;
                    case 'concurrency':
                        $concurrency = $this->parsePositiveInt($name, $value);
                        break;
                    case 'method':
                        $method = strtoupper(trim($value));
                        break;
                    case 'timeout':
                        $timeout = $this->parsePositiveInt($name, $value);
                        break;
                    case 'header':
                        $headers[] = $value;
                        break;
                    case 'body':
                        $body = $value;
                        break;
                    case 'report-json':
                        $reportJsonPath = $value;
                        break;
                    case 'report-html':
                        $reportHtmlPath = $value;
                        break;
                    case 'target-rps':
                        $targetRps = $this->parsePositiveFloat($name, $value);
                        break;
                    case 'target-tps':
                        $targetTps = $this->parsePositiveFloat($name, $value);
                        break;
                    default:
                        throw new InvalidArgumentException("Unknown option: --{$name}");
                }

                continue;
            }

            if ($url === null) {
                $url = $token;
                $i++;
                continue;
            }

            throw new InvalidArgumentException("Unexpected argument: {$token}");
        }

        if ($url === null) {
            throw new InvalidArgumentException('URL is required. Usage: phpload run <url> [options]');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid URL: {$url}");
        }

        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
        if (!in_array($method, $allowedMethods, true)) {
            throw new InvalidArgumentException(
                'Invalid HTTP method. Allowed: ' . implode(', ', $allowedMethods)
            );
        }

        return new RunOptions(
            url: $url,
            requests: $requests,
            concurrency: $concurrency,
            method: $method,
            timeout: $timeout,
            headers: $headers,
            body: $body,
            reportJsonPath: $reportJsonPath,
            reportHtmlPath: $reportHtmlPath,
            targetRps: $targetRps,
            targetTps: $targetTps
        );
    }

    /**
     * @param list<string> $args
     * @return array{0:string, 1:string, 2:int}
     */
    private function parseOptionToken(array $args, int $index): array
    {
        $token = $args[$index];
        $payload = substr($token, 2);

        if ($payload === '') {
            throw new InvalidArgumentException('Empty option is not allowed.');
        }

        $eqPos = strpos($payload, '=');
        if ($eqPos !== false) {
            $name = substr($payload, 0, $eqPos);
            $value = substr($payload, $eqPos + 1);
            if ($value === '') {
                throw new InvalidArgumentException("Option --{$name} requires a value.");
            }

            return [$name, $value, $index + 1];
        }

        $name = $payload;
        $nextIndex = $index + 1;
        if (!array_key_exists($nextIndex, $args)) {
            throw new InvalidArgumentException("Option --{$name} requires a value.");
        }

        $value = $args[$nextIndex];
        if ($this->isOption($value)) {
            throw new InvalidArgumentException("Option --{$name} requires a value.");
        }

        return [$name, $value, $nextIndex + 1];
    }

    private function isOption(string $token): bool
    {
        return str_starts_with($token, '--');
    }

    private function parsePositiveInt(string $name, string $value): int
    {
        if (!preg_match('/^\d+$/', $value)) {
            throw new InvalidArgumentException("Option --{$name} must be a positive integer.");
        }

        $parsed = (int)$value;
        if ($parsed < 1) {
            throw new InvalidArgumentException("Option --{$name} must be >= 1.");
        }

        return $parsed;
    }

    private function parsePositiveFloat(string $name, string $value): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("Option --{$name} must be numeric.");
        }

        $parsed = (float)$value;
        if ($parsed <= 0.0) {
            throw new InvalidArgumentException("Option --{$name} must be > 0.");
        }

        return $parsed;
    }
}

