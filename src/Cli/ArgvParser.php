<?php

declare(strict_types=1);

namespace Eleload\Cli;

use InvalidArgumentException;

/**
 * Parses raw argv tokens into structured option objects for each sub-command.
 */
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
        $rate = null;
        $targetRps = null;
        $targetTps = null;
        $rampUpSec = 0.0;
        $memoryBufferSize = 10_000;
        $blockPrivateNetworks = false;
        $httpVersion = '2.0';
        $dnsCacheTtl = -1;
        $acceptEncoding = 'gzip';
        $noDecompress = false;
        $maxConnections = 0;
        $tcpKeepaliveSec = 60;
        $grpcMethod = null;
        $otelEndpoint = null;
        $bearerTokenEnv = null;
        $basicUserEnv = null;
        $basicPasswordEnv = null;
        $cookieEnv = null;
        $baselinePath = null;
        $saveBaselinePath = null;
        $logLevel = 'warn';
        $logFile = null;

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

            if ($token === '--block-private-networks') {
                $blockPrivateNetworks = true;
                $i++;
                continue;
            }

            if ($token === '--no-decompress') {
                $noDecompress = true;
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
                        if (str_contains($value, "\r") || str_contains($value, "\n")) {
                            throw new InvalidArgumentException('Option --header value must not contain CR (\\r) or LF (\\n) characters.');
                        }
                        $headers[] = $value;
                        break;
                    case 'bearer-token':
                        $bearerToken = $value;
                        break;
                    case 'bearer-token-env':
                        $bearerTokenEnv = $value;
                        break;
                    case 'basic-user':
                        $basicUser = $value;
                        break;
                    case 'basic-user-env':
                        $basicUserEnv = $value;
                        break;
                    case 'basic-password':
                        $basicPassword = $value;
                        break;
                    case 'basic-password-env':
                        $basicPasswordEnv = $value;
                        break;
                    case 'cookie':
                        $cookie = $value;
                        break;
                    case 'cookie-env':
                        $cookieEnv = $value;
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
                    case 'rate':
                        $rate = $this->parsePositiveFloat($name, $value);
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
                    case 'memory-buffer-size':
                        $memoryBufferSize = $this->parsePositiveInt($name, $value);
                        break;
                    case 'http-version':
                        $httpVersion = $this->parseHttpVersion($name, $value);
                        break;
                    case 'dns-cache-ttl':
                        $dnsCacheTtl = $this->parseNonNegativeInt($name, $value);
                        break;
                    case 'accept-encoding':
                        $acceptEncoding = $this->parseAcceptEncoding($name, $value);
                        break;
                    case 'max-connections':
                        $maxConnections = $this->parseNonNegativeInt($name, $value);
                        break;
                    case 'tcp-keepalive':
                        $tcpKeepaliveSec = $this->parseNonNegativeInt($name, $value);
                        break;
                    case 'baseline':
                        $baselinePath = $value;
                        break;
                    case 'save-baseline':
                        $saveBaselinePath = $value;
                        break;
                    case 'grpc':
                        $this->validateGrpcMethod($value);
                        $grpcMethod = $value;
                        break;
                    case 'log-level':
                        $this->validateLogLevel($value);
                        $logLevel = $value;
                        break;
                    case 'log-file':
                        $logFile = $value;
                        break;
                    case 'otel-endpoint':
                        $otelEndpoint = $value;
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

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new InvalidArgumentException("URL must use http or https scheme: {$url}");
        }

        if ($blockPrivateNetworks) {
            $this->validateNoPrivateHost($url);
        }

        // Resolve credential env vars
        if ($bearerTokenEnv !== null) {
            $resolved = getenv($bearerTokenEnv);
            if ($resolved === false || $resolved === '') {
                throw new InvalidArgumentException("Environment variable '{$bearerTokenEnv}' is not set or empty (--bearer-token-env).");
            }
            if ($bearerToken !== null) {
                throw new InvalidArgumentException('Cannot use --bearer-token and --bearer-token-env together.');
            }
            $bearerToken = $resolved;
        }

        if ($basicUserEnv !== null) {
            $resolved = getenv($basicUserEnv);
            if ($resolved === false) {
                throw new InvalidArgumentException("Environment variable '{$basicUserEnv}' is not set (--basic-user-env).");
            }
            if ($basicUser !== null) {
                throw new InvalidArgumentException('Cannot use --basic-user and --basic-user-env together.');
            }
            $basicUser = $resolved;
        }

        if ($basicPasswordEnv !== null) {
            $resolved = getenv($basicPasswordEnv);
            if ($resolved === false) {
                throw new InvalidArgumentException("Environment variable '{$basicPasswordEnv}' is not set (--basic-password-env).");
            }
            if ($basicPassword !== null) {
                throw new InvalidArgumentException('Cannot use --basic-password and --basic-password-env together.');
            }
            $basicPassword = $resolved;
        }

        if ($cookieEnv !== null) {
            $resolved = getenv($cookieEnv);
            if ($resolved === false || $resolved === '') {
                throw new InvalidArgumentException("Environment variable '{$cookieEnv}' is not set or empty (--cookie-env).");
            }
            if ($cookie !== null) {
                throw new InvalidArgumentException('Cannot use --cookie and --cookie-env together.');
            }
            $cookie = $resolved;
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

        if ($durationSec !== null && $rampUpSec >= $durationSec) {
            throw new InvalidArgumentException('Option --ramp-up must be lower than --duration.');
        }

        if ($rate !== null && $durationSec === null) {
            throw new InvalidArgumentException('Option --rate requires --duration.');
        }

        if ($rate !== null) {
            $targetRps = $rate;
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
            rate: $rate,
            targetRps: $targetRps,
            targetTps: $targetTps,
            rampUpSec: $rampUpSec,
            memoryBufferSize: $memoryBufferSize,
            blockPrivateNetworks: $blockPrivateNetworks,
            httpVersion: $httpVersion,
            dnsCacheTtl: $dnsCacheTtl,
            acceptEncoding: $acceptEncoding,
            noDecompress: $noDecompress,
            maxConnections: $maxConnections,
            tcpKeepaliveSec: $tcpKeepaliveSec,
            baselinePath: $baselinePath,
            saveBaselinePath: $saveBaselinePath,
            logLevel: $logLevel,
            logFile: $logFile,
            grpcMethod: $grpcMethod,
            otelEndpoint: $otelEndpoint,
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
        $agents = 1;

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
                    case 'agents':
                        $agents = $this->parsePositiveInt($optName, $value);
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
            name: $name,
            agents: $agents,
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

    /**
 * Returns true when the token starts with `--`.
 */
    private function isOption(string $token): bool
    {
        return str_starts_with($token, '--');
    }

    /**
 * Parses the option value as a positive integer (>= 1).
 *
 * @throws \InvalidArgumentException
 */
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

    /**
 * Parses the option value as a positive float (> 0).
 *
 * @throws \InvalidArgumentException
 */
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

    /**
 * Parses the option value as a non-negative float (>= 0).
 *
 * @throws \InvalidArgumentException
 */
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

    /**
 * Parses the option value as a percentage in the range [0, 100].
 *
 * @throws \InvalidArgumentException
 */
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

    private function parseAcceptEncoding(string $name, string $value): string
    {
        $allowed = ['none', 'gzip', 'br', 'deflate'];
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException(
                "Option --{$name} must be one of: " . implode(', ', $allowed) . '.'
            );
        }

        return $value;
    }

    private function parseNonNegativeInt(string $name, string $value): int
    {
        if (!preg_match('/^\d+$/', $value)) {
            throw new InvalidArgumentException("Option --{$name} must be a non-negative integer.");
        }

        return (int) $value;
    }

    private function parseHttpVersion(string $name, string $value): string
    {
        $allowed = ['1.0', '1.1', '2.0', '3.0'];
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException(
                "Option --{$name} must be one of: " . implode(', ', $allowed) . '.'
            );
        }

        return $value;
    }

    private function validateLogLevel(string $value): void
    {
        $allowed = ['debug', 'info', 'warn', 'error'];
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException(
                'Option --log-level must be one of: ' . implode(', ', $allowed) . '.'
            );
        }
    }

    private function validateGrpcMethod(string $value): void
    {
        if (!preg_match('/^[A-Za-z0-9_.]+\/[A-Za-z0-9_]+$/', $value)) {
            throw new InvalidArgumentException('Option --grpc must use package.Service/Method syntax.');
        }
    }

    /**
     * Rejects URLs whose host resolves to a private or loopback address.
     * Only checks literal hostnames and IP addresses; does not perform DNS resolution.
     *
     * @throws \InvalidArgumentException
     */
    private function validateNoPrivateHost(string $url): void
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $host = trim($host, '[]'); // strip IPv6 brackets

        $blockedNames = ['localhost', 'ip6-localhost', 'ip6-loopback'];
        if (in_array(strtolower($host), $blockedNames, true)) {
            throw new InvalidArgumentException(
                "Host '{$host}' is not allowed with --block-private-networks."
            );
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $publicIp = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($publicIp === false) {
                throw new InvalidArgumentException(
                    "IP address '{$host}' is in a private or reserved range and is not allowed with --block-private-networks."
                );
            }
        }
    }
}
