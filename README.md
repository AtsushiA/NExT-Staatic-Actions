# NExT Staatic Actions

[![CI](https://github.com/AtsushiA/NExT-Staatic-Actions/actions/workflows/ci.yml/badge.svg)](https://github.com/AtsushiA/NExT-Staatic-Actions/actions/workflows/ci.yml)

*(English README: [README.en.md](README.en.md))*

[Staatic](https://staatic.com/wordpress)（静的サイトジェネレーター）の公開（デプロイ）が完了した際に、
メール通知・Cloudflareキャッシュパージ・Webhook通知を自動実行する WordPress プラグインです。
また、指定した日時・毎日・毎週指定曜日のスケジュールで公開そのものを自動実行することもできます。

## 動作要件

- WordPress 5.0 以上
- PHP 7.4 以上
- [Staatic](https://ja.wordpress.org/plugins/staatic/) プラグインが有効化されていること

## インストール

1. このリポジトリを `wp-content/plugins/NExT-Staatic-Actions` に配置する
2. WordPress管理画面のプラグイン一覧から「NExT Staatic Actions」を有効化する
3. 管理メニューの Staatic > Actions から各種通知設定を行う

## 機能

Staatic の公開が**成功**または**失敗**した際に、以下を個別に有効・無効化できます。

| 機能 | 内容 |
|---|---|
| メール通知 | 複数の宛先へ、件名・本文をテンプレートで指定して送信 |
| Cloudflare キャッシュパージ | Zone ID / API トークンを使い Purge Cache API を呼び出す |
| Webhook 通知 | 任意のURLへHTTPリクエスト（メソッド・ヘッダー・ボディを設定可能） |

メール通知・Webhook通知には「保存済みの設定でテスト送信」ボタンがあり、実際に Staatic の公開を待たずに
その場で送信結果（成功/失敗とステータス）を確認できます（有効/無効の設定に関わらず動作します）。

Cloudflare キャッシュパージには、Zone ID・APIトークン保存後にその場で確認できる2つのボタンがあります。

- **接続確認** — Cloudflare APIに接続し、Zone ID・APIトークンが有効かを確認（キャッシュはパージしません）
- **今すぐパージ** — 確認ダイアログの後、保存済みの設定で実際にキャッシュを全パージします

メール・Webhookのテンプレートでは以下のプレースホルダーが利用できます。

```
{{status}} {{publication_id}} {{destination_url}} {{entry_url}}
{{date_created}} {{date_finished}} {{num_urls_crawled}} {{num_results_deployed}}
{{user_id}} {{user_login}} {{site_url}} {{admin_publication_url}} {{failure_message}}
```

## スケジュール公開

Staatic の管理メニュー配下「Actions」設定ページの「スケジュール公開」セクションで、以下の頻度を選んで
自動公開を有効化できます。時刻はサイトのタイムゾーンで判定されます。

| 頻度 | 内容 |
|---|---|
| 指定日時に1回だけ実行 | 指定した日付・時刻に1回だけ公開を実行（過去の日時は無視されます） |
| 毎日実行 | 毎日指定した時刻に公開を実行 |
| 毎週指定曜日に実行 | 選択した曜日の指定時刻にのみ公開を実行 |

WP-Cron（`next_staatic_actions_scheduled_publish`）から Staatic 自身の公開トリガーである
`do_action('staatic_publish')` を呼び出す仕組みで、設定を保存すると自動的にスケジュールが再設定されます。

## 検知の仕組み

Staatic 自体には「公開完了」「公開失敗」を通知する専用フックが存在しないため、Staaticの内部タスクフックを
組み合わせて検知しています。

- **成功**: `staatic_publication_task_after` フックで、パイプライン最後のタスクである `FinishTask` を判定
- **失敗**: `staatic_publication_task_any` で公開中の `Publication` オブジェクトの参照を保持し、
  `shutdown` アクションでステータスが `failed` になっていないかを確認するワークアラウンドで検知

いずれもバックグラウンド公開（管理画面 / WP-Cron）・WP-CLI公開の両方で動作します。詳細設計は
[SPEC.md](SPEC.md) を参照してください。

## 拡張

本プラグインは独自のアクションフックを発火します。メール・Cloudflare・Webhookの3機能もこのフックの
利用者として実装されているため、他のコードから同様に処理を追加できます。

```php
add_action('next_staatic_actions_publish_succeeded', function (array $context) {
    // $context には publication_id / status / destination_url などが含まれる
});

add_action('next_staatic_actions_publish_failed', function (array $context) {
    // ...
});
```

## 開発

```bash
composer install

# コーディング規約チェック / 自動修正
composer run phpcs
composer run phpcbf

# テスト（Unit のみ、WordPress不要）
composer run test:unit

# テスト（Integration含む全体。WordPressテストライブラリが必要）
bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest
WP_TESTS_DIR=/tmp/wordpress-tests-lib vendor/bin/phpunit --bootstrap tests/phpunit/bootstrap.php
```

`main` への push・Pull Request で GitHub Actions が phpcs・PHPUnit（WP最新+1世代前 × PHP 7.4/8.3/8.4）・
Plugin Check を実行します。`0.0.0` 形式のタグを push すると、CIが全て通過した場合のみ配布用zipを
ビルドして GitHub Release を自動作成します（プラグインヘッダーの `Version` とタグが一致している必要があります）。

## アンインストール

プラグイン削除時に `uninstall.php` が実行され、設定オプション（`next_staatic_actions_settings`）を削除します。

## ライセンス

GPLv2 or later
