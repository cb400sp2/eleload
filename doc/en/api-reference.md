# API Reference (phpDocumentor)

> **Also available in:** [日本語](../ja/api-reference.md)

## Overview

`eleload` can generate API reference documents from PHPDoc comments in `src/`
using phpDocumentor.

## Configuration

- Config file: `phpdoc.dist.xml`
- Source path: `src/`
- Output directory: `docs/api/`

## Generate API Docs

Run from the repository root:

```bash
composer docs-api
```

The command executes `phpdoc --config=phpdoc.dist.xml`.

## Output and Publishing

- Generated docs are written to `docs/api/`
- Entry page: `docs/api/index.html`
- The same directory can be published via GitHub Pages when using the `docs/`
  folder as the Pages source

## Notes

- This repository tracks only a lightweight placeholder page in `docs/api/`
- Generated files can be regenerated locally at any time from source comments
