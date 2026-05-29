# Getting Started

> **Also available in:** [日本語](../ja/getting-started.md)

## Requirements

- PHP 8.2 or higher
- `ext-curl` extension (usually bundled with PHP)

## Installation

### From source

Clone the repository and install dependencies:

```bash
git clone https://github.com/cb400sp2/eleload.git
cd eleload
composer install
chmod +x bin/eleload
```

### Global install via Composer

```bash
composer global require cb400sp2/eleload
eleload version
```

Make sure `~/.composer/vendor/bin` is in your `$PATH`.

### PHAR

Download the pre-built binary from [GitHub Releases](https://github.com/cb400sp2/eleload/releases):

```bash
curl -L -o eleload.phar https://github.com/cb400sp2/eleload/releases/latest/download/eleload.phar
# Verify integrity (optional but recommended)
curl -L -o eleload.phar.sha256 https://github.com/cb400sp2/eleload/releases/latest/download/eleload.phar.sha256
shasum -a 256 -c eleload.phar.sha256
chmod +x eleload.phar
./eleload.phar version
```

## Your First Load Test

Run 100 GET requests with 10 concurrent connections:

```bash
eleload run https://example.com --requests=100 --concurrency=10
```

Example output:

```text
Requests:     100
Success:      100
Failed:       0
Duration:     2.41s
RPS:          41.5
TPS:          41.5
Error rate:   0.00%
Latency min:  22ms
Latency avg:  240ms
Latency p50:  235ms
Latency p95:  480ms
Latency p99:  510ms
Latency max:  540ms
```

## Duration-based Test

Instead of a fixed request count, run for a set duration (seconds):

```bash
eleload run https://example.com --duration=60 --concurrency=20
```

Add `--warmup=5` to exclude the first 5 seconds from metrics (useful to skip JIT warm-up).

## CI Integration

Use threshold flags to fail the build when latency or error rates exceed targets:

```bash
eleload run https://api.example.com/health \
  --duration=30 --concurrency=10 \
  --fail-on-p95=500 \
  --fail-on-error-rate=1
```

See [thresholds.md](thresholds.md) for all threshold options.

## Saving Reports

Use `--output-dir` to save timestamped JSON, HTML, and Markdown reports:

```bash
eleload run https://example.com \
  --requests=500 --concurrency=20 \
  --name="smoke test" \
  --output-dir=reports/
```

See [reports.md](reports.md) for all report format options.

## Next Steps

- [CLI Reference](cli-reference.md) — full options for every command
- [Scenarios](scenarios.md) — multi-step flows with variable extraction
- [Thresholds](thresholds.md) — CI integration and failure conditions
- [Security](security.md) — passing credentials safely
