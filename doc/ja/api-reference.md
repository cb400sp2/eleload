# API リファレンス生成 (phpDocumentor)

> **Also available in:** [English](../en/api-reference.md)

## 概要

`eleload` は `src/` 配下の PHPDoc コメントから、phpDocumentor を使って
API リファレンスを生成できます。

## 設定

- 設定ファイル: `phpdoc.dist.xml`
- ソースパス: `src/`
- 出力先: `docs/api/`

## 生成手順

リポジトリルートで以下を実行します。

```bash
composer docs-api
```

このコマンドは `phpdoc --config=phpdoc.dist.xml` を実行します。

## 出力先と公開

- 生成物は `docs/api/` に書き出されます
- エントリページは `docs/api/index.html` です
- GitHub Pages で `docs/` を公開元にした場合、そのまま公開できます

## 補足

- このリポジトリには `docs/api/` のプレースホルダーページのみを保持します
- 生成ファイルはソースコメントからいつでも再生成できます