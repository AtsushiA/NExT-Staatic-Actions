プラグイン名称 : NExT-Staatic-Actions

ローカル環境 : http://test-ai.local/


Staatic プラグインの公開（静的ジェネレート）が完了した際に実行する処理を追加するプラグインを作りたい


## Phase 1（今回実装）

### 検知対象

- 公開**完了**（成功・失敗の両方）を検知する
- 開始・キャンセルの検知は Phase 1 の対象外（将来拡張の余地は残す）

Staatic 本体には「公開完了」「公開失敗」を通知する専用フックが存在しないため、既存のタスク単位フック
（`staatic_publication_task_after` 等）を組み合わせて検知する。

- 成功検知：`staatic_publication_task_after` フックで `FinishTask` を判定（パイプライン最後のタスク）
- 失敗検知：`staatic_publication_task_any` で `$publication` オブジェクトの参照を保持し、
  `shutdown` アクションでステータスが failed になっていないか確認するワークアラウンドで検知

### 公開完了時に実行する処理（すべて成功/失敗ごとに個別ON/OFF可能）

1. **メール通知** — 通知先メールアドレスを登録（複数可）
2. **CDNのパージ** — Cloudflare の Purge Cache API を呼び出す
3. **Webhook 通知** — 任意の URL への HTTP POST（URL / メソッド / ヘッダー / ボディを設定可能。
   Cloudflare 以外の CDN パージ API 等にも汎用的に利用できる）

内部的には、上記1〜3はすべて本プラグイン独自のアクションフック
（`next_staatic_actions_publish_succeeded` / `next_staatic_actions_publish_failed`）の
利用者として実装する。これにより Phase 2 以降の拡張（Slack通知など）もこのフックに相乗りするだけで
追加できる（Phase 1 では設定画面に項目は出さない）。

### 設定管理

wp-config.php の定数ではなく、WP管理画面の設定ページ（Settings API、Staatic メニュー配下の
サブメニュー）で管理する。

- メール: 有効/無効（成功・失敗別）、宛先（複数）、件名、本文
- Cloudflare: 有効/無効（成功・失敗別）、Zone ID、APIトークン
- Webhook: 有効/無効（成功・失敗別）、URL、HTTPメソッド、ヘッダー、ボディ（プレースホルダーテンプレート対応）
- 詳細: デバッグログ ON/OFF


## スケジュール公開（追加実装）

CI/CD整備後に追加した機能。指定した時間にトリガーで Staatic の公開を自動実行する。

- 頻度は「指定日時に1回だけ」「毎日」「毎週指定曜日」の3モードから選択（設定画面）
- 時刻はサイトのタイムゾーンで判定
- WP-Cron（`next_staatic_actions_scheduled_publish`）から Staatic の公開トリガー
  `do_action('staatic_publish')` を呼び出す。設定保存時に自動で再スケジュールする
- 曜日指定モードは WP-Cron が「毎日実行」の間隔でスケジュールし、コールバック内で当日の曜日が
  選択されているか判定してからのみ公開を実行する（WP-Cronは複数曜日の間隔を直接表現できないため）


## Phase 2（将来）

- Slack通知
- 開始・キャンセルの検知（必要になれば）


詳細な設計は実装計画（`/Users/atsushi/.claude/plans/next-staatic-actions-playful-dahl.md`）を参照。
