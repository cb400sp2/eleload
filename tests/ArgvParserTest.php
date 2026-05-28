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
    assertSame([], $options->headers);
    assertSame(null, $options->body);
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
        '--header',
        'Accept: application/json',
        '--header=Content-Type: application/json',
        '--body',
        '{"name":"eleload"}',
        '--report-json',
        'reports/report.json',
        '--report-html=reports/report.html',
        '--target-rps',
        '120.5',
        '--target-tps=110.25',
    ]);

    assertSame('https://example.com/api/items', $options->url);
    assertSame(250, $options->requests);
    assertSame(25, $options->concurrency);
    assertSame('POST', $options->method);
    assertSame(3, $options->timeout);
    assertSame(
        ['Accept: application/json', 'Content-Type: application/json'],
        $options->headers
    );
    assertSame('{"name":"eleload"}', $options->body);
    assertSame('reports/report.json', $options->reportJsonPath);
    assertSame('reports/report.html', $options->reportHtmlPath);
    assertSame(120.5, $options->targetRps);
    assertSame(110.25, $options->targetTps);
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
