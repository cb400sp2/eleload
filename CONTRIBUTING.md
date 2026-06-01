# Contributing to eleload

Thank you for your interest in contributing!
Please follow these guidelines to keep the project healthy.

## Code of Conduct

This project follows [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md). By participating,
you agree to uphold this code.

## Requirements

- PHP 8.2 or higher
- `ext-curl` enabled
- Composer

## Setting up

```bash
git clone https://github.com/cb400sp2/eleload.git
cd eleload
composer install
```

## Running the test suite

```bash
composer test
```

All tests must pass before submitting a pull request.

## Code coverage

We measure line coverage using [php-code-coverage](https://github.com/sebastianbergmann/php-code-coverage)
with PCOV or Xdebug.

### Minimum required line coverage: 60%

To measure coverage locally, install PCOV (or enable Xdebug in `coverage` mode)
and run:

```bash
composer coverage
```

This writes:

- `build/coverage/clover.xml` — machine-readable Clover report (uploaded to Codecov in CI)
- `build/coverage/html/` — human-readable HTML report

Coverage results are automatically uploaded to [Codecov](https://codecov.io/gh/cb400sp2/eleload)
for every push to `main` and every pull request.

Pull requests that reduce line coverage below **60%** will fail CI.
Aim to write tests for every new code path you introduce.

## Static analysis

```bash
composer analyse
```

We run PHPStan at level 8. All reported errors must be resolved.

## Code style

```bash
# Check
composer cs-check

# Fix automatically
composer cs-fix
```

We follow PSR-12 with the additional rules configured in `.php-cs-fixer.php`.

## Submitting a pull request

1. Fork the repository and create a feature branch.
2. Write tests for new behaviour.
3. Ensure `composer test`, `composer analyse`, and `composer cs-check` all pass.
4. Verify coverage does not drop below 60% (`composer coverage`).
5. Open a pull request against `main` and describe what you changed and why.

## Security

If you discover a security vulnerability, please follow the responsible disclosure
process described in [SECURITY.md](SECURITY.md).
