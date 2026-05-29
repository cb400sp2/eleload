<?php

declare(strict_types=1);

/** @var array<string, mixed> $report */
$esc = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$num = static fn (float $value): string => number_format($value, 2);
$signed = static function (float $value) use ($num): string {
    if ($value > 0.0) {
        return '+' . $num($value);
    }
    if ($value < 0.0) {
        return $num($value);
    }

    return '0.00';
};
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>eleload compare report</title>
  <style>
    :root {
      --bg: #f7faf8;
      --card: #ffffff;
      --ink: #15202b;
      --muted: #58697b;
      --line: #d8e2ea;
      --improved-bg: #e6f7eb;
      --improved-ink: #12633a;
      --regressed-bg: #ffe9e8;
      --regressed-ink: #9a1b1b;
      --unchanged-bg: #f0f2f5;
      --unchanged-ink: #425466;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "IBM Plex Sans", "Hiragino Sans", "Yu Gothic", "Noto Sans CJK JP", "Helvetica Neue", Helvetica, Arial, sans-serif;
      color: var(--ink);
      background:
        radial-gradient(circle at 5% 0%, #e2f0e7 0%, rgba(226, 240, 231, 0) 45%),
        radial-gradient(circle at 95% 20%, #e5efff 0%, rgba(229, 239, 255, 0) 40%),
        var(--bg);
    }
    .wrap {
      max-width: 1080px;
      margin: 26px auto 56px;
      padding: 0 16px;
    }
    .hero, .panel {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 12px;
      box-shadow: 0 10px 24px rgba(13, 40, 70, 0.05);
    }
    .hero {
      padding: 18px 20px;
    }
    h1 {
      margin: 0;
      font-size: 24px;
      letter-spacing: 0.02em;
    }
    .meta {
      margin-top: 8px;
      color: var(--muted);
      font-size: 14px;
    }
    .cards {
      margin-top: 14px;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 10px;
    }
    .card {
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 10px 12px;
      background: #fbfdff;
    }
    .k {
      font-size: 12px;
      color: var(--muted);
      letter-spacing: 0.03em;
      text-transform: uppercase;
    }
    .v {
      margin-top: 4px;
      font-size: 24px;
      font-weight: 700;
    }
    .panel {
      margin-top: 16px;
      padding: 14px;
    }
    h2 {
      margin: 0 0 10px;
      font-size: 16px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }
    th, td {
      border-bottom: 1px solid var(--line);
      padding: 8px 6px;
      text-align: left;
      vertical-align: top;
    }
    th {
      color: var(--muted);
      font-weight: 600;
    }
    tr:last-child td { border-bottom: none; }
    .result {
      border-radius: 999px;
      padding: 2px 10px;
      font-size: 12px;
      font-weight: 700;
      display: inline-block;
      letter-spacing: 0.02em;
    }
    .result.improved {
      color: var(--improved-ink);
      background: var(--improved-bg);
    }
    .result.regressed {
      color: var(--regressed-ink);
      background: var(--regressed-bg);
    }
    .result.unchanged {
      color: var(--unchanged-ink);
      background: var(--unchanged-bg);
    }
    .foot {
      margin-top: 12px;
      color: var(--muted);
      font-size: 12px;
    }
  </style>
</head>
<body>
<main class="wrap">
  <section class="hero">
    <h1>eleload compare report</h1>
    <div class="meta">
      RPS/TPS は高いほど改善、p95/p99/error rate は低いほど改善
    </div>
    <div class="cards">
      <div class="card"><div class="k">Improved</div><div class="v"><?= $esc($report['summary']['improved']) ?></div></div>
      <div class="card"><div class="k">Regressed</div><div class="v"><?= $esc($report['summary']['regressed']) ?></div></div>
      <div class="card"><div class="k">Unchanged</div><div class="v"><?= $esc($report['summary']['unchanged']) ?></div></div>
    </div>
  </section>

  <section class="panel">
    <h2>Inputs</h2>
    <table>
      <thead>
      <tr>
        <th>Item</th>
        <th>Before</th>
        <th>After</th>
      </tr>
      </thead>
      <tbody>
      <tr><th>URL</th><td><?= $esc($report['before']['url']) ?></td><td><?= $esc($report['after']['url']) ?></td></tr>
      <tr><th>Method</th><td><?= $esc($report['before']['method']) ?></td><td><?= $esc($report['after']['method']) ?></td></tr>
      <tr><th>Test Name</th><td><?= $esc($report['before']['test_name'] ?? '') ?></td><td><?= $esc($report['after']['test_name'] ?? '') ?></td></tr>
      </tbody>
    </table>
  </section>

  <section class="panel">
    <h2>Metric Deltas</h2>
    <table>
      <thead>
      <tr>
        <th>Metric</th>
        <th>Before</th>
        <th>After</th>
        <th>Delta</th>
        <th>Delta %</th>
        <th>Direction</th>
        <th>Result</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($report['metrics'] as $metric): ?>
        <tr>
          <td><?= $esc($metric['label']) ?></td>
          <td><?= $esc($num((float)$metric['before'])) ?></td>
          <td><?= $esc($num((float)$metric['after'])) ?></td>
          <td><?= $esc($signed((float)$metric['delta'])) ?></td>
          <td><?= $metric['delta_rate'] === null ? 'n/a' : $esc($num((float)$metric['delta_rate'])) . '%' ?></td>
          <td><?= $esc($metric['direction'] === 'higher' ? 'Higher is better' : 'Lower is better') ?></td>
          <td><span class="result <?= $esc($metric['status']) ?>"><?= $esc(strtoupper((string)$metric['status'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <div class="foot">
    Generated by eleload <?= $esc($report['meta']['version']) ?>
  </div>
</main>
</body>
</html>

