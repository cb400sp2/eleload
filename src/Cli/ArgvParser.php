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
        $connectTimeout = null;
        $silent = false;
        $verbose = false;
        $debug = false;
        $yes = false;
        $allowHighLoad = false;
        $followRedirects = false;
        $headers = [];
        $bearerToken = null;
        $basicUser = null;
        $basicPassword = null;
        $cookie = null;
        $body = null;
        $reportJsonPath = null;
        $reportHtmlPath = null;
        $reportMdPath = null;
        $reportCsvPath = null;
        $outputDir = null;
        $testName = null;
        $successStatusCodes = null;
        $expectStatusCodes = null;
        $expectBodyContains = null;
        $durationSec = null;
        $warmupSec = 0.0;
        $failOnP95 = null;
        $failOnP99 = null;
        $failOnErrorRate = null;
        $failOnRpsBelow = null;
        $failOnTpsBelow = null;
        $targetRps = null;
        $targetTps = null;
        $rampUpSec = 0.0;

        $i = 0;
        while ($i < count($args)) {
            $token = $args[$i];

            if ($token === '--follow-redirects') {
                $followRedirects = true;
                $i++;
                continue;
            }

            if ($token === '--silent') {
                $silent = true;
                $i++;
                continue;
            }

            if ($token === '--verbose') {
                $verbose = true;
                $i++;
                continue;
            }

            if ($token === '--debug') {
                $debug = true;
                $i++;
                continue;
            }

            if ($token === '--yes') {
                $yes = true;
                $i++;
                continue;
            }

            if ($token === '--allow-high-load') {
                $allowHighLoad = true;
                $i++;
                continue;
            }

            if ($token === '--no-follow-redirects') {
                $followRedirects = false;
                $i++;
                continue;
            }

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
                    case 'connect-timeout':
                        $connectTimeout = $this->parsePositiveInt($name, $value);
                        break;
                    case 'header':
                        $headers[] = $value;
                        break;
                    case 'bearer-token':
                        $bearerToken = $value;
                        break;
                    case 'basic-user':
                        $basicUser = $value;
                        break;
                    case 'basic-password':
                        $basicPassword = $value;
                        break;
                    case 'cookie':
                        $cookie = $value;
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
                    case 'report-md':
                        $reportMdPath = $value;
                        break;
                    case 'report-csv':
                        $reportCsvPath = $value;
                        break;
                    case 'output-dir':
                        $outputDir = $value;
                        break;
                    case 'name':
                        $testName = $value;
                        break;
                    case 'success-status':
                        $successStatusCodes = $this->parseStatusCodeList($name, $value);
                        break;
                    case 'expect-status':
                        $expectStatusCodes = $this->parseStatusCodeList($name, $value);
                        break;
                    case 'expect-body-contains':
                        $expectBodyContains = $value;
                        break;
                    case 'duration':
                        $durationSec = $this->parsePositiveFloat($name, $value);
                        break;
                    case 'warmup':
                        $warmupSec = $this->parseNonNegativeFloat($name, $value);
                        break;
                    case 'fail-on-p95':
                        $failOnP95 = $this->parsePositiveFloat($name, $value);
                        break;
                    case 'fail-on-p99':
                        $failOnP99 = $this->parsePositiveFloat($name, $value);
                        break;
                    case 'fail-on-error-rate':
                        $failOnErrorRate = $this->parsePercent($name, $value);
                        break;
                    case 'fail-on-rps-below':
                        $failOnRpsBelow = $this->parsePositiveFloat($name, $value);
                        break;
                    case 'fail-on-tps-below':
                        $failOnTpsBelow = $this->parsePositiveFloat($name, $value);
                        break;
                    case 'target-rps':
                        $targetRps = $this->parsePositiveFloat($name, $value);
                        break;
                    case 'target-tps':
                        $targetTps = $this->parsePositiveFloat($name, $value);
                        break;
                    case 'ramp-up':
                        $rampUpSec = $this->parseNonNegativeFloat($name, $value);
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
            throw new InvalidArgumentException('URL is required. Usage: eleload run <url> [options]');
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

        if ($durationSec !== null && $warmupSec >= $durationSec) {
            throw new InvalidArgumentException('Option --warmup must be lower than --duration.');
        }

        if (($basicUser === null) !== ($basicPassword === null)) {
            throw new InvalidArgumentException('Options --basic-user and --basic-password must be provided together.');
        }

        return new RunOptions(
            url: $url,
            requests: $requests,
            concurrency: $concurrency,
            method: $method,
            timeout: $timeout,
            connectTimeout: $connectTimeout,
            silent: $silent,
            verbose: $verbose,
            debug: $debug,
            yes: $yes,
            allowHighLoad: $allowHighLoad,
            followRedirects: $followRedirects,
            headers: $headers,
            bearerToken: $bearerToken,
            basicUser: $basicUser,
            basicPassword: $basicPassword,
            cookie: $cookie,
            body: $body,
            reportJsonPath: $reportJsonPath,
            reportHtmlPath: $reportHtmlPath,
            reportMdPath: $reportMdPath,
            reportCsvPath: $reportCsvPath,
            outputDir: $outputDir,
            name: $testName,
            successStatusCodes: $successStatusCodes,
            expectStatusCodes: $expectStatusCodes,
            expectBodyContains: $expectBodyContains,
            durationSec: $durationSec,
            warmupSec: $warmupSec,
            failOnP95: $failOnP95,
            failOnP99: $failOnP99,
            failOnErrorRate: $failOnErrorRate,
            failOnRpsBelow: $failOnRpsBelow,
            failOnTpsBelow: $failOnTpsBelow,
            targetRps: $targetRps,
            targetTps: $targetTps,
            rampUpSec: $rampUpSec
        );
    }

    /**
     * @param list<string> $args
     */
    public function parseReport(array $args): ReportOptions
    {
        $jsonPath = null;
        $htmlPath = null;

        $i = 0;
        while ($i < count($args)) {
            $token = $args[$i];

            if ($this->isOption($token)) {
                [$name, $value, $i] = $this->parseOptionToken($args, $i);

                if ($name !== 'html') {
                    throw new InvalidArgumentException("Unknown option for report command: --{$name}");
                }

                $htmlPath = $value;
                continue;
            }

            if ($jsonPath === null) {
                $jsonPath = $token;
                $i++;
                continue;
            }

            throw new InvalidArgumentException("Unexpected argument for report command: {$token}");
        }

        if ($jsonPath === null) {
            throw new InvalidArgumentException(
                'JSON report path is required. Usage: eleload report <report.json> --html=<output.html>'
            );
        }

        if ($htmlPath === null) {
            throw new InvalidArgumentException(
                'Output HTML path is required. Usage: eleload report <report.json> --html=<output.html>'
            );
        }

        return new ReportOptions(
            jsonPath: $jsonPath,
            htmlPath: $htmlPath
        );
    }

    /**
     * @param list<string> $args
     */
    public function parseCompare(array $args): CompareOptions
    {
        $beforeJsonPath = null;
        $afterJsonPath = null;
        $htmlPath = null;
        $markdownPath = null;

        $i = 0;
        while ($i < count($args)) {
            $token = $args[$i];

            if ($this->isOption($token)) {
                [$name, $value, $i] = $this->parseOptionToken($args, $i);

                if ($name === 'html') {
                    $htmlPath = $value;
                    continue;
                }

                if ($name === 'md') {
                    $markdownPath = $value;
                    continue;
                }

                throw new InvalidArgumentException("Unknown option for compare command: --{$name}");
            }

            if ($beforeJsonPath === null) {
                $beforeJsonPath = $token;
                $i++;
                continue;
            }

            if ($afterJsonPath === null) {
                $afterJsonPath = $token;
                $i++;
                continue;
            }

            throw new InvalidArgumentException("Unexpected argument for compare command: {$token}");
        }

        if ($beforeJsonPath === null || $afterJsonPath === null) {
            throw new InvalidArgumentException(
                'Two JSON report paths are required. Usage: eleload compare <before.json> <after.json> [--html=<output.html>] [--md=<output.md>]'
            );
        }

        if ($htmlPath === null && $markdownPath === null) {
            throw new InvalidArgumentException(
                'At least one output path is required. Usage: eleload compare <before.json> <after.json> --html=<output.html> [--md=<output.md>]'
            );
        }

        return new CompareOptions(
            beforeJsonPath: $beforeJsonPath,
            afterJsonPath: $afterJsonPath,
            htmlPath: $htmlPath,
            markdownPath: $markdownPath
        );
    }

    /**
     * @param list<string> $args
     */
    public function parseScenario(array $args): ScenarioOptions
    {
        $scenarioPath = null;
        $concurrency = self::DEFAULT_CONCURRENCY;
        $durationSec = null;
        $iterations = self::DEFAULT_REQUESTS;
        $warmupSec = 0.0;
        $silent = false;
        $verbose = false;
        $debug = false;
        $yes = false;
        $allowHighLoad = false;
        $reportJsonPath = null;
        $outputDir = null;
        $name = null;

        $i = 0;
        while ($i < count($args)) {
            $token = $args[$i];

            if ($token === '--silent') {
                $silent = true;
                $i++;
                continue;
            }

            if ($token === '--verbose') {
                $verbose = true;
                $i++;
                continue;
            }

            if ($token === '--debug') {
                $debug = true;
                $i++;
                continue;
            }

            if ($token === '--yes') {
                $yes = true;
                $i++;
                continue;
            }

            if ($token === '--allow-high-load') {
                $allowHighLoad = true;
                $i++;
                continue;
            }

            if ($this->isOption($token)) {
                [$optName, $value, $i] = $this->parseOptionToken($args, $i);

                switch ($optName) {
                    case 'concurrency':
                        $concurrency = $this->parsePositiveInt($optName, $value);
                        break;
                    case 'duration':
                        $durationSec = $this->parsePositiveFloat($optName, $value);
                        break;
                    case 'iterations':
                        $iterations = $this->parsePositiveInt($optName, $value);
                        break;
                    case 'warmup':
                        $warmupSec = $this->parseNonNegativeFloat($optName, $value);
                        break;
                    case 'report-json':
                        $reportJsonPath = $value;
                        break;
                    case 'output-dir':
                        $outputDir = $value;
                        break;
                    case 'name':
                        $name = $value;
                        break;
                    default:
                        throw new InvalidArgumentException("Unknown option for scenario command: --{$optName}");
                }
                continue;
            }

            if ($scenarioPath === null) {
                $scenarioPath = $token;
                $i++;
                continue;
            }

            throw new InvalidArgumentException("Unexpected argument for scenario command: {$token}");
        }

        if ($scenarioPath === null) {
            throw new InvalidArgumentException(
                'Scenario file path is required. Usage: eleload scenario <scenario.json> [options]'
            );
        }

        if ($durationSec !== null && $warmupSec >= $durationSec) {
            throw new InvalidArgumentException('Option --warmup must be lower than --duration.');
        }

        return new ScenarioOptions(
            scenarioPath: $scenarioPath,
            concurrency: $concurrency,
            durationSec: $durationSec,
            iterations: $iterations,
            warmupSec: $warmupSec,
            silent: $silent,
            verbose: $verbose,
            debug: $debug,
            yes: $yes,
            allowHighLoad: $allowHighLoad,
            reportJsonPath: $reportJsonPath,
            outputDir: $outputDir,
            name: $name
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

    private function parseNonNegativeFloat(string $name, string $value): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("Option --{$name} must be numeric.");
        }

        $parsed = (float)$value;
        if ($parsed < 0.0) {
            throw new InvalidArgumentException("Option --{$name} must be >= 0.");
        }

        return $parsed;
    }

    private function parsePercent(string $name, string $value): float
    {
        $parsed = $this->parseNonNegativeFloat($name, $value);
        if ($parsed > 100.0) {
            throw new InvalidArgumentException("Option --{$name} must be <= 100.");
        }

        return $parsed;
    }

    /**
     * @return list<int>
     */
    private function parseStatusCodeList(string $name, string $value): array
    {
        $rawCodes = explode(',', $value);
        $parsedCodes = [];

        foreach ($rawCodes as $rawCode) {
            $trimmed = trim($rawCode);
            if ($trimmed === '') {
                throw new InvalidArgumentException("Option --{$name} must not include empty status codes.");
            }

            if (!preg_match('/^\d+$/', $trimmed)) {
                throw new InvalidArgumentException("Option --{$name} must be comma-separated integers.");
            }

            $statusCode = (int)$trimmed;
            if ($statusCode < 100 || $statusCode > 599) {
                throw new InvalidArgumentException("Option --{$name} status code must be between 100 and 599.");
            }

            if (!in_array($statusCode, $parsedCodes, true)) {
                $parsedCodes[] = $statusCode;
            }
        }

        return $parsedCodes;
    }
}
