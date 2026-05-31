<?php

declare(strict_types=1);

use Eleload\Cli\ArgvParser;
use Eleload\Cli\RunOptions;

test('ArgvParser parses minimum arguments', function (): void {
    $parser = new ArgvParser();
    $options = $parser->parseRun(['https://example.com']);

    /** @var RunOptions $options */

    assertSame('https://example.com', $options->url);
    assertSame(100, $options->requests);
    assertSame(10, $options->concurrency);
    assertSame('GET', $options->method);
    assertSame(10, $options->timeout);
    assertSame(null, $options->connectTimeout);
    assertSame(false, $options->silent);
    assertSame(false, $options->verbose);
    assertSame(false, $options->debug);
    assertSame(false, $options->yes);
    assertSame(false, $options->allowHighLoad);
    assertSame(false, $options->followRedirects);
    assertSame([], $options->headers);
    assertSame(null, $options->bearerToken);
    assertSame(null, $options->basicUser);
    assertSame(null, $options->basicPassword);
    assertSame(null, $options->cookie);
    assertSame(null, $options->body);
    assertSame(null, $options->successStatusCodes);
    assertSame(null, $options->expectStatusCodes);
    assertSame(null, $options->expectBodyContains);
    assertSame(null, $options->reportCsvPath);
    assertSame(null, $options->rate);
});

test('ArgvParser parses full option set', function (): void {
    $parser = new ArgvParser();
    $options = $parser->parseRun([
        'https://example.com/api/items',
        '--requests=250',
        '--concurrency',
        '25',
        '--method',
        'post',
        '--timeout=3',
        '--connect-timeout=2',
        '--silent',
        '--verbose',
        '--debug',
        '--yes',
        '--allow-high-load',
        '--follow-redirects',
        '--header',
        'Accept: application/json',
        '--header=Content-Type: application/json',
        '--bearer-token',
        'token-123',
        '--basic-user=user1',
        '--basic-password',
        'pass1',
        '--cookie=session=abc123',
        '--body',
        '{"name":"eleload"}',
        '--report-json',
        'reports/report.json',
        '--report-html=reports/report.html',
        '--report-md=reports/report.md',
        '--report-csv=reports/report.csv',
        '--output-dir=reports',
        '--name=top page smoke load',
        '--success-status=200,201,204',
        '--expect-status=200,201',
        '--expect-body-contains=Welcome',
        '--duration=60',
        '--warmup=5',
        '--fail-on-p95=500',
        '--fail-on-p99=1000',
        '--fail-on-error-rate=1',
        '--fail-on-rps-below=100',
        '--fail-on-tps-below=90',
        '--target-rps',
        '120.5',
        '--rate=125.75',
        '--target-tps=110.25',
        '--ramp-up=30',
    ]);

    /** @var RunOptions $options */

    assertSame('https://example.com/api/items', $options->url);
    assertSame(250, $options->requests);
    assertSame(25, $options->concurrency);
    assertSame('POST', $options->method);
    assertSame(3, $options->timeout);
    assertSame(2, $options->connectTimeout);
    assertSame(true, $options->silent);
    assertSame(true, $options->verbose);
    assertSame(true, $options->debug);
    assertSame(true, $options->yes);
    assertSame(true, $options->allowHighLoad);
    assertSame(true, $options->followRedirects);
    assertSame(
        ['Accept: application/json', 'Content-Type: application/json'],
        $options->headers
    );
    assertSame('token-123', $options->bearerToken);
    assertSame('user1', $options->basicUser);
    assertSame('pass1', $options->basicPassword);
    assertSame('session=abc123', $options->cookie);
    assertSame('{"name":"eleload"}', $options->body);
    assertSame('reports/report.json', $options->reportJsonPath);
    assertSame('reports/report.html', $options->reportHtmlPath);
    assertSame('reports/report.md', $options->reportMdPath);
    assertSame('reports/report.csv', $options->reportCsvPath);
    assertSame('reports', $options->outputDir);
    assertSame('top page smoke load', $options->name);
    assertSame([200, 201, 204], $options->successStatusCodes);
    assertSame([200, 201], $options->expectStatusCodes);
    assertSame('Welcome', $options->expectBodyContains);
    assertSame(60.0, $options->durationSec);
    assertSame(5.0, $options->warmupSec);
    assertSame(500.0, $options->failOnP95);
    assertSame(1000.0, $options->failOnP99);
    assertSame(1.0, $options->failOnErrorRate);
    assertSame(100.0, $options->failOnRpsBelow);
    assertSame(90.0, $options->failOnTpsBelow);
    assertSame(125.75, $options->rate);
    assertSame(125.75, $options->targetRps);
    assertSame(110.25, $options->targetTps);
    assertSame(30.0, $options->rampUpSec);
});

