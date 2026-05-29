# PHPUnit Migration Investigation and Roadmap

> Status: Investigation complete — recommendation: **migrate**
> Author: automated analysis (issue #73)

## 1. Current Test Infrastructure

### Custom runner (`tests/run.php`)

The project uses a hand-written test runner (93 LOC) that provides:

| Function | PHPUnit equivalent |
|----------|--------------------|
| `test(string $name, callable $fn)` | `#[Test]` method or `it()` (Pest) |
| `assertTrue(bool)` | `$this->assertTrue()` |
| `assertSame(mixed, mixed)` | `$this->assertSame()` |
| `assertContains(string, string)` | `$this->assertStringContainsString()` |
| `assertThrows(callable, class, string)` | `$this->expectException()` + `$this->expectExceptionMessageMatches()` |

Features **missing** compared to PHPUnit 11:

- No data providers (`#[DataProvider]`)
- No `setUp()` / `tearDown()` hooks
- No `#[BeforeClass]` / `#[AfterClass]`
- No JUnit XML output (required for CI test result integration)
- No code coverage support (required for issue #74)
- No test filtering (`--filter`, `--group`)
- No parallel execution
- No `$this->markTestSkipped()` / `$this->markTestIncomplete()`
- No mocking / stubbing support

### Test file inventory

| File | Tests | LOC | Notes |
|------|-------|-----|-------|
| `ArgvParserTest.php` | 41 | 592 | Most complex; heavy `assertThrows` usage |
| `ScenarioLoaderTest.php` | 18 | 244 | Recent addition |
| `StatisticsCalculatorTest.php` | 10 | 372 | Many inline arrays |
| `PercentileCalculatorTest.php` | 9 | 206 | Good candidate for `#[DataProvider]` |
| `RequestOptionsTest.php` | 8 | 140 | |
| `ReportWritersTest.php` | 5 | 180 | Uses temp files |
| `ScenarioRunnerTest.php` | 4 | 38 | |
| `CurlMultiRunnerRateLimitTest.php` | 4 | 76 | |
| `RunCommandSafetyTest.php` | 2 | 43 | |
| `ReportComparatorTest.php` | 2 | 57 | |
| `ReportCommandTest.php` | 2 | 93 | |
| `CompareCommandTest.php` | 2 | 118 | |
| `RunResultSpilloverTest.php` | 1 | 39 | |
| `RunCommandVerboseTest.php` | 1 | 23 | |
| `RunCommandSilentTest.php` | 1 | 26 | |
| `RunCommandDebugTest.php` | 1 | 32 | |
| `RunCommandCsvTest.php` | 1 | 29 | |
| **Total** | **112** | **2308** | |

Assertion call totals: `assertSame` × 267, `assertThrows` × 48, `assertTrue` × 89, `assertContains` × 48

## 2. PHPUnit 11 Benefits

### Developer experience

- **`#[DataProvider]`** — PercentileCalculatorTest has ~40 repetitive test cases that could be collapsed into one parameterised test with a data provider.
- **`setUp()` / `tearDown()`** — Several files create temp files inline. Moving cleanup to `tearDown()` prevents leaks on failure.
- **`$this->markTestSkipped()`** — Needed for YAML tests that require `ext-yaml` or `symfony/yaml` (currently untested on machines without them).
- **Better error messages** — PHPUnit provides type-annotated diffs; the custom runner shows raw `var_export()` output.

### CI integration

- **JUnit XML** — `--log-junit` output is consumed by GitHub Actions test reporter actions and tools like Codecov.
- **Code coverage** (needed for issue #74) — PHPUnit integrates natively with `Xdebug` and `PCOV` to produce Cobertura/HTML/Clover coverage reports. The custom runner has no coverage support at all.
- **Mutation testing** (needed for issue #76) — Infection PHP officially supports PHPUnit. Custom runner support requires additional adapter configuration.

### Future-proofing

- PHPUnit 11 supports PHP 8.1+ and will track new PHP releases.
- The custom runner is frozen at ~93 LOC and would require ongoing maintenance to add missing features.

## 3. Migration Cost Estimate

### Assertion mapping

| Current | PHPUnit 11 | Effort |
|---------|------------|--------|
| `assertTrue($x)` | `$this->assertTrue($x)` | Mechanical find-replace |
| `assertSame($a, $b)` | `$this->assertSame($a, $b)` | Mechanical find-replace |
| `assertContains($needle, $haystack)` | `$this->assertStringContainsString($needle, $haystack)` | Mechanical find-replace |
| `assertThrows(fn, Class, 'msg')` | `$this->expectException(Class)` + `$this->expectExceptionMessage('msg')` + call | Requires restructuring each test |
| `test('name', function() {...})` | `public function testName(): void {...}` | Requires class wrapping |

### Structural changes

Each `*Test.php` file needs to be converted from a flat script to a `class FooTest extends TestCase` class.
This is the most labour-intensive part, but it is mechanical.

Estimated effort per file: **10–30 minutes** for straightforward files;
**45–60 minutes** for `ArgvParserTest.php` (41 tests, 592 LOC) and `StatisticsCalculatorTest.php`.

**Total estimated effort: 6–10 hours** (one developer)

### Risk: `assertThrows` pattern

The current `assertThrows(callable, class, message)` pattern wraps an entire anonymous function.
In PHPUnit, the equivalent is:
```php
$this->expectException(SomeException::class);
$this->expectExceptionMessage('expected message');
$object->methodThatThrows();
```
This requires splitting each `assertThrows` call into 3 lines and removing the closure wrapper.
There are 48 `assertThrows` calls across the codebase.

## 4. Recommended Approach

### Option A: Migrate to PHPUnit 11 (recommended)

1. `composer require --dev phpunit/phpunit:^11` (no production impact)
2. Create `phpunit.xml` configuration
3. Convert each `*Test.php` file to a `TestCase` subclass
4. Replace custom assertions with PHPUnit equivalents
5. Remove `tests/run.php` once all tests pass under PHPUnit
6. Update `composer.json` scripts: `"test": "vendor/bin/phpunit"`
7. Update CI to use `--log-junit` for test result reporting

### Option B: Add PHPUnit alongside the custom runner (transitional)

Run both in parallel during migration, removing the custom runner once all files are ported.
Lower risk but more overhead.

### Option C: Migrate to Pest (alternative)

[Pest](https://pestphp.com/) wraps PHPUnit and uses a function-based API very similar to the current `test()` DSL. Migration from the current runner to Pest would be lower friction than raw PHPUnit:

```php
// Current
test('it adds numbers', function () {
    assertSame(4, add(2, 2));
});

// Pest (minimal change)
it('adds numbers', function () {
    expect(add(2, 2))->toBe(4);
});
```

Pest still uses PHPUnit under the hood, so coverage and JUnit XML are available.

**Recommendation: Option A (PHPUnit 11 direct migration)** to avoid an extra dependency layer.
Pest is a good choice if the team prefers the functional style, but raw PHPUnit is more standard.

## 5. Migration Roadmap

| Phase | Scope | Effort | Prerequisite |
|-------|-------|--------|--------------|
| **0** | Install PHPUnit 11; create `phpunit.xml`; prove one test file works | 1–2h | — |
| **1** | Migrate `PercentileCalculatorTest.php` (9 tests, good `#[DataProvider]` candidate) | 1h | Phase 0 |
| **2** | Migrate remaining simple files (10 files, 1–4 tests each) | 2–3h | Phase 1 |
| **3** | Migrate `ArgvParserTest.php` (41 tests) | 1–2h | Phase 2 |
| **4** | Migrate `StatisticsCalculatorTest.php` (10 tests, 372 LOC) | 1h | Phase 2 |
| **5** | Remove `tests/run.php`; update CI; add `--log-junit` output | 0.5h | Phases 1–4 |
| **6** | Enable code coverage (PCOV/Xdebug) — feeds into issue #74 | 1h | Phase 5 |

Total estimated wall-clock: **7–11 hours**

## 6. `phpunit.xml` Starter Config

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         cacheDirectory=".phpunit.cache"
         colors="true">
  <testsuites>
    <testsuite name="Unit">
      <directory>tests</directory>
    </testsuite>
  </testsuites>
  <source>
    <include>
      <directory suffix=".php">src</directory>
    </include>
  </source>
</phpunit>
```

## 7. Conclusion

**Recommendation: Migrate to PHPUnit 11.**

The current custom runner served its purpose but is now a ceiling on tooling:
code coverage (#74), mutation testing (#76), and proper CI reporting all require PHPUnit.
The migration is mechanical, low-risk, and estimated at under 10 hours.
Begin with Phase 0–1 to gain confidence before converting the larger files.
