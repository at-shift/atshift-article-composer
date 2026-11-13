# Migration Helper 0.1.0

This is a **separate, temporary migration utility**, not an Article Composer
plugin update. Article Composer **v0.7.6 remains the latest main-plugin release**.
Do not install this ZIP instead of the main plugin on a new site.

DeFt development and new releases have ended. Historical DeFt release tags,
ZIPs, checksums and update metadata are retained unchanged for migration/recovery.
The successor is [atshift Article Composer](https://github.com/at-shift/atshift-article-composer).

## Choose Your Route

- **Regular AI chat:** install the helper in WordPress and follow
  [the migration steps](https://github.com/at-shift/atshift-article-composer/blob/migration-helper-v0.1.0/docs/MIGRATION.md).
  A redacted diagnostic report can be shared with your usual AI for explanation.
- **Codex / authorized SSH / WP-CLI agent:** download the attached `AI-MIGRATION.md`
  and give it to your agent. It uses the same engine and safety checks.

Both routes require a WordPress administrator and a tested full backup. They do
not run through an ordinary draft-authoring MCP connection.

## Supported First Route

**Original GitHub DeFt 0.7.4 -> original GitHub Article Composer 0.7.6**,
single site, current `atshde_` tables and database-backed private media.
The helper checks exact package files and data fingerprints before proceeding.

Earlier versions, modified packages, multisite, legacy `sf_` tables,
filesystem-only media, missing/corrupt files and pending reservations stop the
operation. Do not bypass those checks or blindly upgrade through older releases.

1. Back up and test restoration on a separate copy; rehearse there first.
2. Install the target without activation; keep MCP Adapter active.
3. Arrange maintenance, pause writers/jobs and deactivate DeFt without deletion.
4. Inspect, prepare, activate and verify using the helper or the runbook.
5. Deactivate the helper, check real previews/public pages and access rules, then
   reconnect each owner's AI. Retain the inactive old plugin and backup.

Preparation **invalidates old AI keys, OAuth tokens, pending login flows and
transfer tokens**. No post is published, no reservation is cancelled, and no
content/owner is replaced by migration. Backups and full recovery are not automated.
While the helper is active, the successor's access policy intentionally withholds
additional public output; deactivate the helper before testing public images/PDFs.

## Assets and Verification

- `atshift-composer-migration-0.1.0.zip`: installable temporary helper.
- Matching `.sha256`: checksum of that ZIP.
- `AI-MIGRATION.md`: AI/CLI runbook.
- `MIGRATION.md`: human-facing steps, including a Japanese summary.

Both entry points were tested using the actual old/new released ZIPs in a
disposable WordPress 7.1.4 / PHP 8.3.36 / MySQL/InnoDB environment with MCP Adapter
0.7.1 and Twenty Twenty-Five. Verification covers data equality, owner isolation,
credential invalidation, rejected/stale/failed operations, browser CSRF checks,
desktop/mobile UI and preserved image/PDF GET/HEAD delivery after helper removal.

This is focused migration testing, not a full security audit or production-site
certification. Hosting-specific backups, real AI reconnection, theme/membership
behavior and other declared-minimum environments require separate verification.
See [the verification record](https://github.com/at-shift/atshift-article-composer/blob/migration-helper-v0.1.0/docs/MIGRATION-VERIFICATION.md).

No existing main-plugin ZIP, WordPress.org submission or live site is changed by
this helper release. It is not a WordPress.org approval announcement.
