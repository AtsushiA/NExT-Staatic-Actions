# NExT Staatic Actions

*(English README: [README.en.md](README.en.md))*

[Staatic](https://staatic.com/wordpress)（静的サイトジェネレーター）の公開（デプロイ）が完了した際に、
メール通知・Cloudflareキャッシュパージ・Webhook通知を自動実行する WordPress プラグインです。

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

メール・Webhookのテンプレートでは以下のプレースホルダーが利用できます。

```
{{status}} {{publication_id}} {{destination_url}} {{entry_url}}
{{date_created}} {{date_finished}} {{num_urls_crawled}} {{num_results_deployed}}
{{user_id}} {{user_login}} {{site_url}} {{admin_publication_url}} {{failure_message}}
```

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

## アンインストール

プラグイン削除時に `uninstall.php` が実行され、設定オプション（`next_staatic_actions_settings`）を削除します。

## ライセンス

GPLv2 or later
