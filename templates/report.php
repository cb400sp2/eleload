<?php

declare(strict_types=1);

/** @var array<string, mixed> $report */
$summary    = $report['summary'];
$requests   = $summary['requests'];
$throughput = $summary['throughput'];
$latency    = $summary['latency'];
$timeBuckets = $summary['time_buckets'] ?? [];
$testName   = $report['meta']['test_name'] ?? null;
$successStatusCodes = $report['config']['success_status'] ?? null;
$successStatusLabel = (is_array($successStatusCodes) && $successStatusCodes !== [])
    ? implode(',', array_map(static fn (mixed $code): string => (string) $code, $successStatusCodes))
    : '2xx,3xx (default)';

$esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$pct = static fn (float $value): string => number_format($value, 2) . '%';
$num = static fn (float $value): string => number_format($value, 2);

// Prepare Chart.js data from time_buckets
$chartLabels  = [];
$chartRps     = [];
$chartTps     = [];
$chartErr     = [];
$chartLatency = [];
$chartP50     = [];
$chartP75     = [];
$chartP95     = [];
$chartP99     = [];

// Latency dist bins: collect from first bucket with dist data
$distBinLabels = [];
/** @var array<int, list<int>> $distBinData */
$distBinData   = [];
foreach ($timeBuckets as $bucket) {
    $chartLabels[]  = (string) ((int) ($bucket['t'] ?? 0));
    $chartRps[]     = (float) ($bucket['rps'] ?? 0.0);
    $chartTps[]     = (float) ($bucket['tps'] ?? 0.0);
    $chartErr[]     = (float) ($bucket['error_rate'] ?? 0.0);
    $chartLatency[] = (float) ($bucket['avg_latency_ms'] ?? 0.0);
    $chartP50[]     = (float) ($bucket['p50'] ?? 0.0);
    $chartP75[]     = (float) ($bucket['p75'] ?? 0.0);
    $chartP95[]     = (float) ($bucket['p95'] ?? 0.0);
    $chartP99[]     = (float) ($bucket['p99'] ?? 0.0);

    $dist = is_array($bucket['latency_dist'] ?? null) ? $bucket['latency_dist'] : [];
    foreach ($dist as $i => $bin) {
        if ($distBinLabels === [] || !isset($distBinLabels[$i])) {
            $distBinLabels[$i] = (string) ($bin['label'] ?? '');
        }
        $distBinData[$i][] = (int) ($bin['count'] ?? 0);
    }
}
$hasTimeSeries = $chartLabels !== [];
$hasDistData   = $distBinLabels !== [];

$distBinColors = [
    'rgba(99,102,241,0.7)', 'rgba(59,130,246,0.7)', 'rgba(6,182,212,0.7)',
    'rgba(16,185,129,0.7)', 'rgba(245,158,11,0.7)', 'rgba(249,115,22,0.7)',
    'rgba(239,68,68,0.7)',  'rgba(139,92,246,0.7)',
];
$distDatasets = [];
foreach ($distBinLabels as $i => $binLabel) {
    $distDatasets[] = [
        'label' => $binLabel,
        'data' => $distBinData[$i] ?? [],
        'backgroundColor' => $distBinColors[$i % count($distBinColors)],
        'stack' => 'dist',
    ];
}

// Latency CDF points from summary percentiles
$cdfPoints = [
    ['pct' => 50, 'ms' => (float) ($latency['p50'] ?? 0)],
    ['pct' => 95, 'ms' => (float) ($latency['p95'] ?? 0)],
    ['pct' => 99, 'ms' => (float) ($latency['p99'] ?? 0)],
    ['pct' => 100, 'ms' => (float) ($latency['max'] ?? 0)],
];

// Status code chart data
$statusLabels = [];
$statusCounts = [];
$statusColors = [];
$palette = ['#6366f1', '#22d3ee', '#f59e0b', '#ef4444', '#10b981', '#8b5cf6', '#f97316'];
$pi = 0;
foreach ($summary['status_codes'] as $code => $item) {
    $statusLabels[] = (string) $code;
    $statusCounts[] = (int) $item['count'];
    $statusColors[] = $palette[$pi % count($palette)];
    $pi++;
}

