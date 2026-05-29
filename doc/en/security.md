# Security

> **Also available in:** [日本語](../ja/security.md)

## Responsible Use

> **Only run load tests against systems you own or have explicit written permission to test.**

Unauthorized load testing may violate applicable laws and terms of service.
eleload is a testing tool, not an attack tool.

## Passing Credentials Safely

Never put credentials directly on the command line — they appear in shell history
(`~/.bash_history`, `~/.zsh_history`) and in the process list (`/proc/<pid>/cmdline`).

Use the `*-env` variants instead:

```bash
# Set environment variables
export API_TOKEN="my-secret-token"
export DB_PASS="hunter2"

# Reference them by name — never appear in shell history
eleload run https://api.example.com/items \
  --bearer-token-env=API_TOKEN \
  --basic-user=myuser \
  --basic-password-env=DB_PASS
```

| Credential type | Unsafe | Safe |
|----------------|--------|------|
| Bearer token | `--bearer-token=TOKEN` | `--bearer-token-env=VAR` |
| Basic username | `--basic-user=USER` (OK) | — |
| Basic password | `--basic-password=PASS` | `--basic-password-env=VAR` |
| Cookie | `--cookie=TEXT` | `--cookie-env=VAR` |

Credentials are **never** written to report files (JSON, HTML, Markdown, CSV).

## Blocking Private Networks

Use `--block-private-networks` to prevent requests to `localhost`, loopback,
or RFC-1918 private addresses (`10.x.x.x`, `172.16.x.x–172.31.x.x`, `192.168.x.x`):

```bash
eleload run https://api.example.com --block-private-networks --requests=100
```

This is recommended:

- In CI environments, to prevent accidental testing of internal services
- When running scenario files from untrusted sources

## TLS Enforcement

eleload always enables:

- `CURLOPT_SSL_VERIFYPEER` — peer certificate verification (cannot be disabled)
- `CURLOPT_SSL_VERIFYHOST` — hostname verification
- Minimum TLS version 1.2 (`CURL_SSLVERSION_TLSv1_2`)

HTTP URLs are supported for testing HTTP-only endpoints, but HTTPS is strongly recommended.

## URL Validation

Only `http://` and `https://` schemes are accepted.
Schemes such as `ftp://`, `file://`, or `php://` are rejected with an error.

## Supply Chain

- All dependencies are audited with `composer audit` on every CI push.
- [Dependabot](https://github.com/cb400sp2/eleload/network/updates) is enabled for weekly Composer updates.
- PHAR releases include a SHA-256 checksum file (`.sha256`) for integrity verification.

## Reporting Vulnerabilities

See [SECURITY.md](../../SECURITY.md) for the vulnerability reporting policy.
Do **not** open public GitHub issues for security vulnerabilities.
