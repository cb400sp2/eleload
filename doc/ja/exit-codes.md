# 終了コード

> **Also available in:** [English](../en/exit-codes.md)

`eleload` は標準的な UNIX 終了コード規約に従います。

| コード | 意味 |
|-------|------|
| `0` | 成功 — しきい値違反なしでテスト完了 |
| `1` | 失敗 — 無効なオプション、しきい値違反、またはランタイムエラー |
| `2` | 予約済み — 回復不能なエンジンエラー（現在未使用） |

## 終了コード 1 が返される場合

- 必須の引数が不足または無効
- URL のスキーム・形式・プライベートネットワークの検証に失敗
- しきい値フラグ条件が違反（`--fail-on-p95`・`--fail-on-error-rate` など）
- シナリオファイルが見つからない、解析できない、または拡張子が未対応
- curl ランタイムエラーによりテストを完了できない
- `SIGINT` / `SIGTERM` により実行が中断され、partial report で終了した
- `memory_limit` 付近のメモリ圧迫保護により早期終了した
- 予期しない例外が発生

## シェルでの終了コード確認

```bash
eleload run https://example.com --requests=100 --fail-on-error-rate=1
if [ $? -ne 0 ]; then
  echo "負荷試験が失敗しました"
  exit 1
fi
```

## GitHub Actions

GitHub Actions はステップが非ゼロの終了コードを返すと自動的にそのステップを失敗させるため、
通常は追加の `if` チェックは不要です。

```yaml
- name: 負荷試験
  run: eleload run https://api.example.com --duration=30 --fail-on-p95=500
```