test('ArgvParser rejects warmup greater than duration', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', '--duration=5', '--warmup=5']),
        InvalidArgumentException::class,
        'Option --warmup must be lower than --duration'
    );
});

test('ArgvParser rejects ramp-up greater than or equal to duration', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', '--duration=5', '--ramp-up=5']),
        InvalidArgumentException::class,
        'Option --ramp-up must be lower than --duration'
    );
});

test('ArgvParser rejects rate without duration', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', '--rate=100']),
        InvalidArgumentException::class,
        'Option --rate requires --duration'
    );
});

test('ArgvParser applies last redirect control flag', function (): void {
    $parser = new ArgvParser();

    $options1 = $parser->parseRun(['https://example.com', '--follow-redirects', '--no-follow-redirects']);
    assertSame(false, $options1->followRedirects);

    $options2 = $parser->parseRun(['https://example.com', '--no-follow-redirects', '--follow-redirects']);
    assertSame(true, $options2->followRedirects);
});

test('ArgvParser rejects invalid success status list', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', '--success-status=200,abc']),
        InvalidArgumentException::class,
        'comma-separated integers'
    );

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', '--success-status=99']),
        InvalidArgumentException::class,
        'between 100 and 599'
    );
});

test('ArgvParser rejects invalid connect timeout', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', '--connect-timeout=0']),
        InvalidArgumentException::class,
        'Option --connect-timeout must be >= 1'
    );
});

test('ArgvParser rejects invalid expect status list', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', '--expect-status=200,abc']),
        InvalidArgumentException::class,
        'comma-separated integers'
    );

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', '--expect-status=700']),
        InvalidArgumentException::class,
        'between 100 and 599'
    );
});

test('ArgvParser rejects incomplete basic auth options', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', '--basic-user=user']),
        InvalidArgumentException::class,
        'must be provided together'
    );

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', '--basic-password=pass']),
        InvalidArgumentException::class,
        'must be provided together'
    );
});

test('ArgvParser rejects invalid URL', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['not-a-url']),
        InvalidArgumentException::class,
        'Invalid URL'
    );
});

test('ArgvParser rejects unknown option', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', '--unknown=1']),
        InvalidArgumentException::class,
        'Unknown option'
    );
});

test('ArgvParser rejects missing URL', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['--requests=10']),
        InvalidArgumentException::class,
        'URL is required'
    );
});

test('ArgvParser parses report command options', function (): void {
    $parser = new ArgvParser();
    $options = $parser->parseReport(['reports/input.json', '--html=reports/output.html']);

    assertSame('reports/input.json', $options->jsonPath);
    assertSame('reports/output.html', $options->htmlPath);
});

test('ArgvParser report command requires html option', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseReport(['reports/input.json']),
        InvalidArgumentException::class,
        'Output HTML path is required'
    );
});

test('ArgvParser report command rejects unknown option', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseReport(['reports/input.json', '--output=reports/out.html']),
        InvalidArgumentException::class,
        'Unknown option for report command'
    );
});

test('ArgvParser parses compare command options', function (): void {
    $parser = new ArgvParser();
    $options = $parser->parseCompare([
        'reports/before.json',
        'reports/after.json',
        '--html=reports/compare.html',
        '--md=reports/compare.md',
    ]);

    assertSame('reports/before.json', $options->beforeJsonPath);
    assertSame('reports/after.json', $options->afterJsonPath);
    assertSame('reports/compare.html', $options->htmlPath);
    assertSame('reports/compare.md', $options->markdownPath);
});

test('ArgvParser compare command requires at least one output', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseCompare(['reports/before.json', 'reports/after.json']),
        InvalidArgumentException::class,
        'At least one output path is required'
    );
});

