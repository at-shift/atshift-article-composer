# 0.7.6 Canonical Repository Verification

Date: November 13, 2026. These are local verification results, not directory approval
or evidence that a production site was updated.

## Artifacts

| Build | Bytes | Files | SHA-256 |
| --- | ---: | ---: | --- |
| GitHub | 735984 | 425 | `cab3b51de465228cc567294f46c6a3ec0c97475d075eca32f46064e8c6bf6ce8` |
| WordPress.org preparation | 692599 | 419 | `69c32d7cddeeb99cc8fbe7664017f52f0c9aad91a00030620a8e029e562db5b6` |

Both were installed from the exact built ZIPs, not loose source files. Final
release candidates are byte-identical to the installed test artifacts. The
directory preparation differs from the submitted baseline only in README.md,
readme.txt and POT source-file references; runtime code is unchanged. The pending
submission itself was not replaced.

## Results

Environment: WordPress 7.1.4, PHP 8.3.36, MySQL, Twenty Twenty-Five, MCP Adapter 0.7.1.

- 77 Node checks passed; 145 HTTP workflow checks passed.
- 80 browser model/article/tool checks passed (32 + 28 + 20).
- Installed-PHP suites passed for schema retry, owner isolation, OAuth, nonces,
  drafts, trash/restoration, media, quotas, destinations, taxonomies and reservations.
- Desktop/mobile saving, preview, trash and restore passed; no browser page errors.
- Relocated content directories and multisite second-site isolation passed.
- 97 first-party/test PHP files passed syntax validation.
- Packaging, translation compilation, canonical identity, dependency manifest,
  artifact integrity and handoff-negative checks passed.

## Native Update Chain

All remote responses below were controlled local fixtures. Future versions 0.7.7
and 0.7.8 were synthetic test versions, not published releases.

1. A synthetic older header on the canonical GitHub package was updated to the
   exact 0.7.6 ZIP by WordPress's native upgrader: **69 checks passed**.
2. The installed GitHub updater delivered a simulated 0.7.7 directory package:
   **70 checks passed**. Drafts, components, theme settings, activation and
   automatic-update preferences were unchanged. The external updater file and
   Update URI were removed.
3. In a fresh PHP process, WordPress core offered a simulated directory 0.7.8
   update without contacting GitHub: **8 checks passed**.
4. Integration checks passed again after the transition.

The live directory handoff remains deferred until an official newer ZIP is
published and verified. See RELEASING.md. The current GitHub build still uses
GitHub updates; the handoff tool does not run automatically.

## Plugin Check

Plugin Check 2.2.0 on the installed directory preparation: **0 errors, 370 warnings**.
Warnings remain in the submitted runtime and are not claimed fixed, waived by
reviewers or equivalent to an independent security audit. Categories include
direct database access/caching, namespaced identifiers, nonce/input analysis,
required Markdown documentation, text-domain loading and a metadata query.

Earlier attempts exposed a stale ZIP path in a translation test and a test
checkout under `.local`, which Vite correctly refused to serve. The test path was
corrected and the final full suite ran in an isolated temporary directory without
weakening the private-file deny rules. All test-owned databases were dropped and
temporary servers stopped. Seven expected WordPress core external-HTTP warnings
occurred under the offline test policy; no unexpected debug-log entries remained.
