# セキュリティ

> **Also available in:** [English](../en/security.md)

## 責任ある使用

> **自身が管理しているシステム、または明示的な書面による許可を得たシステムに対してのみ負荷試験を実行してください。**

許可なく負荷試験を行うことは、適用法および利用規約に違反する可能性があります。
eleload はテストツールであり、攻撃ツールではありません。

## 認証情報の安全な渡し方

コマンドラインに認証情報を直接記述しないでください。シェル履歴（`~/.bash_history`・`~/.zsh_history`）
およびプロセスリスト（`/proc/<pid>/cmdline`）に残ります。

代わりに `*-env` 形式を使用してください:

```bash
# 環境変数を設定
export API_TOKEN="my-secret-token"
export DB_PASS="hunter2"

# 名前で参照 — シェル履歴に残らない
eleload run https://api.example.com/items \
  --bearer-token-env=API_TOKEN \
  --basic-user=myuser \
  --basic-password-env=DB_PASS
```

| 認証情報の種類 | 非推奨 | 推奨 |
|--------------|--------|------|
| Bearer トークン | `--bearer-token=TOKEN` | `--bearer-token-env=VAR` |
| Basic ユーザー名 | `--basic-user=USER`（問題なし） | — |
| Basic パスワード | `--basic-password=PASS` | `--basic-password-env=VAR` |
| Cookie | `--cookie=TEXT` | `--cookie-env=VAR` |

認証情報はレポートファイル（JSON・HTML・Markdown・CSV）に**書き込まれません**。

## プライベートネットワークのブロック

`--block-private-networks` を使用して、`localhost`・ループバック・RFC-1918 プライベートアドレス
（`10.x.x.x`・`172.16.x.x〜172.31.x.x`・`192.168.x.x`）へのリクエストを防止します:

```bash
eleload run https://api.example.com --block-private-networks --requests=100
```

推奨される場面:

- CI 環境で内部サービスへの意図しないテストを防ぐ
- 信頼できないソースからのシナリオファイルを実行する場合

## TLS の強制

eleload は常に以下を有効にします:

- `CURLOPT_SSL_VERIFYPEER` — ピア証明書の検証（無効化不可）
- `CURLOPT_SSL_VERIFYHOST` — ホスト名の検証
- 最小 TLS バージョン 1.2（`CURL_SSLVERSION_TLSv1_2`）

HTTP-only エンドポイントのテストには HTTP URL もサポートされていますが、HTTPS を強く推奨します。

## URL バリデーション

受け入れられるのは `http://` と `https://` スキームのみです。
`ftp://`・`file://`・`php://` などのスキームはエラーで拒否されます。

## サプライチェーン

- すべての依存関係は CI の毎回のプッシュで `composer audit` により監査されます。
- 週次の Composer 更新のために [Dependabot](https://github.com/cb400sp2/eleload/network/updates) が有効です。
- PHAR リリースには整合性検証のための SHA-256 チェックサムファイル（`.sha256`）が含まれます。

## 脆弱性の報告

脆弱性報告ポリシーについては [SECURITY.md](../../SECURITY.md) を参照してください。
セキュリティの脆弱性について GitHub のパブリック Issue を**開かないでください**。
