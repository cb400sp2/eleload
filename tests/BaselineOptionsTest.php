<?php

declare(strict_types=1);

use Eleload\Cli\ArgvParser;

// ---- ArgvParser: --baseline ----
test('ArgvParser parses --baseline option', function (): void {
    $parser = new ArgvParser();
    $opts = $parser->parseRun(['https://example.com/', '--requests=1', '--baseline=/tmp/b.json']);
    assertSame('/tmp/b.json', $opts->baselinePath);
    assertSame(null, $opts->saveBaselinePath);
});

test('ArgvParser parses --save-baseline option', function (): void {
    $parser = new ArgvParser();
    $opts = $parser->parseRun(['https://example.com/', '--requests=1', '--save-baseline=/tmp/new.json']);
    assertSame('/tmp/new.json', $opts->saveBaselinePath);
    assertSame(null, $opts->baselinePath);
});

test('ArgvParser: both --baseline and --save-baseline can coexist', function (): void {
    $parser = new ArgvParser();
    $opts = $parser->parseRun([
        'https://example.com/',
        '--requests=1',
        '--baseline=/tmp/b.json',
        '--save-baseline=/tmp/new.json',
    ]);
    assertSame('/tmp/b.json', $opts->baselinePath);
    assertSame('/tmp/new.json', $opts->saveBaselinePath);
});

test('ArgvParser: baseline and save-baseline default to null', function (): void {
    $parser = new ArgvParser();
    $opts = $parser->parseRun(['https://example.com/', '--requests=1']);
    assertSame(null, $opts->baselinePath);
    assertSame(null, $opts->saveBaselinePath);
});

// ---- RunOptions constructor defaults ----
test('RunOptions baselinePath defaults to null', function (): void {
    $opts = new \Eleload\Cli\RunOptions(
        url: 'https://example.com/',
        requests: 1,
        concurrency: 1,
        method: 'GET',
        timeout: 10,
        connectTimeout: null,
        silent: false,
        verbose: false,
        debug: false,
        yes: false,
        allowHighLoad: false,
        followRedirects: false,
        headers: [],
        bearerToken: null,
        basicUser: null,
        basicPassword: null,
        cookie: null,
        body: null,
        reportJsonPath: null,
        reportHtmlPath: null,
        reportMdPath: null,
        reportCsvPath: null,
        reportJunitPath: null,
        outputDir: null,
        name: null,
        successStatusCodes: null,
        expectStatusCodes: null,
        expectBodyContains: null,
        durationSec: null,
        warmupSec: 0.0,
        failOnP95: null,
        failOnP99: null,
        failOnErrorRate: null,
        failOnRpsBelow: null,
        failOnTpsBelow: null,
        rate: null,
        targetRps: null,
        targetTps: null,
        rampUpSec: 0.0,
    );
    assertSame(null, $opts->baselinePath);
    assertSame(null, $opts->saveBaselinePath);
});
