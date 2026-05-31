<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/run.php';

use Eleload\Cli\TuiDashboard;

// -----------------------------------------------------------------------
// TuiDashboard tests
// (TTY is not available in CI, so we only test non-TTY / logic behavior)
// -----------------------------------------------------------------------

test('TuiDashboard start/finish produce no output when not a TTY', function (): void {
    $dashboard = new TuiDashboard();

    ob_start();
    $dashboard->start();
    $dashboard->finish();
    $output = ob_get_clean();

    // In a non-TTY environment (CI) there should be no ANSI output
    assertSame('', $output);
});

test('TuiDashboard update produces no output when not a TTY', function (): void {
    $dashboard = new TuiDashboard();
    $dashboard->start();

    ob_start();
    $dashboard->update(50, 100, 10.5, 2.0, 5.0);
    $output = ob_get_clean();

    assertSame('', $output);
    $dashboard->finish();
});

test('TuiDashboard multiple update calls accumulate history silently when not a TTY', function (): void {
    $dashboard = new TuiDashboard();
    $dashboard->start();

    ob_start();
    for ($i = 0; $i < 25; $i++) {
        $dashboard->update($i * 4, 100, (float) $i, 0.0, (float) $i);
    }
    $output = ob_get_clean();

    assertSame('', $output);
    $dashboard->finish();
});
