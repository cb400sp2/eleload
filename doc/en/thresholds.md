# CI Thresholds

> **Also available in:** [日本語](../ja/thresholds.md)

Use threshold flags to make `eleload run` exit with code `1` when performance targets are not met.
This allows you to fail a CI pipeline automatically.

## Available Flags

| Flag | Description |
|------|-------------|
| `--fail-on-p95=MS` | Fail if p95 latency exceeds MS milliseconds |
| `--fail-on-p99=MS` | Fail if p99 latency exceeds MS milliseconds |
| `--fail-on-error-rate=PCT` | Fail if error rate exceeds PCT percent (e.g. `1` for 1%) |
| `--fail-on-rps-below=N` | Fail if measured RPS is below N |
| `--fail-on-tps-below=N` | Fail if measured TPS is below N |

Multiple threshold flags can be combined; the test fails if **any** condition is violated.

## Examples

### Latency budget

```bash
eleload run https://api.example.com/orders \
  --duration=60 --concurrency=50 \
  --fail-on-p95=500 \
  --fail-on-p99=1000
```

### Error rate guard

```bash
eleload run https://api.example.com/health \
  --requests=200 --concurrency=10 \
  --fail-on-error-rate=0.5
```

### Throughput requirement

```bash
eleload run https://api.example.com/search \
  --duration=30 --concurrency=20 \
  --fail-on-rps-below=100 \
  --fail-on-tps-below=95
```

## GitHub Actions Integration

```yaml
- name: Load test
  run: |
    eleload run ${{ env.API_URL }}/health \
      --duration=30 --concurrency=10 \
      --fail-on-p95=500 --fail-on-error-rate=1 \
      --output-dir=reports/
  env:
    API_URL: https://api.staging.example.com

- name: Upload reports
  uses: actions/upload-artifact@v4
  if: always()
  with:
    name: load-test-reports
    path: reports/
```

## Exit Codes

See [exit-codes.md](exit-codes.md) for the full list.

A non-zero exit code is returned when:

1. Invalid options are provided (exit `1`)
2. Any threshold condition is violated (exit `1`)
3. A runtime error occurs (exit `1`)

## Warmup

Use `--warmup=N` to exclude the first N seconds from threshold evaluation.
This is useful when the server needs JIT warm-up before reaching steady state:

```bash
eleload run https://api.example.com \
  --duration=90 --warmup=30 --concurrency=20 \
  --fail-on-p95=300
```