$jsonLabels      = json_encode($chartLabels, JSON_THROW_ON_ERROR);
$jsonRps         = json_encode($chartRps, JSON_THROW_ON_ERROR);
$jsonTps         = json_encode($chartTps, JSON_THROW_ON_ERROR);
$jsonErr         = json_encode($chartErr, JSON_THROW_ON_ERROR);
$jsonLatency     = json_encode($chartLatency, JSON_THROW_ON_ERROR);
$jsonP50         = json_encode($chartP50, JSON_THROW_ON_ERROR);
$jsonP75         = json_encode($chartP75, JSON_THROW_ON_ERROR);
$jsonP95         = json_encode($chartP95, JSON_THROW_ON_ERROR);
$jsonP99         = json_encode($chartP99, JSON_THROW_ON_ERROR);
$jsonDistDatasets = json_encode($distDatasets, JSON_THROW_ON_ERROR);
$jsonCdfPct      = json_encode(array_column($cdfPoints, 'pct'), JSON_THROW_ON_ERROR);
$jsonCdfMs       = json_encode(array_column($cdfPoints, 'ms'), JSON_THROW_ON_ERROR);
$jsonStLabels    = json_encode($statusLabels, JSON_THROW_ON_ERROR);
$jsonStCounts    = json_encode($statusCounts, JSON_THROW_ON_ERROR);
$jsonStColors    = json_encode($statusColors, JSON_THROW_ON_ERROR);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>eleload report</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['"IBM Plex Sans"', '"Hiragino Sans"', '"Yu Gothic"', '"Noto Sans CJK JP"', '"Helvetica Neue"', 'Helvetica', 'Arial', 'sans-serif'],
          },
        },
      },
    };
  </script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased">
