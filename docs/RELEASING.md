# Release and Update Channels

The canonical runtime is the WordPress.org submission, starting with 0.7.6.
Both channels install `atshift-article-composer/atshift-article-composer.php`.
The old `at-shift/atshift-deft` repository, releases and experimental installations
are separate. No automatic migration from their data is provided.

## Build

```sh
npm ci
npm run build:php-deps
npm run pack:wordpress
npm run pack:wordpress-org
npm run test:distribution
node --test tests/update-handoff.test.mjs
```

GitHub adds only its updater, `Update URI`, partial Japanese translations and
Japanese guide. The WordPress.org build excludes these additions. Runtime code,
schemas and dependencies must otherwise match. Source archives are not installable
plugin ZIPs. Do not commit generated dependencies, ZIPs, credentials or test data.

Publish a GitHub tag `vVERSION` with these three assets only after approval:

- `atshift-article-composer-VERSION.zip`
- `atshift-article-composer-VERSION.zip.sha256`
- `atshift-article-composer-update.json`

The updater validates the exact repository, release URL, plugin identity, version,
asset size and SHA-256. Drafts, prereleases, older versions and malformed releases
are not offered. It does not enable automatic updates or alter another plugin's
updates. GitHub receives ordinary update HTTP requests, never site credentials,
drafts or connection keys. Repository maintainers control the delivered code.

## Switch to WordPress.org

Do not switch merely because the application is approved or its page exists.
First publish and verify an official directory ZIP newer than the highest GitHub
version already distributed. Coordinate versions to avoid downgrades or an
equal-version update that WordPress will not offer.

```sh
node scripts/prepare-wordpress-org-handoff.mjs NEW_VERSION LAST_GITHUB_VERSION .local/handoff
```

This downloads the versioned ZIP from `downloads.wordpress.org`, rejects redirects,
checks its canonical identity/version and absence of the GitHub updater, and writes
the unchanged bytes under the GitHub asset filename with checksum and metadata.
`wordpress-org-handoff.json` records provenance locally. The update manifest still
uses channel `github` because the currently installed GitHub updater transports
this final update. The package itself is the official WordPress.org package.

Install and test the exact handoff ZIP through the native WordPress updater before
publishing it. In a fresh request verify:

1. Plugin remains active under the same basename.
2. Drafts, components, settings, owner permissions and update preferences survive.
3. GitHub updater file, filter and external `Update URI` are absent.
4. WordPress core offers later directory updates without a GitHub request.

After explicit publication approval, attach the three generated update assets to
the final GitHub release. Do not run the ordinary GitHub package builder for this
release: that would reintroduce the updater. Keep this release available so sites
that missed it can still transition later; do not replace it with a higher GitHub
release that contains the updater. Continue development in this repository and
publish subsequent production updates through WordPress.org.

Users apply a normal plugin update; automatic update behavior follows their existing
preference. No deletion/reinstallation is required for this canonical identity.
The English-only directory package removes bundled Japanese catalogs; standard
WordPress.org language packs remain supported, but translation availability is
not guaranteed until those language packs exist.

## Verification

Run the disposable suite with `scripts/test-wordpress-review.mjs` and explicit
fixture paths. It blocks production databases and external HTTP. It exercises a
normal native GitHub update, a simulated future handoff, then a fresh-process
directory update check. Future version numbers and API responses in this test
are fixtures, not released packages or proof of directory approval.

Before each public release, download the uploaded assets again, compare hashes,
verify the tag/commit and plugin version, and verify WordPress.org artifacts
separately when that destination is requested. Never treat local tests as deployment.
