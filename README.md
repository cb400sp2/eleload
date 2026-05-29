# eleload

[![CI](https://github.com/cb400sp2/eleload/actions/workflows/ci.yml/badge.svg)](https://github.com/cb400sp2/eleload/actions/workflows/ci.yml)

**[日本語版 README はこちら](README.ja.md)**

`eleload` is a lightweight HTTP load testing CLI tool written in PHP.
It uses `curl_multi` for concurrent requests and outputs throughput and latency metrics.

> **Important**: Only run load tests against systems you own or have explicit permission to test.

## Features

- Zero-dependency CLI (`run` / `report` / `compare` / `scenario` / `help` / `version`)
- Concurrent HTTP execution via `curl_multi`
- Metrics: `requests` / `success` / `failed` / `RPS` / `TPS` / `error rate` / `latency (min/avg/p50/p95/p99/max)` / `status code breakdown`
- Rate control: `--rate` / `--target-rps` / `--target-tps` / `--ramp-up`
- CI thresholds: `--fail-on-p95` / `--fail-on-p99` / `--fail-on-error-rate` / `--fail-on-rps-below` / `--fail-on-tps-below`
- Duration mode: `--duration` / `--warmup`
- Success criteria: `--success-status` / `--expect-status` / `--expect-body-contains`
- Auth: `--bearer-token` / `--basic-user` / `--basic-password` / `--cookie`
- Redirect: `--follow-redirects` / `--no-follow-redirects`
- Timeouts: `--timeout` / `--connect-timeout`
- Reports: JSON / HTML / Markdown / CSV / Console
- Compare two runs: `compare before.json after.json --html=...`
- Scenario files: multi-step flows with variable extraction (JSON format)
- Output control: `--silent` / `--verbose` / `--debug`
- High-load safety guard with `--yes` / `--allow-high-load`

## Requirements

- PHP 8.2+
- `ext-curl`

## Install

### From source

```bash
composer install
chmod +x bin/eleload
```

### Global install via Composer

```bash
composer global require cb400sp2/eleload
eleload version
```

### PHAR

Download the pre-built `eleload.phar` from [GitHub Releases](https://github.com/cb400sp2/eleload/releases).

```bash
curl -L -o eleload.phar https://github.com/cb400sp2/eleload/releases/latest/download/eleload.phar
chmod +x eleload.phar
./eleload.phar version
```

## Quick Start

```bash
# Basic load test
./bin/eleload run https://example.com --requests=100 --concurrency=10

# Duration-based test with thresholds
./bin/eleload run https://example.com \
  --duration=60 --rate=100 --warmup=5 \
  --concurrency=50 \
  --fail-on-p95=500 --fail-on-error-rate=1

# POST with JSON body
./bin/eleload run https://example.com/api/items \
  --method=POST \
  --header="Content-Type: application/json" \
  --body='{"name":"test"}' \
  --requests=500 --concurrency=20

# Bearer token auth
./bin/eleload run https://example.com/api/items \
  --bearer-token=xxxxx \
  --requests=500 --concurrency=20

# Save reports
./bin/eleload run https://example.com \
  --requests=1000 --concurrency=50 \
  --name="smoke test" \
  --output-dir=reports

# Compare two runs
./bin/eleload compare reports/before.json reports/after.json \
  --html=reports/compare.html

# Regenerate HTML from JSON
./bin/eleload report reports/report.json --html=reports/report.html

# Scenario (multi-step)
./bin/eleload scenario examples/scenarios/login-then-fetch.json \
  --concurrency=10 --duration=60
```

## Development

```bash
composer test         # Run tests (97 tests)
composer analyse      # PHPStan level 8
composer cs-check     # PHP-CS-Fixer dry-run
composer cs-fix       # PHP-CS-Fixer auto-fix
php -d phar.readonly=0 bin/build-phar.php  # Build PHAR
```

## Exit Codes

| Code | Meaning |
| ---- | ------- |
| `0`  | Success — no threshold violations |
| `1`  | Failure — invalid options, threshold violation, or runtime error |
| `2`  | Reserved for unrecoverable engine errors |

## JSON Report Schema

All JSON reports include a `meta` block:

```json
{
  "meta": {
    "tool": "eleload",
    "version": "1.0.0",
    "schema_version": 1,
    "test_name": "smoke test"
  }
}
```

`schema_version` is incremented only on breaking layout changes.

## Metric Definitions

| Metric | Description |
| ------ | ----------- |
| `RPS`  | Total HTTP requests per second |
| `TPS`  | Successful transactions per second |
| `TPS/RPS Rate` | `TPS / RPS × 100` |
| `RPS Achievement` | `RPS / target_rps × 100` (when `--target-rps` is set) |
| `TPS Achievement` | `TPS / target_tps × 100` (when `--target-tps` is set) |

In single-URL mode, 1 request = 1 transaction.

## Documentation

- [doc/en/getting-started.md](doc/en/getting-started.md) — Installation and first steps
- [doc/en/cli-reference.md](doc/en/cli-reference.md) — All commands and options
- [doc/en/scenarios.md](doc/en/scenarios.md) — Multi-step scenario files
- [doc/en/reports.md](doc/en/reports.md) — Report formats
- [doc/en/thresholds.md](doc/en/thresholds.md) — CI threshold options
- [doc/en/security.md](doc/en/security.md) — Security best practices

## License

MIT