<main class="max-w-5xl mx-auto px-4 py-8 space-y-6">

  <!-- Header -->
  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-bold tracking-tight text-gray-900">eleload report</h1>
      <?php if (is_string($testName) && $testName !== ''): ?>
        <p class="mt-1 text-sm text-gray-500"><?= $esc($testName) ?></p>
      <?php endif; ?>
    </div>
    <span class="text-xs text-gray-400 mt-1">v<?= $esc($report['meta']['version']) ?></span>
  </div>

  <!-- Meta row -->
  <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-500 border-b border-gray-200 pb-4">
    <span><span class="font-medium text-gray-700">URL</span> <?= $esc($report['target']['url']) ?></span>
    <span><span class="font-medium text-gray-700">Method</span> <?= $esc($report['target']['method']) ?></span>
    <span><span class="font-medium text-gray-700">Success Status</span> <?= $esc($successStatusLabel) ?></span>
    <span><span class="font-medium text-gray-700">Concurrency</span> <?= $esc($report['config']['concurrency']) ?></span>
    <span><span class="font-medium text-gray-700">Duration</span> <?= $esc(number_format((float) $summary['duration_sec'], 3)) ?> s</span>
  </div>

  <!-- KPI cards -->
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Requests</div>
      <div class="mt-1 text-2xl font-bold text-gray-900"><?= $esc($requests['total']) ?></div>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Success Rate</div>
      <div class="mt-1 text-2xl font-bold text-emerald-600"><?= $esc($pct((float) $requests['success_rate'])) ?></div>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Error Rate</div>
      <div class="mt-1 text-2xl font-bold <?= (float) $requests['error_rate'] > 0 ? 'text-red-500' : 'text-gray-900' ?>"><?= $esc($pct((float) $requests['error_rate'])) ?></div>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">RPS</div>
      <div class="mt-1 text-2xl font-bold text-gray-900"><?= $esc($num((float) $throughput['rps'])) ?></div>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">p95 (ms)</div>
      <div class="mt-1 text-2xl font-bold text-gray-900"><?= $esc($num((float) $latency['p95'])) ?></div>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">p99 (ms)</div>
      <div class="mt-1 text-2xl font-bold text-gray-900"><?= $esc($num((float) $latency['p99'])) ?></div>
    </div>
  </div>

  <?php if ($hasTimeSeries): ?>
  <!-- Time-series charts -->
  <div class="bg-white border border-gray-200 rounded-lg p-5">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">Throughput over time</h2>
    <canvas id="chartThroughput" height="90"></canvas>
  </div>
  <div class="bg-white border border-gray-200 rounded-lg p-5">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">Avg latency &amp; error rate over time</h2>
    <canvas id="chartLatency" height="90"></canvas>
  </div>

  <!-- Percentile time-series -->
  <?php if ($hasDistData): ?>
  <div class="bg-white border border-gray-200 rounded-lg p-5">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">Latency percentiles over time (p50 / p75 / p95 / p99)</h2>
    <canvas id="chartPercentiles" height="90"></canvas>
  </div>

  <!-- Latency distribution heatmap -->
  <div class="bg-white border border-gray-200 rounded-lg p-5">
    <h2 class="text-sm font-semibold text-gray-700 mb-1">Latency distribution heatmap</h2>
    <p class="text-xs text-gray-400 mb-4">Stacked bars: each second × latency band. Hover for counts.</p>
    <canvas id="chartHeatmap" height="90"></canvas>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <!-- Latency CDF + Status codes -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white border border-gray-200 rounded-lg p-5">
      <h2 class="text-sm font-semibold text-gray-700 mb-4">Latency CDF</h2>
      <canvas id="chartCdf" height="160"></canvas>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-5">
      <h2 class="text-sm font-semibold text-gray-700 mb-4">Status codes</h2>
      <div class="flex items-center gap-6">
        <div class="w-40 h-40 flex-shrink-0"><canvas id="chartStatus"></canvas></div>
        <table class="text-sm w-full">
          <thead>
            <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
              <th class="pb-1 font-semibold">Code</th>
              <th class="pb-1 font-semibold text-right">Count</th>
              <th class="pb-1 font-semibold text-right">Rate</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($summary['status_codes'] as $code => $item): ?>
            <tr class="border-b border-gray-50 last:border-0">
              <td class="py-1 font-mono"><?= $esc($code) ?></td>
              <td class="py-1 text-right text-gray-600"><?= $esc($item['count']) ?></td>
              <td class="py-1 text-right text-gray-600"><?= $esc($pct((float) $item['rate'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Throughput + Latency tables -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white border border-gray-200 rounded-lg p-5">
      <h2 class="text-sm font-semibold text-gray-700 mb-3">Throughput</h2>
      <table class="text-sm w-full">
        <tbody>
          <tr class="border-b border-gray-50"><td class="py-1.5 text-gray-500 w-1/2">RPS</td><td class="py-1.5 font-medium"><?= $esc($num((float) $throughput['rps'])) ?> req/s</td></tr>
          <tr class="border-b border-gray-50"><td class="py-1.5 text-gray-500">TPS</td><td class="py-1.5 font-medium"><?= $esc($num((float) $throughput['tps'])) ?> tx/s</td></tr>
          <tr class="border-b border-gray-50"><td class="py-1.5 text-gray-500">TPS/RPS Rate</td><td class="py-1.5 font-medium"><?= $esc($pct((float) $throughput['tps_rps_rate'])) ?></td></tr>
          <?php if (array_key_exists('target_rps', $throughput)): ?>
            <tr class="border-b border-gray-50"><td class="py-1.5 text-gray-500">Target RPS</td><td class="py-1.5 font-medium"><?= $esc($num((float) $throughput['target_rps'])) ?> req/s</td></tr>
            <tr class="border-b border-gray-50"><td class="py-1.5 text-gray-500">RPS Achievement</td><td class="py-1.5 font-medium"><?= $esc($pct((float) $throughput['rps_achievement_rate'])) ?></td></tr>
          <?php endif; ?>
          <?php if (array_key_exists('target_tps', $throughput)): ?>
            <tr class="border-b border-gray-50"><td class="py-1.5 text-gray-500">Target TPS</td><td class="py-1.5 font-medium"><?= $esc($num((float) $throughput['target_tps'])) ?> tx/s</td></tr>
            <tr><td class="py-1.5 text-gray-500">TPS Achievement</td><td class="py-1.5 font-medium"><?= $esc($pct((float) $throughput['tps_achievement_rate'])) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-5">
      <h2 class="text-sm font-semibold text-gray-700 mb-3">Latency</h2>
      <table class="text-sm w-full">
        <tbody>
          <tr class="border-b border-gray-50"><td class="py-1.5 text-gray-500 w-1/2">min</td><td class="py-1.5 font-medium"><?= $esc($num((float) $latency['min'])) ?> ms</td></tr>
          <tr class="border-b border-gray-50"><td class="py-1.5 text-gray-500">avg</td><td class="py-1.5 font-medium"><?= $esc($num((float) $latency['avg'])) ?> ms</td></tr>
          <tr class="border-b border-gray-50"><td class="py-1.5 text-gray-500">p50</td><td class="py-1.5 font-medium"><?= $esc($num((float) $latency['p50'])) ?> ms</td></tr>
          <tr class="border-b border-gray-50"><td class="py-1.5 text-gray-500">p95</td><td class="py-1.5 font-medium"><?= $esc($num((float) $latency['p95'])) ?> ms</td></tr>
          <tr class="border-b border-gray-50"><td class="py-1.5 text-gray-500">p99</td><td class="py-1.5 font-medium"><?= $esc($num((float) $latency['p99'])) ?> ms</td></tr>
          <tr><td class="py-1.5 text-gray-500">max</td><td class="py-1.5 font-medium"><?= $esc($num((float) $latency['max'])) ?> ms</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (!empty($report['thresholds']['checks'])): ?>
  <!-- Thresholds -->
  <div class="bg-white border border-gray-200 rounded-lg p-5">
    <h2 class="text-sm font-semibold text-gray-700 mb-3">Thresholds</h2>
    <table class="text-sm w-full">
      <thead>
        <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
          <th class="pb-1.5 font-semibold">Check</th>
          <th class="pb-1.5 font-semibold text-right">Actual</th>
          <th class="pb-1.5 font-semibold text-right">Rule</th>
          <th class="pb-1.5 font-semibold text-right">Result</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($report['thresholds']['checks'] as $check): ?>
        <tr class="border-b border-gray-50 last:border-0">
          <td class="py-1.5"><?= $esc($check['name']) ?></td>
          <td class="py-1.5 text-right font-mono"><?= $esc($num((float) $check['actual'])) ?></td>
          <td class="py-1.5 text-right text-gray-500"><?= $esc($check['operator']) ?> <?= $esc($num((float) $check['threshold'])) ?></td>
          <td class="py-1.5 text-right">
            <?php if ($check['passed']): ?>
              <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-700">PASS</span>
            <?php else: ?>
              <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold bg-red-50 text-red-600">FAIL</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Errors -->
  <div class="bg-white border border-gray-200 rounded-lg p-5">
    <h2 class="text-sm font-semibold text-gray-700 mb-3">Errors</h2>
    <?php if ($report['errors'] === []): ?>
      <p class="text-sm text-gray-400">No errors.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="text-sm w-full min-w-[600px]">
          <thead>
            <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
              <th class="pb-1.5 font-semibold">#</th>
              <th class="pb-1.5 font-semibold">HTTP</th>
              <th class="pb-1.5 font-semibold">errno</th>
              <th class="pb-1.5 font-semibold text-right">Latency (ms)</th>
              <th class="pb-1.5 font-semibold">Error</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($report['errors'] as $error): ?>
            <tr class="border-b border-gray-50 last:border-0">
              <td class="py-1.5 font-mono text-gray-400"><?= $esc($error['request']) ?></td>
              <td class="py-1.5 font-mono"><?= $esc($error['http_code']) ?></td>
              <td class="py-1.5 font-mono"><?= $esc($error['error_no']) ?></td>
              <td class="py-1.5 text-right font-mono"><?= $esc($num((float) $error['latency_ms'])) ?></td>
              <td class="py-1.5 text-gray-600 break-all"><?= $esc($error['error'] !== '' ? $error['error'] : '(no message)') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <p class="text-xs text-gray-400 text-right">Generated by eleload <?= $esc($report['meta']['version']) ?></p>

</main>

<script>
(function () {
  'use strict';
  const GRID_COLOR = 'rgba(0,0,0,0.05)';
  const baseOptions = {
    responsive: true,
    plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } },
    scales: {
      x: { grid: { color: GRID_COLOR }, ticks: { font: { size: 11 } } },
      y: { grid: { color: GRID_COLOR }, ticks: { font: { size: 11 } }, beginAtZero: true },
    },
  };

  <?php if ($hasTimeSeries): ?>
  // Throughput time-series
  new Chart(document.getElementById('chartThroughput'), {
    type: 'line',
    data: {
      labels: <?= $jsonLabels ?>,
      datasets: [
        { label: 'RPS', data: <?= $jsonRps ?>, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.08)', tension: 0.3, pointRadius: 2, fill: true },
        { label: 'TPS', data: <?= $jsonTps ?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)', tension: 0.3, pointRadius: 2, fill: true },
      ],
    },
    options: { ...baseOptions, scales: { ...baseOptions.scales, x: { ...baseOptions.scales.x, title: { display: true, text: 'elapsed (s)', font: { size: 11 } } } } },
  });

  // Latency + error rate time-series (dual axis)
  new Chart(document.getElementById('chartLatency'), {
    type: 'line',
    data: {
      labels: <?= $jsonLabels ?>,
      datasets: [
        { label: 'Avg Latency (ms)', data: <?= $jsonLatency ?>, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.08)', tension: 0.3, pointRadius: 2, fill: true, yAxisID: 'y' },
        { label: 'Error Rate (%)',   data: <?= $jsonErr ?>,     borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.08)',   tension: 0.3, pointRadius: 2, fill: true, yAxisID: 'y2' },
      ],
    },
    options: {
      ...baseOptions,
      scales: {
        x:  { ...baseOptions.scales.x, title: { display: true, text: 'elapsed (s)', font: { size: 11 } } },
        y:  { ...baseOptions.scales.y, position: 'left',  title: { display: true, text: 'ms', font: { size: 11 } } },
        y2: { ...baseOptions.scales.y, position: 'right', title: { display: true, text: '%', font: { size: 11 } }, grid: { drawOnChartArea: false } },
      },
    },
  });
  <?php if ($hasDistData): ?>

  // Percentile time-series
  new Chart(document.getElementById('chartPercentiles'), {
    type: 'line',
    data: {
      labels: <?= $jsonLabels ?>,
      datasets: [
        { label: 'p50', data: <?= $jsonP50 ?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0)',  tension: 0.3, pointRadius: 0, borderWidth: 1.5 },
        { label: 'p75', data: <?= $jsonP75 ?>, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0)',  tension: 0.3, pointRadius: 0, borderWidth: 1.5 },
        { label: 'p95', data: <?= $jsonP95 ?>, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0)',  tension: 0.3, pointRadius: 0, borderWidth: 2 },
        { label: 'p99', data: <?= $jsonP99 ?>, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0)',   tension: 0.3, pointRadius: 0, borderWidth: 2 },
      ],
    },
    options: {
      ...baseOptions,
      scales: {
        x: { ...baseOptions.scales.x, title: { display: true, text: 'elapsed (s)', font: { size: 11 } } },
        y: { ...baseOptions.scales.y, title: { display: true, text: 'latency (ms)', font: { size: 11 } } },
      },
    },
  });

  // Latency distribution heatmap (stacked bar)
  new Chart(document.getElementById('chartHeatmap'), {
    type: 'bar',
    data: {
      labels: <?= $jsonLabels ?>,
      datasets: <?= $jsonDistDatasets ?>,
    },
    options: {
      ...baseOptions,
      plugins: { ...baseOptions.plugins, tooltip: { mode: 'index' } },
      scales: {
        x: { ...baseOptions.scales.x, stacked: true, title: { display: true, text: 'elapsed (s)', font: { size: 11 } } },
        y: { ...baseOptions.scales.y, stacked: true, title: { display: true, text: 'requests', font: { size: 11 } } },
      },
    },
  });
  <?php endif; ?>
  <?php endif; ?>

  // Latency CDF
  new Chart(document.getElementById('chartCdf'), {
    type: 'line',
    data: {
      labels: <?= $jsonCdfPct ?>,
      datasets: [{
        label: 'Latency (ms)',
        data: <?= $jsonCdfMs ?>,
        borderColor: '#22d3ee',
        backgroundColor: 'rgba(34,211,238,0.1)',
        tension: 0.3,
        pointRadius: 5,
        fill: true,
      }],
    },
    options: {
      ...baseOptions,
      scales: {
        x: { ...baseOptions.scales.x, title: { display: true, text: 'percentile', font: { size: 11 } } },
        y: { ...baseOptions.scales.y, title: { display: true, text: 'latency (ms)', font: { size: 11 } } },
      },
    },
  });

  // Status code donut
  new Chart(document.getElementById('chartStatus'), {
    type: 'doughnut',
    data: {
      labels: <?= $jsonStLabels ?>,
      datasets: [{ data: <?= $jsonStCounts ?>, backgroundColor: <?= $jsonStColors ?>, borderWidth: 0 }],
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (ctx) => ` ${ctx.label}: ${ctx.parsed}` } },
      },
      cutout: '68%',
    },
  });
}());
</script>
</body>
</html>

