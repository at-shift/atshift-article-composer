# Repository Baseline

This repository starts from the WordPress.org submission prepared on November 12,
2026, version 0.7.6. It does not import the earlier repository's Git history,
reviewer correspondence, credentials, local databases or deployment backups.
The old repository and its releases remain unchanged.

Canonical submission artifact:

- Filename: `atshift-article-composer-0.7.6-wordpress-org.zip`
- SHA-256: `a975836433fb7adace155fbd2d81059462261bc77fef342e1a8d78b802e51091`
- 419 files; 692467 bytes

The initial repository changes distribution identity, GitHub update routing,
documentation and update tests. Runtime PHP, JavaScript, CSS, schemas and scoped
dependencies remain the submitted baseline. The distribution README is revised
to explain the new repository and future directory handoff. Japanese guide/catalogs
and the GitHub updater are GitHub-only additions.

An allocated directory slug and an uploaded submission are not review approval
or publication. This repository creation does not alter the pending submission.

## Earlier Experimental Data

No automatic import of older DeFt / Structured Fields data is included. Use a
fresh installation or a separately tested export/import plan. This is distinct
from future updates of the new canonical package, which keep the same basename.

## Update Verification

The new suite exercises native WordPress installation/update and checks stored
drafts, components, theme settings, activation and automatic-update preferences.
A synthetic future directory package then removes the updater, and a fresh PHP
process verifies that core offers a subsequent directory update without calling
GitHub. Those future versions and network responses are test fixtures only.
