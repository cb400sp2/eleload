<?php

declare(strict_types=1);

/** @var array<string, mixed> $report */
$summary = $report['summary'];
$requests = $summary['requests'];
$throughput = $summary['throughput'];
$latency = $summary['latency'];

$esc = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$pct = static fn (float $value): string => number_format($value, 2) . '%';
$num = static fn (float $value): string => number_format($value, 2);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>eleload report</title>
  <style>
    :root {
      --bg: #f8fbff;
      --card: #ffffff;
      --ink: #1c2a3a;
      --muted: #617184;
      --accent: #0d6dff;
      --ok: #0f9d58;
      --ng: #d93025;
      --line: #dfe7f2;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "IBM Plex Sans", "Helvetica Neue", Helvetica, Arial, sans-serif;
      color: var(--ink);
      background:
        radial-gradient(circle at 0% 0%, #e4f0ff 0%, rgba(228, 240, 255, 0) 45%),
        radial-gradient(circle at 100% 20%, #ffe8d7 0%, rgba(255, 232, 215, 0) 40%),
        var(--bg);
    }
    .wrap {
      max-width: 1040px;
      margin: 24px auto 56px;
      padding: 0 16px;
    }
    .hero {
      background: linear-gradient(120deg, #ffffff 0%, #f2f7ff 100%);
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 18px 20px;
      box-shadow: 0 14px 28px rgba(24, 47, 79, 0.06);
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
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 8px 20px;
    }
    .cards {
      margin-top: 16px;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 10px;
    }
    .card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 10px 12px;
    }
    .k {
      color: var(--muted);
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .v {
      margin-top: 4px;
      font-size: 20px;
      font-weight: 700;
    }
    .v.ok { color: var(--ok); }
    .v.ng { color: var(--ng); }
    .panel {
      margin-top: 16px;
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 12px;
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
    .foot {
      margin-top: 14px;
      color: var(--muted);
      font-size: 12px;
    }
  </style>
</head>
<body>
<main class="wrap">
  <section class="hero">
    <h1>eleload report</h1>
    <div class="meta">
      <div>URL: <?= $esc($report['target']['url']) ?></div>
      <div>Method: <?= $esc($report['target']['method']) ?></div>
      <div>Requests: <?= $esc($requests['total']) ?></div>
      <div>Concurrency: <?= $esc($report['config']['concurrency']) ?></div>
      <div>Duration: <?= $esc(number_format((float)$summary['duration_sec'], 3)) ?> sec</div>
    </div>
    <div class="cards">
      <div class="card"><div class="k">Total Requests</div><div class="v"><?= $esc($requests['total']) ?></div></div>
      <div class="card"><div class="k">Success Rate</div><div class="v ok"><?= $esc($pct((float)$requests['success_rate'])) ?></div></div>
      <div class="card"><div class="k">Error Rate</div><div class="v ng"><?= $esc($pct((float)$requests['error_rate'])) ?></div></div>
      <div class="card"><div class="k">RPS</div><div class="v"><?= $esc($num((float)$throughput['rps'])) ?></div></div>
      <div class="card"><div class="k">TPS</div><div class="v"><?= $esc($num((float)$throughput['tps'])) ?></div></div>
      <div class="card"><div class="k">p95 (ms)</div><div class="v"><?= $esc($num((float)$latency['p95'])) ?></div></div>
    </div>
  </section>

  <section class="panel">
    <h2>Throughput</h2>
    <table>
      <tbody>
        <tr><th>RPS</th><td><?= $esc($num((float)$throughput['rps'])) ?> req/sec</td></tr>
        <tr><th>TPS</th><td><?= $esc($num((float)$throughput['tps'])) ?> tx/sec</td></tr>
        <tr><th>TPS / RPS Rate</th><td><?= $esc($pct((float)$throughput['tps_rps_rate'])) ?></td></tr>
        <?php if (array_key_exists('target_rps', $throughput)): ?>
          <tr><th>Target RPS</th><td><?= $esc($num((float)$throughput['target_rps'])) ?> req/sec</td></tr>
          <tr><th>RPS Achievement</th><td><?= $esc($pct((float)$throughput['rps_achievement_rate'])) ?></td></tr>
        <?php endif; ?>
        <?php if (array_key_exists('target_tps', $throughput)): ?>
          <tr><th>Target TPS</th><td><?= $esc($num((float)$throughput['target_tps'])) ?> tx/sec</td></tr>
          <tr><th>TPS Achievement</th><td><?= $esc($pct((float)$throughput['tps_achievement_rate'])) ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="panel">
    <h2>Latency</h2>
    <table>
      <tbody>
        <tr><th>min</th><td><?= $esc($num((float)$latency['min'])) ?> ms</td></tr>
        <tr><th>avg</th><td><?= $esc($num((float)$latency['avg'])) ?> ms</td></tr>
        <tr><th>p50</th><td><?= $esc($num((float)$latency['p50'])) ?> ms</td></tr>
        <tr><th>p95</th><td><?= $esc($num((float)$latency['p95'])) ?> ms</td></tr>
        <tr><th>p99</th><td><?= $esc($num((float)$latency['p99'])) ?> ms</td></tr>
        <tr><th>max</th><td><?= $esc($num((float)$latency['max'])) ?> ms</td></tr>
      </tbody>
    </table>
  </section>

  <section class="panel">
    <h2>Status Codes</h2>
    <table>
      <thead>
      <tr>
        <th>Code</th>
        <th>Count</th>
        <th>Rate</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($summary['status_codes'] as $code => $item): ?>
        <tr>
          <td><?= $esc($code) ?></td>
          <td><?= $esc($item['count']) ?></td>
          <td><?= $esc($pct((float)$item['rate'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="panel">
    <h2>Errors</h2>
    <?php if ($report['errors'] === []): ?>
      <p>No errors.</p>
    <?php else: ?>
      <table>
        <thead>
        <tr>
          <th>Request</th>
          <th>HTTP Code</th>
          <th>cURL errno</th>
          <th>Latency (ms)</th>
          <th>Error</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($report['errors'] as $error): ?>
          <tr>
            <td><?= $esc($error['request']) ?></td>
            <td><?= $esc($error['http_code']) ?></td>
            <td><?= $esc($error['error_no']) ?></td>
            <td><?= $esc($num((float)$error['latency_ms'])) ?></td>
            <td><?= $esc($error['error'] !== '' ? $error['error'] : '(no message)') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <div class="foot">
    Generated by eleload <?= $esc($report['meta']['version']) ?>
  </div>
</main>
</body>
</html>

