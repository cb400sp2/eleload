<?php

declare(strict_types=1);

error_reporting(E_ALL);

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';

if (is_file($autoload)) {
    require $autoload;
} else {
    require $root . '/src/bootstrap.php';
}

$tests = [];

function test(string $name, callable $fn): void
{
    global $tests;
    $tests[] = [$name, $fn];
}

function assertTrue(bool $condition, string $message = 'Assertion failed'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message = 'Values are not identical'): void
{
    if ($expected !== $actual) {
        $detail = sprintf(
            "%s\nExpected: %s\nActual:   %s",
            $message,
            var_export($expected, true),
            var_export($actual, true)
        );
        throw new RuntimeException($detail);
    }
}

function assertContains(string $needle, string $haystack, string $message = 'Expected substring not found'): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . "\nNeedle: {$needle}\nHaystack: {$haystack}");
    }
}

function assertThrows(callable $fn, string $exceptionClass, string $messageContains = ''): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if (!$e instanceof $exceptionClass) {
            throw new RuntimeException(
                sprintf('Unexpected exception class. Expected %s, got %s', $exceptionClass, $e::class)
            );
        }

        if ($messageContains !== '' && !str_contains($e->getMessage(), $messageContains)) {
            throw new RuntimeException(
                sprintf("Exception message does not contain '%s'. Actual: %s", $messageContains, $e->getMessage())
            );
        }

        return;
    }

    throw new RuntimeException(sprintf('Expected exception %s was not thrown', $exceptionClass));
}

foreach (glob(__DIR__ . '/*Test.php') as $testFile) {
    require $testFile;
}

$passed = 0;
$failed = 0;

foreach ($tests as [$name, $fn]) {
    try {
        $fn();
        $passed++;
        fwrite(STDOUT, "[PASS] {$name}" . PHP_EOL);
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, "[FAIL] {$name}: {$e->getMessage()}" . PHP_EOL);
    }
}

fwrite(STDOUT, PHP_EOL . sprintf('Tests: %d passed, %d failed', $passed, $failed) . PHP_EOL);
exit($failed === 0 ? 0 : 1);
