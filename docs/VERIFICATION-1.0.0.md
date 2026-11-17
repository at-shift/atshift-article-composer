# 1.0.0 Local Release Verification

Date: November 17, 2026. At the time of these checks, no GitHub push, tag or Release,
SVN publication, submission replacement or installed-site deployment was performed.
The later authorized source-only GitHub update does not publish a plugin release;
see [RELEASE-1.0.0.md](RELEASE-1.0.0.md) for the current release gates.
The owner reported directory approval of the earlier 0.7.6 submission. Its
historical artifacts remain unchanged.

## Installed Artifacts

| Build | Bytes | Files | SHA-256 |
| --- | ---: | ---: | --- |
| GitHub development build | 789466 | 427 | `4cd3fddb55776cc228f1313eac9d8b7ded106e0d48825c4a39dd17ca7874d470` |
| WordPress.org release candidate | 786682 | 426 | `bdc8250d10a4ca1bbe949e66bd2d5833b483f62ce938d24580f57960f0a73e36` |

The exact ZIPs were installed before the checks. Rebuilding the working tree
after verification produced the same hashes. Both packages include the Japanese
PO/MO, four JavaScript translation catalogs and Japanese user guide.

The updater-enabled development ZIP above is **not** the intended public GitHub
1.0.0 transition asset. After directory publication, download and verify the
official 1.0.0 ZIP, then use those unchanged bytes for GitHub's handoff metadata
and asset. See [RELEASING.md](RELEASING.md).

## Results

Environment: WordPress 7.1.4, PHP 8.3.36, MySQL 8.4, Twenty Twenty-Five and MCP
Adapter 0.7.1. This run does not establish a new minimum-version compatibility matrix.

- 79 Node checks and 145 HTTP workflow checks passed.
- 80 browser model/article/tool checks passed (32 + 28 + 20).
- Installed-PHP suites passed for schema retry, owner isolation, OAuth, nonces,
  drafts, trash/restoration, media, quotas, destinations, taxonomies, reservations,
  appearance, storage portability and MCP prerequisite handling.
- Desktop/mobile saving, preview, trash and restore passed.
- Japanese Articles, Components and AI connections pages passed checks at
  1440px and 390px widths, including horizontal-overflow checks and Japanese
  save confirmation. Screenshots were visually reviewed.
- All 647 gettext messages are translated, non-fuzzy and placeholder/plural
  compatible. `msgfmt --check --check-format` passed. Installed i18n checks
  passed again after update; article data, schemas and identifiers stay unchanged.
- 95 first-party/test PHP files passed syntax validation.
- Relocated content directories and multisite second-site isolation passed.
- Build, package-integrity, readme guidance, translation and handoff-negative
  checks passed; `git diff --check` was clean.

The offline fixture has no WordPress core Japanese language pack. Core admin
navigation and user-created sample names therefore remain English; this is not
treated as an untranslated plugin string. External avatar requests are blocked.
The catalog review was AI-assisted and context-based, not an independent human
linguist certification. See [TRANSLATION-1.0.0.md](TRANSLATION-1.0.0.md).

## Actual-Version Native Update Rehearsal

Remote update responses were controlled local fixtures. The 1.0.0 directory
candidate is locally built, not yet a downloaded official WordPress.org release.

1. Installed the original published GitHub 0.7.6 ZIP, verified against SHA-256
   `cab3b51de465228cc567294f46c6a3ec0c97475d075eca32f46064e8c6bf6ce8`.
2. WordPress's native upgrader installed the exact 1.0.0 directory candidate:
   **73 checks passed**. The GitHub updater and Update URI were removed. Drafts,
   components, activation, theme settings and automatic-update preferences were
   retained, together with Japanese translations and the user guide.
3. In a fresh PHP process, WordPress core offered a simulated directory 1.0.1
   update without GitHub requests: **8 checks passed**.
4. Installed i18n checks (**25 passed**) and integration checks passed again.
5. The ordinary development-build upgrade and synthetic future directory-handoff
   tests also passed.

Live directory API/download verification and a handoff built from the officially
distributed ZIP remain publication gates. Existing installed copies have not
been switched. The separate legacy migration helper remains pinned to its
original DeFt 0.7.4 -> Article Composer 0.7.6 path.

## Plugin Check

Plugin Check 2.2.0 on the installed directory candidate: **0 errors, 371 warnings**.
The prior 0.7.6 record had 370 warnings. The additional warning is
`unexpected_markdown_file` for the newly bundled `GETTING-STARTED.ja.md`. It is
retained as user documentation. The existing `AI-GUIDE.md` warning also remains.

| Warning category | Count |
| --- | ---: |
| Direct database calls | 161 |
| Database caching analysis | 139 |
| Namespace prefix analysis | 40 |
| Nonce verification analysis | 17 |
| Input sanitization analysis | 6 |
| Hook/constant prefix analysis | 4 |
| Required Markdown files | 2 |
| Text-domain loading | 1 |
| Metadata query | 1 |

These warnings are recorded, not claimed fixed or waived by approval. This run
is not an independent security audit.

## Evidence and Cleanup

Final runner: `scripts/test-wordpress-review.mjs`.
Evidence directory on the verification machine:
`/private/tmp/atshift-article-composer-qa-20261117/review-GLdJsj`.
Selected logs and screenshots are also retained locally under
`.local/verification/1.0.0/`; neither location is part of the plugin ZIP.

Earlier attempts corrected outdated English-only assertions, readme wording
expectations, a catalog-page label assertion and a checkout under `.local` that
Vite correctly refused to serve. The final full run used an isolated temporary
directory without weakening private-file protections. The test-owned database
was dropped and temporary servers stopped. Nine expected WordPress core
external-HTTP warnings occurred under the offline policy; no unexpected debug
log entries remained.

After the package checks, the owner approved English banner v3 with "Just talk.
Then publish." and smaller feature copy. The 1544x500 and 772x250 exports are
prepared in `wordpress-org/assets/`; no Japanese banner is included. These
directory assets are outside the plugin ZIP and do not change the tested package
hashes. They have not been uploaded.