test('ArgvParser compare command requires two json paths', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseCompare(['reports/before.json', '--html=reports/compare.html']),
        InvalidArgumentException::class,
        'Two JSON report paths are required'
    );
});

test('ArgvParser compare command rejects unknown option', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseCompare(['reports/before.json', 'reports/after.json', '--output=report.html']),
        InvalidArgumentException::class,
        'Unknown option for compare command'
    );
});

test('ArgvParser parses --ramp-up option', function (): void {
    $parser = new ArgvParser();

    $options = $parser->parseRun(['https://example.com', '--ramp-up=10']);
    assertSame(10.0, $options->rampUpSec);

    $options2 = $parser->parseRun(['https://example.com', '--ramp-up=0']);
    assertSame(0.0, $options2->rampUpSec);

    $options3 = $parser->parseRun(['https://example.com']);
    assertSame(0.0, $options3->rampUpSec);
});

test('ArgvParser rejects negative --ramp-up', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', '--ramp-up=-1']),
        InvalidArgumentException::class,
        'ramp-up'
    );
});

// -----------------------------------------------------------------------
// parseScenario
// -----------------------------------------------------------------------

test('ArgvParser parseScenario parses minimum arguments', function (): void {
    $parser = new ArgvParser();
    $options = $parser->parseScenario(['scenario.json']);

    assertSame('scenario.json', $options->scenarioPath);
    assertSame(10, $options->concurrency);
    assertSame(null, $options->durationSec);
    assertSame(100, $options->iterations);
    assertSame(0.0, $options->warmupSec);
    assertSame(false, $options->silent);
    assertSame(false, $options->verbose);
    assertSame(false, $options->debug);
    assertSame(false, $options->yes);
    assertSame(false, $options->allowHighLoad);
    assertSame(null, $options->reportJsonPath);
    assertSame(null, $options->reportHtmlPath);
    assertSame(null, $options->outputDir);
    assertSame(null, $options->name);
    assertSame(1, $options->agents);
});

test('ArgvParser parseScenario parses all options', function (): void {
    $parser = new ArgvParser();
    $options = $parser->parseScenario([
        'my-scenario.json',
        '--concurrency=20',
        '--duration=60',
        '--warmup=5',
        '--silent',
        '--verbose',
        '--debug',
        '--yes',
        '--allow-high-load',
        '--agents=3',
        '--report-json=out/report.json',
        '--report-html=out/report.html',
        '--output-dir=out',
        '--name=My Perf Test',
    ]);

    assertSame('my-scenario.json', $options->scenarioPath);
    assertSame(20, $options->concurrency);
    assertSame(60.0, $options->durationSec);
    assertSame(5.0, $options->warmupSec);
    assertSame(true, $options->silent);
    assertSame(true, $options->verbose);
    assertSame(true, $options->debug);
    assertSame(true, $options->yes);
    assertSame(true, $options->allowHighLoad);
    assertSame(3, $options->agents);
    assertSame('out/report.json', $options->reportJsonPath);
    assertSame('out/report.html', $options->reportHtmlPath);
    assertSame('out', $options->outputDir);
    assertSame('My Perf Test', $options->name);
});

test('ArgvParser parseScenario parses --iterations option', function (): void {
    $parser = new ArgvParser();
    $options = $parser->parseScenario(['scenario.json', '--iterations=250']);

    assertSame(250, $options->iterations);
});

test('ArgvParser parseScenario boolean flags work without value', function (): void {
    $parser = new ArgvParser();
    $options = $parser->parseScenario(['scenario.json', '--silent', '--yes']);

    assertSame(true, $options->silent);
    assertSame(true, $options->yes);
    assertSame(false, $options->verbose);
});

test('ArgvParser parseScenario rejects warmup >= duration', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseScenario(['scenario.json', '--duration=10', '--warmup=10']),
        InvalidArgumentException::class,
        '--warmup must be lower than --duration'
    );
});

test('ArgvParser parseScenario requires scenario path', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseScenario([]),
        InvalidArgumentException::class,
        'Scenario file path is required'
    );
});

