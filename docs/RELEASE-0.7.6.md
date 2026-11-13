# atshift Article Composer 0.7.6

**AI connections require the official [WordPress MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) 0.7.1 or newer, installed and active.** Download the AI guide in Article Composer and follow its connection instructions.

This is the first release in `at-shift/atshift-article-composer`, based on the submitted WordPress.org 0.7.6 runtime. Directory review remains pending; this GitHub release is not directory approval.

## Install

Download `atshift-article-composer-0.7.6.zip` below, not GitHub's automatically generated source archive. WordPress 6.9+ and PHP 8.2+ are required. Start on an isolated test site with a backup.

**This is not an automatic migration from atshift DeFt or Structured Fields Prototype.** Use a fresh installation or a separately tested export/import plan. The previous repository, releases and existing sites are unchanged.

## Update Channels

- Both builds use `atshift-article-composer/atshift-article-composer.php`.
- This GitHub ZIP includes its verified release updater, partial Japanese translations and the Japanese guide. Automatic-update preferences remain yours.
- The WordPress.org build is English-only and contains no external updater.
- After an official newer directory ZIP becomes available, a final GitHub transition update can install that ZIP unchanged. Subsequent updates then use WordPress.org, without deleting/reinstalling the plugin. The transition update must be applied; publication alone does not switch installed copies.
- At handoff, bundled Japanese files are replaced by the directory package; translation availability then follows WordPress.org language packs.

## Baseline Improvements

- Membership-aware structure JSON and managed-media access; unsupported combinations hold these extra outputs and show administrators why.
- Owner-scoped AI content permissions, nonce checks, retryable schema setup and database-backed private files.
- Existing draft, preview, publication destination, taxonomy, scheduling, trash/restoration and shared component workflows.
- Canonical package identity, checksum-pinned GitHub metadata, a directory handoff preparation tool, and native update-chain regression tests.

## Verification

Installed-ZIP testing on WordPress 7.1.4 / PHP 8.3.36 passed: 77 Node checks, 145 HTTP checks, 80 browser checks, PHP integration suites, multisite and relocated-content tests. Native GitHub update, simulated directory handoff and fresh-process directory update detection passed 69, 70 and 8 checks respectively. Plugin Check: **0 errors, 370 remaining warnings**; no claim of reviewer approval or independent certification is made.

See [Getting Started](https://github.com/at-shift/atshift-article-composer/blob/main/docs/GETTING-STARTED.md), [Repository Baseline](https://github.com/at-shift/atshift-article-composer/blob/main/docs/BASELINE.md), [Verification](https://github.com/at-shift/atshift-article-composer/blob/main/docs/VERIFICATION-0.7.6.md) and [Release Channels](https://github.com/at-shift/atshift-article-composer/blob/main/docs/RELEASING.md). Assets include the installable ZIP, SHA-256 checksum and updater metadata.
