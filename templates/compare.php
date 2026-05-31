<?php

declare(strict_types=1);

/** @var array<string, mixed> $report */
$esc    = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$num    = static fn (float $value): string  => number_format($value, 2);
$signed = static function (float $value) use ($num): string {
    if ($value > 0.0) {
        return '+' . $num($value);
    }
    if ($value < 0.0) {
        return $num($value);
    }
    return '0.00';
};

$statusClass = static fn (string $status): string => match ($status) {
    'improved'   => 'bg-green-100 text-green-800',
    'regressed'  => 'bg-red-100 text-red-800',
    default      => 'bg-gray-100 text-gray-600',
};
$statusLabel = static fn (string $status): string => match ($status) {
    'improved'  => 'Improved',
    'regressed' => 'Regressed',
    default     => 'Unchanged',
};

$deltaClass = static fn (string $status): string => match ($status) {
    'improved'  => 'text-green-700 font-semibold',
    'regressed' => 'text-red-700 font-semibold',
    default     => 'text-gray-500',
};

/** @var list<array{t:int,rps:float,tps:float,error_rate:float,avg_latency_ms:float}> $beforeBuckets */
$beforeBuckets = $report['before_time_buckets'] ?? [];
/** @var list<array{t:int,rps:float,tps:float,error_rate:float,avg_latency_ms:float}> $afterBuckets */
$afterBuckets  = $report['after_time_buckets'] ?? [];

$toJson = static fn (mixed $v): string => (string) json_encode($v, JSON_UNESCAPED_SLASHES);
?><!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>eleload compare report</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
  <style>
    body { font-family: "IBM Plex Sans","Hiragino Sans","Yu Gothic","Helvetica Neue",Helvetica,Arial,sans-serif; }
    .card-hover { transition: box-shadow .15s; }
    .card-hover:hover { box-shadow: 0 8px 24px rgba(0,0,0,.1); }
  </style>
</head>
<body class="bg-slate-50 min-h-screen">

<!-- HEADER -->
<header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
  <div class="flex items-center gap-3">
    <span class="text-2xl font-bold tracking-tight text-slate-800">eleload</span>
    <span class="text-slate-400 text-lg">compare report</span>
  </div>
  <div class="text-xs text-slate-400">v<?= $esc($report['meta']['version']) ?></div>
</header>