test('ArgvParser parseScenario rejects unknown option', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseScenario(['scenario.json', '--unknown=1']),
        InvalidArgumentException::class,
        'Unknown option for scenario'
    );
});

test('ArgvParser parseScenario rejects unexpected positional argument', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseScenario(['scenario.json', 'extra-arg']),
        InvalidArgumentException::class,
        'Unexpected argument'
    );
});

// ---------------------------------------------------------------------------
// Security: URL scheme validation
// ---------------------------------------------------------------------------

test('ArgvParser rejects non-http/https URL scheme', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['ftp://example.com']),
        InvalidArgumentException::class,
        'http or https'
    );
});

test('ArgvParser accepts http scheme', function (): void {
    $parser = new ArgvParser();
    $options = $parser->parseRun(['http://example.com']);
    assertSame('http://example.com', $options->url);
});

test('ArgvParser accepts https scheme', function (): void {
    $parser = new ArgvParser();
    $options = $parser->parseRun(['https://example.com']);
    assertSame('https://example.com', $options->url);
});

// ---------------------------------------------------------------------------
// Security: CRLF injection prevention in headers
// ---------------------------------------------------------------------------

test('ArgvParser rejects header with CR character', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', '--header=X-Evil: bad' . "\r" . 'value']),
        InvalidArgumentException::class,
        'CR'
    );
});

test('ArgvParser rejects header with LF character', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', '--header=X-Evil: bad' . "\n" . 'value']),
        InvalidArgumentException::class,
        'LF'
    );
});

// ---------------------------------------------------------------------------
// Security: --block-private-networks
// ---------------------------------------------------------------------------

test('ArgvParser rejects localhost with --block-private-networks', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['http://localhost/api', '--block-private-networks']),
        InvalidArgumentException::class,
        'block-private-networks'
    );
});

test('ArgvParser rejects private IP with --block-private-networks', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['http://192.168.1.1/api', '--block-private-networks']),
        InvalidArgumentException::class,
        'block-private-networks'
    );
});

test('ArgvParser allows public IP with --block-private-networks', function (): void {
    $parser = new ArgvParser();
    $options = $parser->parseRun(['http://1.1.1.1/api', '--block-private-networks']);
    assertSame('http://1.1.1.1/api', $options->url);
    assertSame(true, $options->blockPrivateNetworks);
});

// ---------------------------------------------------------------------------
// Security: credential env vars
// ---------------------------------------------------------------------------

test('ArgvParser resolves --bearer-token-env from environment', function (): void {
    $envVar = 'ELELOAD_TEST_TOKEN_' . getmypid();
    putenv($envVar . '=secret-token');

    $parser = new ArgvParser();
    $options = $parser->parseRun(['https://example.com', "--bearer-token-env={$envVar}"]);
    assertSame('secret-token', $options->bearerToken);

    putenv($envVar);
});

test('ArgvParser rejects --bearer-token-env for unset variable', function (): void {
    $envVar = 'ELELOAD_TEST_UNSET_' . getmypid();
    putenv($envVar); // ensure unset

    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', "--bearer-token-env={$envVar}"]),
        InvalidArgumentException::class,
        'not set or empty'
    );
});

test('ArgvParser rejects combining --bearer-token and --bearer-token-env', function (): void {
    $envVar = 'ELELOAD_TEST_BOTH_' . getmypid();
    putenv($envVar . '=val');

    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', '--bearer-token=tok', "--bearer-token-env={$envVar}"]),
        InvalidArgumentException::class,
        'together'
    );

    putenv($envVar);
});

test('ArgvParser parses --tui flag', function (): void {
    $parser = new ArgvParser();
    $opts = $parser->parseRun(['https://example.com', '--requests=10', '--tui']);
    assertTrue($opts->tui);
});

test('ArgvParser tui defaults to false', function (): void {
    $parser = new ArgvParser();
    $opts = $parser->parseRun(['https://example.com', '--requests=10']);
    assertFalse($opts->tui);
});

test('ArgvParser parses --prometheus-pushgateway-url', function (): void {
    $parser = new ArgvParser();
    $opts = $parser->parseRun(['https://example.com', '--requests=10', '--prometheus-pushgateway-url=http://localhost:9091']);
    assertSame('http://localhost:9091', $opts->prometheusUrl);
});
