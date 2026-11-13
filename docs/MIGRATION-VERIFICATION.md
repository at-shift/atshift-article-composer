# Migration Helper Verification

Local verification: November 13, 2026. Helper version 0.1.0.
This is not a production migration, public release, WordPress.org submission or
directory review approval. The main plugin and its existing release ZIPs remain unchanged.

## Tested Route

- Original published GitHub DeFt 0.7.4 ZIP to original published GitHub Article
  Composer 0.7.6 ZIP, installed through WordPress/WP-CLI.
- WordPress 7.1.4, PHP 8.3.36, MySQL/InnoDB, MCP Adapter 0.7.1, Twenty Twenty-Five.
- Separate, randomly named disposable database; two test owners. No existing
  site's accounts, passwords, plugin files or content were changed.
- Data created by the actual old runtime: five immutable snapshots, two catalog
  components, owner usage, one published destination/category, three private
  assets, frozen public media and fourteen binary chunks.

## Results

Both entry points passed independently. A database backup restored the old
fixture between CLI and browser runs.

- 45 PHP assertions: seed, preflight guards, preparation, retained-data behavior.
- CLI inspect / prepare / activate / fresh-process verify; repeat verification;
  recovery of an interrupted activation receipt with the correct target active.
- Rejection of stale inspection fingerprints, missing confirmation/backup,
  premature activation, duplicate preparation, changed prepared content,
  anonymous and author access, reservations and pending cron, pending prefix
  migration, legacy table conflicts, missing/corrupt bytes and edited packages.
- Injected preparation failure rolls back credential updates and creates no
  successful checkpoint. No posts are published or reservations cancelled by migration.
- Preserved table counts/hashes, post records, revisions, category assignment,
  owners and role capabilities, published URL/status, binary media and catalog.
- Existing connections/tokens/flows/transfers invalidated; old publishing key
  rejected. Owner isolation and saving a subsequent revision work on the target.
- Browser POST nonce/anonymous rejection, diagnostic download, prepare / activate /
  verify sequence, migrated draft list. Desktop 1440 and mobile 390 screenshots
  inspected (mobile first viewport and scrolled controls); mobile has no
  horizontal overflow, and no JavaScript page errors.
- After deactivating the helper, anonymous GET and HEAD for both preserved
  managed image and PDF return 200 with nonzero content lengths.
- Eleven existing distribution/prefix/update-handoff Node tests still pass.
- Only expected core update warnings from deliberately blocked external HTTP
  were present. External Gravatar requests were blocked by browser test policy.

Tested helper ZIP SHA-256:

`e69f4d602832318210820dcfc6a624435113c64187275e8684b9005e459b1b6a`

The ZIP contains the shared engine, WordPress UI, WP-CLI command, both guides,
license and pinned source/target file manifests. It contains no customer data,
credentials or main-plugin package. Build with `scripts/package-migration.mjs`;
exercise with `scripts/test-migration.mjs`. Fixture paths are supplied through
`AC_WP_CORE`, `AC_WP_FIXTURES`, `WP_CLI`, `AC_MCP_ADAPTER`, `AC_OLD_ZIP`,
`AC_NEW_ZIP` and `AC_PLAYWRIGHT`. The test harness owns and deletes only its random
`ac_migration_*` database and stops its HTTP/browser processes.

## Boundaries

- This does not cover legacy filesystem imports, `sf_` prefix conversion,
  multisite, modified packages, earlier alphas or another version pair.
- A helper receipt proves data equality at that point, not all real-world theme,
  custom post type or membership behavior. Rehearse the actual site's clone.
- The helper is intentionally unverified by the target's public-output access
  policy. Deactivate it after data verification before testing public managed
  media; do not loosen access protections or remove unrelated integrations.
- No real external AI account was reconnected by this isolated test. Test each
  owner's reconnection and permissions separately, without accidental publishing.
- Full backup restoration is not an automatic rollback button. Preparation uses
  a database transaction; plugin activation may run DDL outside that transaction.
- PHP 8.2 and WordPress 6.9 are declared minimums, not additional matrix results
  from this helper run. Hosting-specific backup and maintenance remain operator
  responsibilities.
- No full security audit or WordPress Plugin Check of this separate temporary
  helper was performed in this task; it is not included in the directory package.
