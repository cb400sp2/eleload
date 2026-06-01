# Migration Guide

This guide defines how `eleload` handles breaking changes between major
versions.

## Version-by-Version Breaking Changes

| Source major | Target major | Status | Breaking changes |
| ------------ | ------------ | ------ | ---------------- |
| 1.x | 2.x | Planned | No published breaking changes yet |

### 1.x -> 2.x (planned)

There are currently no announced breaking changes for 2.x. When a breaking
change is approved, this section will include:

- What changed
- Why it changed
- Exact migration steps

## Option Mapping (Old -> New)

| Old option | New option | Availability | Removal target | Notes |
| ---------- | ---------- | ------------ | -------------- | ----- |
| No renamed options in 1.x | No change | 1.x | - | Existing options remain compatible in the current major line |

For future major releases, every renamed or removed option must be listed in
this table before release.

## Deprecation Policy

A feature or option scheduled for removal in major version `N+1` must be marked
as deprecated in major version `N`.

Policy details:

1. Add deprecation notice at least one major version before removal.
2. Keep deprecated behavior functional throughout the previous major line.
3. Include replacement guidance in warnings and release notes.
4. Remove deprecated behavior only at the next major release boundary.

## Migration Checklist

Before upgrading to a new major version:

1. Read this file from top to bottom.
2. Apply all option mapping changes.
3. Run smoke tests with your usual thresholds.
4. Compare old/new reports with `eleload compare`.
