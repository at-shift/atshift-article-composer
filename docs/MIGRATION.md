# Migrating from DeFt

Two entry points use **the same migration engine**, outside Article Composer:

| Your environment | Route |
| --- | --- |
| Regular AI chat and a WordPress administrator login | Temporary Migration Helper in WordPress **Tools > Article Composer migration** |
| Codex or another agent with authorized SSH / WP-CLI access | Give the agent [AI-MIGRATION.md](AI-MIGRATION.md); it runs the same checks and operations |

The first helper supports original GitHub **DeFt 0.7.4 -> Article Composer 0.7.6**
on a single site with `atshde_` tables and database-backed private media. It does
not convert earlier prefixes or filesystem-only media. Unsupported cases stop,
not silently skip data. This is separate from future GitHub-to-WordPress.org updates.

## Downloads

- [Migration Helper 0.1.0 and checksum](https://github.com/at-shift/atshift-article-composer/releases/tag/migration-helper-v0.1.0)
- [Source DeFt 0.7.4 package](https://github.com/at-shift/atshift-deft/releases/tag/v0.7.4), retained for migration/recovery only
- [Target Article Composer 0.7.6 package](https://github.com/at-shift/atshift-article-composer/releases/tag/v0.7.6)
- [AI migration runbook download](https://github.com/at-shift/atshift-article-composer/releases/download/migration-helper-v0.1.0/AI-MIGRATION.md)

DeFt development and new releases have ended. Existing DeFt ZIPs and tags remain
unchanged. Its updater does not automatically change the plugin's identity.
Do not install an old DeFt release on a new site, and do not blindly upgrade an
unsupported older site through historical versions to satisfy this helper.
Use the pinned 0.7.6 target for this initial migration, not an arbitrary later ZIP.

## WordPress Route

1. Have your hosting backup restored on a test copy. Include the complete
   database, plugin/configuration files, uploads and old private files outside
   WordPress. Keep a backup reference. The helper does not create the backup.
2. Install the original Article Composer 0.7.6 GitHub ZIP **without activating**.
   Install and activate the separately supplied Migration Helper ZIP. Keep
   the official MCP Adapter active.
3. Arrange maintenance, stop editing/AI sessions/background jobs, and deactivate
   DeFt without deleting it. Open **Tools > Article Composer migration**.
4. Resolve every diagnostic blocker. Download the report to let your usual AI
   explain it. It contains counts/hashes and reasons, not article bodies or keys.
   Do not upload backups, passwords, SQL dumps or private documents to your chat.
5. Enter the tested backup reference and confirm the preconditions: this is an
   isolated rehearsal, or a production run whose rehearsal already passed. Then choose
   **Prepare migration**. Old AI and transfer credentials are invalidated.
6. Choose **Activate Article Composer**, then **Verify saved data** on the next
   page. Deactivate the helper (its active presence intentionally holds additional
   public output under the new access policy). Review real drafts, previews, published pages, images/PDFs and access
   restrictions. Reconnect each person's AI. Do not publish during the test.
7. After the test copy passes, approve a production maintenance window and repeat
   with a fresh backup and diagnostic. Retain the inactive old plugin and backup.
   Remove the temporary helper after verification; the main plugin keeps no
   legacy migration machinery.

Do not resolve reservations by deleting them or publish them early. Ask their
owner to cancel/reschedule separately or wait until they finish. If a step stops,
keep maintenance enabled and seek assistance; reactivating DeFt alone is not a
complete rollback. See the runbook's recovery procedure.

## 通常のAIチャットを使う場合

1. サーバー側で全体バックアップを取り、コピー環境で復元できることを確認します。
2. Article Composer 0.7.6を「有効化せず」追加し、別配布の移行ツールを有効化します。
3. メンテナンス中にAI接続・編集・予約処理を止め、DeFtを停止します。削除はしません。
4. 「ツール > Article Composer migration」で診断し、問題があればそこで止めます。
5. バックアップと事前確認を済ませて「Prepare migration」、次に「Activate Article Composer」、
   最後に「Verify saved data」を実行します。古いAI接続キーは無効になるため再接続します。
6. データ照合後、移行ツールを無効化してから、下書き・プレビュー・公開ページ・画像・会員向けの閲覧制限を確認します。
   ツールが有効な間は新しいアクセス保護により追加の公開出力が保留されます。メンテナンスは確認が終わるまで解除しません。
   コピー環境で成功してから、本番の実行を承認してください。完了後は移行ツールを取り外せます。

診断レポートを普段のAIに読ませて相談できます。パスワードやバックアップ本体は渡さないでください。
予約投稿、旧形式のテーブル、DBにない古い画像・PDF、別バージョンは初期版の自動移行対象外です。

## Build and Validation

The helper is a separate artifact and is **not** bundled with either main-plugin
distribution. Its first version is 0.1.0; Article Composer remains 0.7.6.

```sh
node scripts/package-migration.mjs /private/packages/atshift-deft-0.7.4.zip /private/packages/atshift-article-composer-0.7.6.zip
```

The packager pins both original release ZIP hashes, builds file manifests and
produces `.local/migration/atshift-composer-migration-0.1.0.zip` with a SHA-256
sidecar. It does not publish, update a site, or add migration code to the main ZIP.