<main class="max-w-6xl mx-auto px-4 py-6 space-y-6">

  <!-- SUMMARY BANNER -->
  <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
    <div class="flex flex-wrap gap-4 items-center mb-4">
      <div class="flex-1 min-w-0">
        <h1 class="text-xl font-bold text-slate-800">Performance Comparison</h1>
        <p class="text-sm text-slate-500 mt-1">Higher is better for RPS/TPS; lower is better for latency &amp; error rate</p>
      </div>
      <div class="flex gap-3">
        <div class="text-center">
          <div class="text-2xl font-bold text-green-600"><?= $esc((string)$report['summary']['improved']) ?></div>
          <div class="text-xs text-slate-500 uppercase tracking-wide">Improved</div>
        </div>
        <div class="text-center">
          <div class="text-2xl font-bold text-red-500"><?= $esc((string)$report['summary']['regressed']) ?></div>
          <div class="text-xs text-slate-500 uppercase tracking-wide">Regressed</div>
        </div>
        <div class="text-center">
          <div class="text-2xl font-bold text-slate-400"><?= $esc((string)$report['summary']['unchanged']) ?></div>
          <div class="text-xs text-slate-500 uppercase tracking-wide">Unchanged</div>
        </div>
      </div>
    </div>

    <!-- Threshold toggle -->
    <div class="flex items-center gap-4 border-t border-slate-100 pt-3">
      <span class="text-xs text-slate-500">Highlight threshold:</span>
      <div id="threshold-controls" class="flex gap-2">
        <button onclick="setThreshold(0)" class="threshold-btn px-3 py-1 rounded-full text-xs border border-slate-300 hover:bg-slate-100">All</button>
        <button onclick="setThreshold(5)" class="threshold-btn px-3 py-1 rounded-full text-xs border border-slate-300 hover:bg-slate-100">&gt;5%</button>
        <button onclick="setThreshold(10)" class="threshold-btn px-3 py-1 rounded-full text-xs border border-slate-300 hover:bg-slate-100">&gt;10%</button>
      </div>
    </div>
  </section>

  <!-- SIDE-BY-SIDE METRIC CARDS -->
  <section>
    <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">Key Metrics</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
      <?php foreach ($report['metrics'] as $metric): ?>
        <?php
        $dr    = $metric['delta_rate'] !== null ? (float) $metric['delta_rate'] : null;
        $absdr = $dr !== null ? abs($dr) : null;
        ?>
        <div class="metric-card card-hover bg-white rounded-xl border border-slate-200 shadow-sm p-4"
             data-delta-pct="<?= $esc((string)($absdr ?? 0)) ?>"
             data-status="<?= $esc($metric['status']) ?>">
          <div class="text-xs text-slate-500 uppercase tracking-wide mb-1"><?= $esc($metric['label']) ?></div>
          <div class="flex items-end gap-2 mb-2">
            <div class="text-xs text-slate-400">Before</div>
            <div class="text-sm font-semibold text-slate-700"><?= $esc($num((float)$metric['before'])) ?></div>
          </div>
          <div class="flex items-end gap-2 mb-3">
            <div class="text-xs text-slate-400">After</div>
            <div class="text-lg font-bold text-slate-900"><?= $esc($num((float)$metric['after'])) ?></div>
          </div>
          <div class="flex items-center justify-between">
            <span class="<?= $esc($deltaClass($metric['status'])) ?> text-sm">
              <?= $esc($signed((float)$metric['delta'])) ?>
              <?= $dr !== null ? '(' . $esc($signed($dr)) . '%)' : '' ?>
            </span>
            <span class="text-xs rounded-full px-2 py-0.5 <?= $esc($statusClass($metric['status'])) ?>">
              <?= $esc($statusLabel($metric['status'])) ?>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- TIME-SERIES OVERLAY -->
  <?php if ($beforeBuckets !== [] || $afterBuckets !== []): ?>
  <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-base font-semibold text-slate-800">Time-Series Overlay</h2>
      <div class="flex gap-2 text-xs">
        <span class="flex items-center gap-1"><span class="inline-block w-4 h-1 bg-blue-500 rounded"></span>Before</span>
        <span class="flex items-center gap-1"><span class="inline-block w-4 h-1 bg-orange-400 rounded"></span>After</span>
      </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div>
        <div class="text-xs text-slate-500 mb-2">RPS (requests / sec)</div>
        <canvas id="chartRps" height="160"></canvas>
      </div>
      <div>
        <div class="text-xs text-slate-500 mb-2">Avg Latency (ms)</div>
        <canvas id="chartLatency" height="160"></canvas>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- DETAILED DIFF TABLE -->
  <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
    <h2 class="text-base font-semibold text-slate-800 mb-4">Detailed Diff</h2>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-xs text-slate-500 uppercase tracking-wider border-b border-slate-200">
            <th class="text-left py-2 pr-4 font-semibold">Metric</th>
            <th class="text-right py-2 px-4 font-semibold">Before</th>
            <th class="text-right py-2 px-4 font-semibold">After</th>
            <th class="text-right py-2 px-4 font-semibold">Delta</th>
            <th class="text-right py-2 px-4 font-semibold">Δ%</th>
            <th class="text-center py-2 pl-4 font-semibold">Result</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($report['metrics'] as $metric): ?>
            <?php $dr = $metric['delta_rate'] !== null ? (float) $metric['delta_rate'] : null; ?>
            <tr class="hover:bg-slate-50">
              <td class="py-3 pr-4 font-medium text-slate-800"><?= $esc($metric['label']) ?></td>
              <td class="py-3 px-4 text-right text-slate-600"><?= $esc($num((float)$metric['before'])) ?></td>
              <td class="py-3 px-4 text-right font-semibold text-slate-800"><?= $esc($num((float)$metric['after'])) ?></td>
              <td class="py-3 px-4 text-right <?= $esc($deltaClass($metric['status'])) ?>"><?= $esc($signed((float)$metric['delta'])) ?></td>
              <td class="py-3 px-4 text-right <?= $esc($deltaClass($metric['status'])) ?>"><?= $dr !== null ? $esc($signed($dr)) . '%' : 'n/a' ?></td>
              <td class="py-3 pl-4 text-center">
                <span class="text-xs rounded-full px-2.5 py-1 font-semibold <?= $esc($statusClass($metric['status'])) ?>">
                  <?= $esc($statusLabel($metric['status'])) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- INPUTS -->
  <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
    <h2 class="text-base font-semibold text-slate-800 mb-4">Test Inputs</h2>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-xs text-slate-500 uppercase tracking-wider border-b border-slate-200">
            <th class="text-left py-2 pr-6 font-semibold">Item</th>
            <th class="text-left py-2 px-6 font-semibold">Before</th>
            <th class="text-left py-2 pl-6 font-semibold">After</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr class="hover:bg-slate-50">
            <td class="py-3 pr-6 text-slate-500">URL</td>
            <td class="py-3 px-6 font-mono text-xs text-slate-700 break-all"><?= $esc($report['before']['url']) ?></td>
            <td class="py-3 pl-6 font-mono text-xs text-slate-700 break-all"><?= $esc($report['after']['url']) ?></td>
          </tr>
          <tr class="hover:bg-slate-50">
            <td class="py-3 pr-6 text-slate-500">Method</td>
            <td class="py-3 px-6 font-semibold text-slate-700"><?= $esc($report['before']['method']) ?></td>
            <td class="py-3 pl-6 font-semibold text-slate-700"><?= $esc($report['after']['method']) ?></td>
          </tr>
          <tr class="hover:bg-slate-50">
            <td class="py-3 pr-6 text-slate-500">Test Name</td>
            <td class="py-3 px-6 text-slate-700"><?= $esc($report['before']['test_name'] ?? '') ?></td>
            <td class="py-3 pl-6 text-slate-700"><?= $esc($report['after']['test_name'] ?? '') ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <footer class="text-center text-xs text-slate-400 py-2">
    Generated by eleload <?= $esc($report['meta']['version']) ?>
  </footer>
