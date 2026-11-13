# Identifiers and Earlier Versions

This repository starts from the submitted atshift Article Composer 0.7.6 runtime.
Its canonical identity is `atshift-article-composer/atshift-article-composer.php`
on both GitHub and WordPress.org.

The main plugin does not migrate older experimental data or private filesystem
files. A separate [Migration Helper and AI runbook](MIGRATION.md) support the
original GitHub 0.7.4 to 0.7.6 single-site route when current `atshde_` tables and
database media are already present. Both routes use the same engine; neither
renames legacy `sf_` tables nor merges conflicting datasets. Old connection
authority is explicitly invalidated, not silently inherited. Keep original
backups and rehearse first. Do not activate old and new packages together.

Internal names such as `Atshift\Deft`, existing storage prefixes and
`structured-fields` protocol routes are intentional implementation identifiers,
not alternative installation slugs. Do not edit the displayed connection URLs.
See [Release and Update Channels](RELEASING.md) for the new canonical package's
future GitHub-to-WordPress.org handoff.
