# Identifiers and Earlier Versions

This repository starts from the submitted atshift Article Composer 0.7.6 runtime.
Its canonical identity is `atshift-article-composer/atshift-article-composer.php`
on both GitHub and WordPress.org.

It does not migrate older experimental data, private filesystem files, connection
grants or permissions. Use a fresh site for evaluation. Keep original backups and
plan a separately tested export/import before moving an older experimental site.
Do not activate both the old package and Article Composer together.

Internal names such as `Atshift\Deft`, existing storage prefixes and
`structured-fields` protocol routes are intentional implementation identifiers,
not alternative installation slugs. Do not edit the displayed connection URLs.
See [Release and Update Channels](RELEASING.md) for the new canonical package's
future GitHub-to-WordPress.org handoff.
