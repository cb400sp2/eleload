<?php

declare(strict_types=1);

use Eleload\Cli\ArgvParser;

test('ArgvParser parses minimum arguments', function (): void {
    $parser = new ArgvParser();
    $options = $parser->parseRun(['https://example.com']);

    assertSame('https://example.com', $options->url);
    assertSame(100, $options->requests);
    assertSame(10, $options->concurrency);
    assertSame('GET', $options->method);
    assertSame(10, $options->timeout);
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
        '--target-tps=110.25',
    ]);

    assertSame('https://example.com/api/items', $options->url);
    assertSame(250, $options->requests);
    assertSame(25, $options->concurrency);
    assertSame('POST', $options->method);
    assertSame(3, $options->timeout);
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
    assertSame(120.5, $options->targetRps);
    assertSame(110.25, $options->targetTps);
});

test('ArgvParser rejects warmup greater than duration', function (): void {
    $parser = new ArgvParser();

    assertThrows(
        fn () => $parser->parseRun(['https://example.com', '--duration=5', '--warmup=5']),
        InvalidArgumentException::class,
        'Option --warmup must be lower than --duration'
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
