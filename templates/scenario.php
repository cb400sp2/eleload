<?php

declare(strict_types=1);

/** @var array<string, mixed> $report */
$summary  = $report['summary'];
$steps    = $report['steps'] ?? [];
$scenario = $report['scenario'] ?? [];
$testName = $scenario['name'] ?? null;

$esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$pct = static fn (float $value): string => number_format($value, 2) . '%';
$num = static fn (float $value): string => number_format($value, 2);

// Highlight thresholds
$slowP95Threshold  = 1000.0; // ms — p95 > 1 s is highlighted
$highErrorThreshold = 5.0;   // % — error rate > 5% is highlighted

// Sort steps by p95Ms descending for the bottleneck chart
$sortedSteps = $steps;
usort($sortedSteps, static fn (array $a, array $b): int => ($b['p95Ms'] ?? 0) <=> ($a['p95Ms'] ?? 0));

$stepNames  = array_column($steps, 'name');
$stepAvg    = array_map(static fn (array $s): float => (float) ($s['avgMs'] ?? 0.0), $steps);
$stepP95    = array_map(static fn (array $s): float => (float) ($s['p95Ms'] ?? 0.0), $steps);
$stepP99    = array_map(static fn (array $s): float => (float) ($s['p99Ms'] ?? 0.0), $steps);
$stepErrors = array_map(static fn (array $s): float => (float) ($s['errorRate'] ?? 0.0), $steps);

$jsonNames  = (string) json_encode($stepNames, JSON_THROW_ON_ERROR);
$jsonAvg    = (string) json_encode($stepAvg, JSON_THROW_ON_ERROR);
$jsonP95    = (string) json_encode($stepP95, JSON_THROW_ON_ERROR);
$jsonP99    = (string) json_encode($stepP99, JSON_THROW_ON_ERROR);
$jsonErrors = (string) json_encode($stepErrors, JSON_THROW_ON_ERROR);

