<?php

declare(strict_types=1);

use Eleload\Compare\ReportComparator;

test('ReportComparator calculates improved and regressed metrics', function (): void {
    $before = [
        'target' => ['url' => 'https://example.com', 'method' => 'GET'],
        'summary' => [
            'throughput' => ['rps' => 100.0, 'tps' => 90.0],
            'latency' => ['p95' => 220.0, 'p99' => 350.0],
            'requests' => ['error_rate' => 2.0],
        ],
        'meta' => ['test_name' => 'before-run'],
    ];

    $after = [
        'target' => ['url' => 'https://example.com', 'method' => 'GET'],
        'summary' => [
            'throughput' => ['rps' => 110.0, 'tps' => 80.0],
            'latency' => ['p95' => 200.0, 'p99' => 360.0],
            'requests' => ['error_rate' => 1.0],
        ],
        'meta' => ['test_name' => 'after-run'],
    ];

    $comparison = (new ReportComparator())->compare($before, $after);
    $statuses = array_map(
        static fn (array $metric): string => (string)$metric['status'],
        $comparison['metrics']
    );

    assertSame(['improved', 'regressed', 'improved', 'regressed', 'improved'], $statuses);
    assertSame(3, $comparison['summary']['improved']);
    assertSame(2, $comparison['summary']['regressed']);
    assertSame(0, $comparison['summary']['unchanged']);
});

test('ReportComparator validates expected metric paths', function (): void {
    $before = [
        'target' => ['url' => 'https://example.com', 'method' => 'GET'],
        'summary' => ['throughput' => ['rps' => 100.0, 'tps' => 90.0]],
        'meta' => [],
    ];
    $after = [
        'target' => ['url' => 'https://example.com', 'method' => 'GET'],
        'summary' => ['throughput' => ['rps' => 110.0, 'tps' => 95.0]],
        'meta' => [],
    ];

    assertThrows(
        fn () => (new ReportComparator())->compare($before, $after),
        RuntimeException::class,
        'missing'
    );
});

