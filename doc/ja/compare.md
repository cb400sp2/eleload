# 2 つの実行結果の比較

> **Also available in:** [English](../en/compare.md)

`compare` コマンドは、2 つの保存済み JSON レポート間のメトリクスの劣化と改善をハイライトします。

## 使い方

```bash
eleload compare reports/before.json reports/after.json \
  --html=reports/compare.html \
  --md=reports/compare.md
```

両方の入力ファイルは有効な eleload JSON レポートである必要があります（[json-schema.md](json-schema.md) を参照）。

## オプション

| オプション | 説明 |
|-----------|------|
| `--html=FILE` | HTML 比較レポートを FILE に書き込み |
| `--md=FILE` | Markdown 比較レポートを FILE に書き込み |

`--html` または `--md` のいずれか（または両方）を指定する必要があります。

## 出力の構成

比較レポートは各メトリクスの絶対値と相対的な変化を表示します:

| メトリクス | Before | After | 変化 |
|-----------|--------|-------|------|
| Requests | 1000 | 1000 | — |
| Success | 998 | 995 | -3 (-0.3%) |
| RPS | 41.5 | 38.2 | -3.3 (-7.9%) ⚠ |
| p95 latency | 480ms | 530ms | +50ms (+10.4%) ⚠ |
| p99 latency | 510ms | 610ms | +100ms (+19.6%) ⚠ |
| Error rate | 0.20% | 0.50% | +0.30% ⚠ |

劣化は HTML・Markdown 形式の両方でハイライト（⚠）されます。

## 典型的な CI ワークフロー

```bash
# ステップ 1: 変更前に実行
eleload run https://api.example.com/health \
  --requests=500 --concurrency=10 \
  --output-dir=reports/ --name="before"

# ステップ 2: 変更をデプロイして再実行
eleload run https://api.example.com/health \
  --requests=500 --concurrency=10 \
  --output-dir=reports/ --name="after"

# ステップ 3: 比較
eleload compare reports/before.json reports/after.json \
  --html=reports/compare.html

# 任意: レポートを CI アーティファクトとして添付
```

## 関連

- [reports.md](reports.md) — 単一実行レポートの生成
- [thresholds.md](thresholds.md) — しきい値違反による自動 CI 失敗
