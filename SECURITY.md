# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.x     | ✅ Yes    |

## Reporting a Vulnerability

Please **do not** open a public GitHub issue for security vulnerabilities.

Report vulnerabilities by emailing the maintainer or opening a [GitHub Security Advisory](https://github.com/cb400sp2/eleload/security/advisories/new).

Include:
- A description of the vulnerability and its potential impact
- Steps to reproduce or a proof-of-concept
- Affected versions

We aim to respond within 5 business days.

## Security Considerations

### Responsible Use

eleload is a load testing tool. **Only test systems you own or have explicit written permission to test.** Unauthorized load testing may violate laws and terms of service.

### Token and Credential Safety

- Prefer `--bearer-token-env`, `--basic-password-env`, and `--cookie-env` over inline `--bearer-token` / `--basic-password` / `--cookie` to avoid credentials appearing in shell history and process listings (`/proc/<pid>/cmdline`).
- Credentials are never written to report files (JSON, HTML, Markdown, CSV).

### Private Network Protection

Use `--block-private-networks` to prevent accidental requests to `localhost`, loopback, or RFC-1918 private addresses. This is useful in CI environments and when running untrusted scenario files.

### TLS

eleload enforces:
- `CURLOPT_SSL_VERIFYPEER` and `CURLOPT_SSL_VERIFYHOST` (peer certificate verification is always on)
- Minimum TLS version 1.2 (`CURL_SSLVERSION_TLSv1_2`)

### Supply Chain

- Dependencies are audited via `composer audit` in CI on every push.
- [Dependabot](https://github.com/cb400sp2/eleload/network/updates) is enabled for weekly Composer dependency updates.
- PHAR releases include a SHA-256 checksum file (`.sha256`) for integrity verification.
