# eleload

`eleload` is a lightweight HTTP load testing CLI tool written in PHP.

It uses `curl_multi` for concurrent requests and prints throughput and latency metrics.

## Features

- `run/help/version` commands without Symfony Console or Laravel
- Concurrent HTTP execution with `curl_multi`
- Metrics:
  - requests / success / failed
  - success rate / error rate
  - RPS / TPS / TPS-RPS rate
  - latency (`min/avg/p50/p95/p99/max`)
  - status code count and rate
- Optional target metrics:
  - `--target-rps`
  - `--target-tps`
- Duration and CI threshold options:
  - `--duration`
  - `--warmup`
  - `--fail-on-p95`
  - `--fail-on-p99`
  - `--fail-on-error-rate`
  - `--fail-on-rps-below`
  - `--fail-on-tps-below`
- Success criteria customization:
  - `--success-status`
- Auth options:
  - `--bearer-token`
  - `--basic-user`
  - `--basic-password`
- Report output:
  - `--report-json`
  - `--report-html`
  - `--report-md`
  - `--output-dir`
  - `--name`
  - `report` command to regenerate HTML from saved JSON
- UTF-8 report content, including multibyte text in JSON and HTML reports

## Requirements

- PHP 8.2+
- `ext-curl`

## Install

```bash
composer install
chmod +x bin/eleload
```

## Test

```bash
composer test
```

## Usage

```bash
./bin/eleload help
./bin/eleload version
```

Regenerate HTML from an existing JSON report:

```bash
./bin/eleload report reports/report.json --html=reports/regenerated.html
```

Run a simple load test:

```bash
./bin/eleload run https://example.com --requests=100 --concurrency=10
```

POST example:

```bash
./bin/eleload run https://example.com/api/items \
  --method=POST \
  --header="Content-Type: application/json" \
  --body='{"name":"test"}' \
  --requests=500 \
  --concurrency=20
```

Custom success status example:

```bash
./bin/eleload run https://example.com/api/items \
  --method=POST \
  --success-status=200,201,204 \
  --requests=500 \
  --concurrency=20
```

Bearer token example:

```bash
./bin/eleload run https://example.com/api/items \
  --bearer-token=xxxxx \
  --requests=500 \
  --concurrency=20
```

Basic auth example:

```bash
./bin/eleload run https://example.com/api/items \
  --basic-user=user \
  --basic-password=pass \
  --requests=500 \
  --concurrency=20
```

Output JSON + HTML reports:

```bash
./bin/eleload run https://example.com \
  --requests=1000 \
  --concurrency=50 \
  --target-rps=100 \
  --target-tps=95 \
  --report-json=reports/report.json \
  --report-html=reports/report.html \
  --report-md=reports/report.md
```

Output timestamped reports to a directory:

```bash
./bin/eleload run https://example.com \
  --requests=1000 \
  --concurrency=50 \
  --name="top page smoke load" \
  --output-dir=reports
```

Run for a fixed duration and fail on thresholds:

```bash
./bin/eleload run https://example.com \
  --duration=60 \
  --warmup=5 \
  --concurrency=50 \
  --fail-on-p95=500 \
  --fail-on-error-rate=1
```

## Metric Definitions

- `RPS`: total HTTP requests per second
- `TPS`: successful transactions per second
- Single URL mode treats `1 request = 1 transaction`
- `TPS / RPS Rate`: `TPS / RPS * 100`
- `RPS Achievement`: `RPS / target_rps * 100` (only when target set)
- `TPS Achievement`: `TPS / target_tps * 100` (only when target set)
