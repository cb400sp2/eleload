<?php

declare(strict_types=1);

use Eleload\Logging\JsonLinesLogger;
use Eleload\Logging\NullLogger;
use Eleload\Logging\LoggerInterface;
use Eleload\Cli\ArgvParser;

// ---- NullLogger ----
test('NullLogger implements LoggerInterface', function (): void {
    $logger = new NullLogger();
    assertTrue($logger instanceof LoggerInterface);
});

test('NullLogger accepts all log levels without error', function (): void {
    $logger = new NullLogger();
    $logger->debug('dbg', ['k' => 'v']);
    $logger->info('inf');
    $logger->warn('wrn');
    $logger->error('err');
    assertTrue(true); // no exceptions thrown
});

// ---- JsonLinesLogger: file output ----
test('JsonLinesLogger writes JSON lines to file', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'log') . '.jsonl';
    $logger = new JsonLinesLogger($tmp, 'debug');
    $logger->info('hello', ['user' => 'alice']);
    $logger->error('boom');
    unset($logger); // trigger __destruct / fclose

    $lines = array_filter(explode("\n", file_get_contents($tmp) ?: ''), fn ($l) => $l !== '');
    unlink($tmp);

    assertSame(2, count($lines));
    $first = json_decode($lines[0], true);
    assertSame('info', $first['level']);
    assertSame('hello', $first['msg']);
    assertSame('alice', $first['ctx']['user']);
    assertNotNull($first['ts']);

    $second = json_decode(array_values($lines)[1], true);
    assertSame('error', $second['level']);
    assertSame('boom', $second['msg']);
    assertFalse(array_key_exists('ctx', $second));
});

test('JsonLinesLogger respects minimum level filter', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'log') . '.jsonl';
    $logger = new JsonLinesLogger($tmp, 'warn');
    $logger->debug('should be filtered');
    $logger->info('also filtered');
    $logger->warn('visible');
    $logger->error('also visible');
    unset($logger);

    $lines = array_filter(explode("\n", file_get_contents($tmp) ?: ''), fn ($l) => $l !== '');
    unlink($tmp);

    assertSame(2, count($lines));
    $first = json_decode(array_values($lines)[0], true);
    assertSame('warn', $first['level']);
});

test('JsonLinesLogger writes to stderr stream', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'log') . '.jsonl';
    $h = fopen($tmp, 'wb');
    $logger = new JsonLinesLogger($h, 'info');
    $logger->info('stream test');
    // stream is not owned, do not close via destructor
    fclose($h);

    $lines = array_filter(explode("\n", file_get_contents($tmp) ?: ''), fn ($l) => $l !== '');
    unlink($tmp);

    assertSame(1, count($lines));
    $entry = json_decode(array_values($lines)[0], true);
    assertSame('info', $entry['level']);
});

// ---- JsonLinesLogger: validation ----
test('JsonLinesLogger throws for invalid level', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'log') . '.jsonl';
    assertThrows(
        fn () => new JsonLinesLogger($tmp, 'trace'),
        InvalidArgumentException::class,
        'trace'
    );
    @unlink($tmp);
});

test('JsonLinesLogger throws for invalid destination type', function (): void {
    assertThrows(
        fn () => new JsonLinesLogger(12345, 'info'),
        InvalidArgumentException::class,
        'Destination'
    );
});

test('JsonLinesLogger ts format is ISO8601 UTC', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'log') . '.jsonl';
    $logger = new JsonLinesLogger($tmp, 'debug');
    $logger->debug('ts test');
    unset($logger);
    $lines = array_filter(explode("\n", file_get_contents($tmp) ?: ''), fn ($l) => $l !== '');
    unlink($tmp);
    $entry = json_decode(array_values($lines)[0], true);
    // should match YYYY-MM-DDTHH:MM:SS.xxxxxxZ
    assertTrue(
        (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z$/', $entry['ts']),
        'ts format invalid: ' . $entry['ts']
    );
});

// ---- ArgvParser: --log-level, --log-file ----
test('ArgvParser parses --log-level', function (): void {
    $parser = new ArgvParser();
    $opts = $parser->parseRun(['https://example.com/', '--log-level=debug']);
    assertSame('debug', $opts->logLevel);
});

test('ArgvParser parses --log-file', function (): void {
    $parser = new ArgvParser();
    $opts = $parser->parseRun(['https://example.com/', '--log-file=/tmp/app.log']);
    assertSame('/tmp/app.log', $opts->logFile);
});

test('ArgvParser logLevel defaults to warn', function (): void {
    $parser = new ArgvParser();
    $opts = $parser->parseRun(['https://example.com/']);
    assertSame('warn', $opts->logLevel);
    assertSame(null, $opts->logFile);
});

test('ArgvParser throws for unknown --log-level value', function (): void {
    $parser = new ArgvParser();
    assertThrows(
        fn () => $parser->parseRun(['https://example.com/', '--log-level=trace']),
        InvalidArgumentException::class,
        'log-level'
    );
});