</main>

<script>
// ----- Threshold filter -----
function setThreshold(pct) {
  document.querySelectorAll('.threshold-btn').forEach(function(btn) {
    btn.classList.remove('bg-blue-600','text-white','border-blue-600');
    btn.classList.add('border-slate-300');
  });
  var active = event.target;
  active.classList.add('bg-blue-600','text-white','border-blue-600');
  active.classList.remove('border-slate-300');

  document.querySelectorAll('.metric-card').forEach(function(card) {
    var dp = parseFloat(card.dataset.deltaPct) || 0;
    if (pct === 0 || dp >= pct) {
      card.classList.remove('opacity-30');
    } else {
      card.classList.add('opacity-30');
    }
  });
}
// Set "All" as default active
(function() {
  var btns = document.querySelectorAll('.threshold-btn');
  if (btns.length > 0) {
    btns[0].classList.add('bg-blue-600','text-white','border-blue-600');
    btns[0].classList.remove('border-slate-300');
  }
})();

// ----- Chart helpers -----
var BEFORE_BUCKETS = <?= $toJson($beforeBuckets) ?>;
var AFTER_BUCKETS  = <?= $toJson($afterBuckets) ?>;

function makeLabels(buckets) {
  if (!buckets || !buckets.length) return [];
  return buckets.map(function(b) { return 't=' + b.t + 's'; });
}

function buildOverlayChart(id, bField, aField) {
  var canvas = document.getElementById(id);
  if (!canvas) return;
  var bLabels = makeLabels(BEFORE_BUCKETS);
  var aLabels = makeLabels(AFTER_BUCKETS);
  var allLen  = Math.max(bLabels.length, aLabels.length);
  if (allLen === 0) { return; }

  var labels = allLen === bLabels.length ? bLabels : aLabels;

  new Chart(canvas, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Before',
          data: BEFORE_BUCKETS.map(function(b) { return b[bField]; }),
          borderColor: 'rgb(59,130,246)',
          backgroundColor: 'rgba(59,130,246,0.08)',
          borderWidth: 2,
          pointRadius: 0,
          tension: 0.3,
          fill: true,
        },
        {
          label: 'After',
          data: AFTER_BUCKETS.map(function(b) { return b[aField]; }),
          borderColor: 'rgb(251,146,60)',
          backgroundColor: 'rgba(251,146,60,0.08)',
          borderWidth: 2,
          pointRadius: 0,
          tension: 0.3,
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { maxTicksLimit: 10, font: { size: 11 } }, grid: { color: 'rgba(0,0,0,.04)' } },
        y: { ticks: { font: { size: 11 } }, grid: { color: 'rgba(0,0,0,.04)' }, beginAtZero: true },
      },
    },
  });
}

buildOverlayChart('chartRps',     'rps',            'rps');
buildOverlayChart('chartLatency', 'avg_latency_ms', 'avg_latency_ms');
</script>
</body>
</html>
