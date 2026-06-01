# CLI Reference

> **Also available in:** [日本語](../ja/cli-reference.md)

## Commands

| Command | Description |
|---------|-------------|
| `run <url> [options]` | Run a load test against a single URL |
| `scenario <file> [options]` | Run a multi-step scenario file |
| `report <report.json> [options]` | Regenerate reports from a saved JSON file |
| `compare <before.json> <after.json> [options]` | Compare two test results |
| `help` | Show usage |
| `version` | Show version string |

---

## `run` — Single-URL Load Test

```sh
eleload run <url> [options]
```

### Request Options

| Option | Default | Description |
|--------|---------|-------------|
| `--requests=N` | 100 | Total number of HTTP requests |
| `--concurrency=N` | 10 | Number of concurrent connections |
| `--method=METHOD` | `GET` | HTTP method (GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS) |
| `--header="K: V"` | — | Add a request header (repeatable) |
| `--body="..."` | — | Request body |
| `--timeout=N` | 10 | Per-request timeout in seconds |
| `--connect-timeout=N` | min(timeout, 5) | Connection timeout in seconds |
| `--follow-redirects` | off | Follow HTTP redirects |
| `--no-follow-redirects` | — | Disable redirect following (default) |

### Authentication Options

| Option | Description |
|--------|-------------|
| `--bearer-token=TOKEN` | Set `Authorization: Bearer TOKEN` |
| `--bearer-token-env=VAR` | Read bearer token from environment variable `VAR` |
| `--basic-user=USER` | Basic auth username |
| `--basic-user-env=VAR` | Read basic auth username from environment variable |
| `--basic-password=PASS` | Basic auth password |
| `--basic-password-env=VAR` | Read basic auth password from environment variable |
| `--cookie=TEXT` | Set `Cookie` header value |
| `--cookie-env=VAR` | Read cookie value from environment variable |

> Prefer `*-env` variants to keep credentials out of shell history. See [security.md](security.md).

### Rate and Duration Options

| Option | Description |
|--------|-------------|
| `--duration=N` | Run for N seconds instead of a fixed request count |
| `--warmup=N` | Exclude first N seconds from reported metrics |
| `--rate=N` | Fixed request rate (requests/s); requires `--duration` |
| `--target-rps=N` | Target RPS for achievement metric |
| `--target-tps=N` | Target TPS for achievement metric |
| `--ramp-up=N` | Linearly increase concurrency over N seconds (0 = disabled) |

### Threshold / Failure Options

| Option | Description |
|--------|-------------|
| `--fail-on-p95=MS` | Fail if p95 latency exceeds MS milliseconds |
| `--fail-on-p99=MS` | Fail if p99 latency exceeds MS milliseconds |
| `--fail-on-error-rate=PCT` | Fail if error rate exceeds PCT percent |
| `--fail-on-rps-below=N` | Fail if RPS is below N |
| `--fail-on-tps-below=N` | Fail if TPS is below N |

### Success Criteria Options

| Option | Description |
|--------|-------------|
| `--success-status=LIST` | Comma-separated list of success HTTP status codes (e.g. `200,201,204`) |
| `--expect-status=LIST` | Comma-separated list of expected status codes (treated as failure if not matched) |
| `--expect-body-contains=TEXT` | Validate that response body contains TEXT |

### Output and Report Options

| Option | Description |
|--------|-------------|
| `--name=TEXT` | Test name shown in reports |
| `--output-dir=DIR` | Write timestamped JSON, HTML, and Markdown reports to DIR |
| `--report-json=FILE` | Write JSON report to FILE |
| `--report-html=FILE` | Write HTML report to FILE |
| `--report-md=FILE` | Write Markdown report to FILE |
| `--report-csv=FILE` | Write CSV report to FILE |

### Misc Options

| Option | Description |
|--------|-------------|
| `--silent` | Suppress normal run output |
| `--verbose` | Show richer error and slowest-request details |
| `--debug` | Print parsed options and execution plan before running |
| `--yes` | Skip high-load confirmation prompt |
| `--allow-high-load` | Explicitly allow high-load settings (≥1000 concurrency) |
| `--block-private-networks` | Reject requests to `localhost`, loopback, or RFC-1918 private addresses |
| `--memory-buffer-size=N` | Max in-memory results before spilling to disk (default: 10000) |

### Graceful Shutdown and Partial Reports

- On `SIGINT`/`SIGTERM`, `run` stops dispatching new requests, waits for in-flight requests to finish, and then exits gracefully.
- During execution, memory usage is periodically monitored via `memory_get_peak_usage(true)`.
- When peak usage approaches `memory_limit` (about 90%), `run` stops early to avoid fatal OOM and finalizes a partial report.
- Partial runs set `meta.partial=true` and include `meta.termination_reason` in JSON output.

---

## `scenario` — Multi-Step Scenario

```sh
eleload scenario <scenario-file> [options]
```

`<scenario-file>` must be a `.json`, `.yaml`, or `.yml` file. See [scenarios.md](scenarios.md).

| Option | Default | Description |
|--------|---------|-------------|
| `--concurrency=N` | 10 | Concurrent virtual users |
| `--duration=N` | — | Run for N seconds |
| `--iterations=N` | 100 | Scenario iterations (when `--duration` is not set) |
| `--warmup=N` | 0 | Exclude initial N seconds from metrics |
| `--name=TEXT` | (from file) | Override scenario name in reports |
| `--output-dir=DIR` | — | Write timestamped JSON report to DIR |
| `--report-json=FILE` | — | Write JSON summary report to FILE |
| `--silent` | — | Suppress output |
| `--verbose` | — | Show failed step details |
| `--debug` | — | Print parsed options and scenario definition |
| `--yes` | — | Skip high-load confirmation |
| `--allow-high-load` | — | Explicitly allow high-load settings |

---

## `report` — Regenerate Reports

```sh
eleload report <report.json> [options]
```

Re-render a saved JSON report into other formats.

| Option | Description |
|--------|-------------|
| `--html=FILE` | Output HTML report path |

---

## `compare` — Compare Two Runs

```sh
eleload compare <before.json> <after.json> [options]
```

Compare metrics between two saved JSON reports and highlight regressions.

| Option | Description |
|--------|-------------|
| `--html=FILE` | Output HTML comparison report path |
| `--md=FILE` | Output Markdown comparison report path |

See [compare.md](compare.md) for full details.

---

## `help` and `version`

```bash
eleload help       # Print full usage
eleload version    # Print version string (e.g. "eleload 1.0.0")
```