$hasSteps = $steps !== [];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>eleload scenario report</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
</head>
<body class="bg-gray-50 text-gray-800 antialiased" style="font-family:'IBM Plex Sans','Hiragino Sans','Yu Gothic',sans-serif">
<main class="max-w-5xl mx-auto px-4 py-8 space-y-6">

  <!-- Header -->
  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-bold tracking-tight text-gray-900">eleload scenario report</h1>
      <?php if (is_string($testName) && $testName !== ''): ?>
        <p class="mt-1 text-sm text-gray-500"><?= $esc($testName) ?></p>
      <?php endif; ?>
    </div>
    <span class="text-xs text-gray-400 mt-1">v<?= $esc($report['meta']['version']) ?></span>
  </div>

  <!-- KPI row -->
  <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Iterations</div>
      <div class="mt-1 text-2xl font-bold text-gray-900"><?= $esc($summary['total_iterations'] ?? 0) ?></div>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Success</div>
      <div class="mt-1 text-2xl font-bold text-emerald-600"><?= $esc($summary['success_iterations'] ?? 0) ?></div>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Failed</div>
      <div class="mt-1 text-2xl font-bold text-red-500"><?= $esc($summary['failed_iterations'] ?? 0) ?></div>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">TPS</div>
      <div class="mt-1 text-2xl font-bold text-indigo-600"><?= $esc($num((float) ($summary['tps'] ?? 0.0))) ?></div>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Error Rate</div>
      <div class="mt-1 text-2xl font-bold <?= (float) ($summary['error_rate'] ?? 0) > 0 ? 'text-red-500' : 'text-emerald-600' ?>">
        <?= $esc($pct((float) ($summary['error_rate'] ?? 0.0))) ?>
      </div>
    </div>
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Duration</div>
      <div class="mt-1 text-2xl font-bold text-gray-700"><?= $esc($num((float) ($summary['duration_sec'] ?? 0.0))) ?>s</div>
    </div>
  </div>

  <?php if ($hasSteps): ?>

  <!-- Step breakdown table -->
  <div class="bg-white border border-gray-200 rounded-lg p-5">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-base font-semibold text-gray-800">Step Breakdown</h2>
      <div class="flex gap-3 text-xs text-gray-400">
        <span class="flex items-center gap-1">
          <span class="inline-block w-3 h-3 rounded bg-red-100 border border-red-300"></span>p95 &gt; <?= $esc(number_format($slowP95Threshold)) ?>ms
        </span>
        <span class="flex items-center gap-1">
          <span class="inline-block w-3 h-3 rounded bg-amber-100 border border-amber-300"></span>error &gt; <?= $esc(number_format($highErrorThreshold)) ?>%
        </span>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-200">
            <th class="text-left py-2 pr-4 font-semibold">#</th>
            <th class="text-left py-2 pr-6 font-semibold">Step</th>
            <th class="text-right py-2 px-4 font-semibold">Count</th>
            <th class="text-right py-2 px-4 font-semibold">Success</th>
            <th class="text-right py-2 px-4 font-semibold">Error %</th>
            <th class="text-right py-2 px-4 font-semibold">RPS</th>
            <th class="text-right py-2 px-4 font-semibold">Avg ms</th>
            <th class="text-right py-2 px-4 font-semibold">p50 ms</th>
            <th class="text-right py-2 px-4 font-semibold">p95 ms</th>
            <th class="text-right py-2 px-4 font-semibold">p99 ms</th>
            <th class="text-right py-2 pl-4 font-semibold">Max ms</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($steps as $step): ?>
            <?php
            $isSlow = (float) ($step['p95Ms'] ?? 0.0) > $slowP95Threshold;
            $isErrory = (float) ($step['errorRate'] ?? 0.0) > $highErrorThreshold;
            $rowClass = $isSlow ? 'bg-red-50 hover:bg-red-100' : ($isErrory ? 'bg-amber-50 hover:bg-amber-100' : 'hover:bg-gray-50');
            ?>
            <tr class="<?= $esc($rowClass) ?>">
              <td class="py-3 pr-4 text-gray-400 text-xs"><?= $esc($step['index'] + 1) ?></td>
              <td class="py-3 pr-6 font-medium text-gray-800">
                <?= $esc($step['name']) ?>
                <?php if ($isSlow): ?>
                  <span class="ml-1 text-xs rounded-full px-2 py-0.5 bg-red-100 text-red-700 font-semibold">slow</span>
                <?php elseif ($isErrory): ?>
                  <span class="ml-1 text-xs rounded-full px-2 py-0.5 bg-amber-100 text-amber-700 font-semibold">errors</span>
                <?php endif; ?>
              </td>
              <td class="py-3 px-4 text-right text-gray-600"><?= $esc($step['count'] ?? 0) ?></td>
              <td class="py-3 px-4 text-right text-emerald-700"><?= $esc($step['successCount'] ?? 0) ?></td>
              <td class="py-3 px-4 text-right <?= $isErrory ? 'text-red-600 font-semibold' : 'text-gray-600' ?>">
                <?= $esc($pct((float) ($step['errorRate'] ?? 0.0))) ?>
              </td>
              <td class="py-3 px-4 text-right text-gray-600"><?= $esc($num((float) ($step['rps'] ?? 0.0))) ?></td>
              <td class="py-3 px-4 text-right text-gray-600"><?= $esc($num((float) ($step['avgMs'] ?? 0.0))) ?></td>
              <td class="py-3 px-4 text-right text-gray-600"><?= $esc($num((float) ($step['p50Ms'] ?? 0.0))) ?></td>
              <td class="py-3 px-4 text-right <?= $isSlow ? 'text-red-600 font-semibold' : 'text-gray-600' ?>">
                <?= $esc($num((float) ($step['p95Ms'] ?? 0.0))) ?>
              </td>
              <td class="py-3 px-4 text-right text-gray-600"><?= $esc($num((float) ($step['p99Ms'] ?? 0.0))) ?></td>
              <td class="py-3 pl-4 text-right text-gray-600"><?= $esc($num((float) ($step['maxMs'] ?? 0.0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Step latency chart -->
  <div class="bg-white border border-gray-200 rounded-lg p-5">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">Step latency comparison (avg / p95 / p99)</h2>
    <canvas id="chartStepLatency" height="100"></canvas>
  </div>

  <!-- Step error rate chart -->
  <?php
  $hasErrors = false;
  foreach ($steps as $s) {
      if ((float) ($s['errorRate'] ?? 0.0) > 0.0) {
          $hasErrors = true;
          break;
      }
  }
  ?>
  <?php if ($hasErrors): ?>
  <div class="bg-white border border-gray-200 rounded-lg p-5">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">Step error rates</h2>
    <canvas id="chartStepErrors" height="80"></canvas>
  </div>
  <?php endif; ?>

  <?php endif; ?>

  <!-- Scenario steps definition -->
  <?php if (isset($scenario['steps']) && is_array($scenario['steps'])): ?>
  <div class="bg-white border border-gray-200 rounded-lg p-5">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">Scenario Definition</h2>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-200">
            <th class="text-left py-2 pr-4 font-semibold">#</th>
            <th class="text-left py-2 pr-6 font-semibold">Name</th>
            <th class="text-left py-2 pr-6 font-semibold">Method</th>
            <th class="text-left py-2 font-semibold">URL</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($scenario['steps'] as $i => $stepDef): ?>
            <tr class="hover:bg-gray-50">
              <td class="py-3 pr-4 text-gray-400 text-xs"><?= $esc($i + 1) ?></td>
              <td class="py-3 pr-6 font-medium text-gray-800"><?= $esc($stepDef['name'] ?? '') ?></td>
              <td class="py-3 pr-6 font-mono text-xs font-semibold text-indigo-700"><?= $esc($stepDef['method'] ?? 'GET') ?></td>
              <td class="py-3 font-mono text-xs text-gray-600 break-all"><?= $esc($stepDef['url'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <p class="text-xs text-gray-400 text-right">Generated by eleload <?= $esc($report['meta']['version']) ?></p>
</main>

<?php if ($hasSteps): ?>
<script>
(function () {
  'use strict';
  const GRID = 'rgba(0,0,0,0.05)';
  const base = {
    responsive: true,
    plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } },
    scales: {
      x: { grid: { color: GRID }, ticks: { font: { size: 10 } } },
      y: { grid: { color: GRID }, ticks: { font: { size: 10 } }, beginAtZero: true },
    },
  };

  // Step latency bar chart
  new Chart(document.getElementById('chartStepLatency'), {
    type: 'bar',
    data: {
      labels: <?= $jsonNames ?>,
      datasets: [
        { label: 'Avg ms',  data: <?= $jsonAvg ?>,  backgroundColor: 'rgba(148,163,184,0.6)', borderWidth: 0 },
        { label: 'p95 ms',  data: <?= $jsonP95 ?>,  backgroundColor: 'rgba(245,158,11,0.7)',  borderWidth: 0 },
        { label: 'p99 ms',  data: <?= $jsonP99 ?>,  backgroundColor: 'rgba(239,68,68,0.6)',   borderWidth: 0 },
      ],
    },
    options: {
      ...base,
      scales: {
        ...base.scales,
        y: { ...base.scales.y, title: { display: true, text: 'latency (ms)', font: { size: 10 } } },
      },
    },
  });

  <?php if ($hasErrors): ?>
  // Step error rate chart
  new Chart(document.getElementById('chartStepErrors'), {
    type: 'bar',
    data: {
      labels: <?= $jsonNames ?>,
      datasets: [
        { label: 'Error %', data: <?= $jsonErrors ?>, backgroundColor: 'rgba(239,68,68,0.7)', borderWidth: 0 },
      ],
    },
    options: {
      ...base,
      scales: {
        ...base.scales,
        y: { ...base.scales.y, title: { display: true, text: 'error rate (%)', font: { size: 10 } } },
      },
    },
  });
  <?php endif; ?>
}());
</script>
<?php endif; ?>
</body>
</html>
