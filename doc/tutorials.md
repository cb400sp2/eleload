# Tutorials and Recipes

This page provides practical, copy-paste-friendly recipes for common `eleload`
workflows.

## 1. Basic API Load Test

Run a simple fixed-request test and save JSON + HTML + Markdown reports.

```bash
./bin/eleload run https://api.example.com/v1/items \
  --requests=1000 \
  --concurrency=50 \
  --name="basic-api-load" \
  --output-dir=reports/basic
```

When the run finishes, check `reports/basic/` for timestamped report files.

## 2. Login -> Fetch Scenario

Execute a multi-step flow where one step logs in and a later step fetches data
using extracted variables.

```bash
./bin/eleload scenario examples/scenarios/login-then-fetch.json \
  --duration=60 \
  --concurrency=10 \
  --name="login-fetch-scenario" \
  --output-dir=reports/login-fetch
```

This pattern is useful for reproducing realistic authenticated API traffic.

## 3. Distributed Load with Local Agents

Use multiple local agent processes to scale a scenario run.

```bash
./bin/eleload scenario examples/scenarios/simple-get.json \
  --duration=120 \
  --concurrency=20 \
  --agents=4 \
  --name="distributed-local-agents" \
  --output-dir=reports/distributed
```

`--agents` runs worker processes on the same machine and merges results.

## 4. SLA Regression Workflow

### Step 1: Capture a baseline

```bash
./bin/eleload run https://api.example.com/v1/items \
  --duration=60 \
  --rate=80 \
  --concurrency=20 \
  --report-json=reports/baseline-report.json \
  --save-baseline=reports/baseline.json
```

### Step 2: Run candidate and compare against baseline

```bash
./bin/eleload run https://api.example.com/v1/items \
  --duration=60 \
  --rate=80 \
  --concurrency=20 \
  --report-json=reports/candidate-report.json \
  --baseline=reports/baseline.json \
  --fail-on-p95=300 \
  --fail-on-error-rate=1
```

### Step 3: Generate before/after diff report

```bash
./bin/eleload compare reports/baseline-report.json reports/candidate-report.json \
  --html=reports/compare.html \
  --md=reports/compare.md
```

Use this flow in CI to detect regressions in latency and reliability.
