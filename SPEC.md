プラグイン名称 : NExT-Staatic-Actions

ローカル環境 : http://test-ai.local/

リポジトリ : https://github.com/AtsushiA/NExT-Staatic-Actions


Staatic プラグインの公開（静的ジェネレート）が完了した際に実行する処理を追加する WordPress プラグイン。
公開の成功/失敗検知に加えて、公開そのものをスケジュール実行する機能も持つ。


## 検知の仕組み

Staatic 本体には「公開完了」「公開失敗」を通知する専用フックが存在しないため、既存のタスク単位フック
（`staatic_publication_task_after` 等）を組み合わせて検知する。

- **成功検知**：`staatic_publication_task_after` フックで `FinishTask` を判定（パイプライン最後のタスク）
- **失敗検知**：`staatic_publication_task_any` で `$publication` オブジェクトの参照を保持し、
  `shutdown` アクションでステータスが failed になっていないか確認するワークアラウンドで検知
- 開始・キャンセルの検知は対象外（将来拡張の余地は残す）

いずれもバックグラウンド公開（管理画面 / WP-Cron）・WP-CLI公開の両方で動作する。

検知結果は本プラグイン独自のアクションフック（`next_staatic_actions_publish_succeeded` /
`next_staatic_actions_publish_failed`）として発火し、以下の3機能はすべてこのフックの利用者として実装する。
これにより他コードからも同じフックに相乗りして処理を追加できる。


## 公開完了時に実行する処理（すべて成功/失敗ごとに個別ON/OFF可能）

1. **メール通知** — 通知先メールアドレスを登録（複数可）。件名・本文はプレースホルダーテンプレート対応
2. **Cloudflare キャッシュパージ** — Zone ID・APIトークンを使い Purge Cache API を呼び出す
3. **Webhook 通知** — 任意の URL への HTTPリクエスト（メソッド / ヘッダー / ボディを設定可能。
   Cloudflare 以外の CDN パージ API 等にも汎用的に利用できる）

### テスト送信・接続確認機能

実際の公開を待たずに、設定画面からその場で動作確認できる。

- **メール通知・Webhook通知**：「保存済みの設定でテスト送信」ボタンで、サンプルデータを使って実際に送信し、
  成功/失敗とステータスを画面に表示する（有効/無効の設定に関わらず動作）
- **Cloudflare キャッシュパージ**：
  - 「接続確認」— Cloudflare API に接続し、Zone ID・APIトークンが有効かを確認（パージはしない）
  - 「今すぐパージ」— 確認ダイアログの後、保存済みの設定で実際にキャッシュを全パージする

いずれも管理画面の nonce 保護付き admin-ajax エンドポイント（`next_staatic_actions_test_send`）経由で、
ページ全体のリロードなしに結果を表示する。


## スケジュール公開

指定した時間にトリガーで Staatic の公開（静的ジェネレート）そのものを自動実行する。

- 頻度は「指定日時に1回だけ」「毎日」「毎週指定曜日」の3モードから選択（設定画面）
- 時刻はサイトのタイムゾーンで判定
- WP-Cron（`next_staatic_actions_scheduled_publish`）から Staatic の公開トリガー
  `do_action('staatic_publish')` を呼び出す。設定保存時に自動で再スケジュールする
- 曜日指定モードは WP-Cron が「毎日実行」の間隔でスケジュールし、コールバック内で当日の曜日が
  選択されているか判定してからのみ公開を実行する（WP-Cronは複数曜日の間隔を直接表現できないため）
- プラグイン無効化・アンインストール時にスケジュールをクリアする


## 設定管理

wp-config.php の定数ではなく、WP管理画面の設定ページ（Settings API、Staatic メニュー配下の
サブメニュー「Actions」）で管理する。縦に長くならないよう、タブ構成にしている。

タブ構成：メール通知 / Cloudflare キャッシュパージ / Webhook 通知 / スケジュール公開 / 詳細設定

- メール: 有効/無効（成功・失敗別）、宛先（複数）、件名、本文、テスト送信
- Cloudflare: 有効/無効（成功・失敗別）、Zone ID、APIトークン、接続確認、今すぐパージ
- Webhook: 有効/無効（成功・失敗別）、URL、HTTPメソッド、ヘッダー、ボディ（プレースホルダーテンプレート対応）、テスト送信
- スケジュール公開: 有効/無効、頻度、日付、曜日、時刻
- 詳細設定: デバッグログ ON/OFF

全タブのフィールドは常にフォームに出力し、CSSで表示/非表示のみを切り替える（タブをまたいで保存しても
他タブの設定がデフォルト値で上書きされないようにするため）。


## CI/CD

- composer（phpcs / WordPress Coding Standards、yoast/wp-test-utils による PHPUnit）
- GitHub Actions:
  - `ci.yml` — main への push/PR で phpcs・PHPUnit（WP最新+1世代前 × PHP 7.4/8.3/8.4）・Plugin Check
  - `release.yml` — `0.0.0` 形式のタグ push で `ci.yml` を再実行し、全チェック通過時のみ配布用zipを
    ビルドして GitHub Release を自動作成（タグとプラグインヘッダーの `Version` が一致している必要あり）
- 開発は `feature/*` ブランチ → PR → CI通過 → マージ → タグpush → 自動リリース、の流れで行う


## 更新履歴

| バージョン | 内容 |
|---|---|
| [1.0.0](https://github.com/AtsushiA/NExT-Staatic-Actions/releases/tag/1.0.0) | 初回リリース。公開成功/失敗の検知、メール通知・Cloudflareキャッシュパージ・Webhook通知、独自拡張フック、CI/CD（phpcs・PHPUnit・GitHub Actions・タグリリース） |
| [1.1.0](https://github.com/AtsushiA/NExT-Staatic-Actions/releases/tag/1.1.0) | スケジュール公開機能を追加（指定日時1回・毎日・毎週指定曜日） |
| [1.2.0](https://github.com/AtsushiA/NExT-Staatic-Actions/releases/tag/1.2.0) | 設定ページをタブ構成に変更（メール／Cloudflare／Webhook／スケジュール公開／詳細設定） |
| [1.3.0](https://github.com/AtsushiA/NExT-Staatic-Actions/releases/tag/1.3.0) | メール通知・Webhook通知に「テスト送信」ボタンを追加（ステータス表示付き） |
| [1.4.0](https://github.com/AtsushiA/NExT-Staatic-Actions/releases/tag/1.4.0) | Cloudflareに「接続確認」「今すぐパージ」ボタンを追加 |


## 将来的な拡張（未着手）

- Slack通知
- 開始・キャンセルの検知（必要になれば）


詳細な初期設計は実装計画（`/Users/atsushi/.claude/plans/next-staatic-actions-playful-dahl.md`）を参照。
README（日本語 [README.md](README.md) / English [README.en.md](README.en.md)）も参照。
