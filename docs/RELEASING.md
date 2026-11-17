# Release and Update Channels

The canonical runtime is the WordPress.org submission, starting with 0.7.6.
Both channels install `atshift-article-composer/atshift-article-composer.php`.
The old `at-shift/atshift-deft` repository, releases and experimental installations
are separate. No automatic migration from their data is provided.

## One Source Branch

Maintain runtime code, translations, guides, tests and directory assets together
on `main`. Do not create separate long-lived GitHub and WordPress.org source
branches. Distribution differences belong in the package builder; the final
GitHub handoff uses the officially downloaded directory ZIP unchanged.
Historical tags and Releases remain immutable. Updating `main` does not create
a Release or offer a new version to installed plugins.

## Build

```sh
npm ci
npm run build:php-deps
npm run pack:wordpress
npm run pack:wordpress-org
npm run test:distribution
node --test tests/update-handoff.test.mjs
```

The development GitHub build adds only its updater and `Update URI`.
Both builds include Japanese translations and the Japanese guide. Runtime code,
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
The directory package includes the Japanese catalogs and guide. Verify their
presence and actual gettext/JavaScript loading after the update; do not depend on
a separate WordPress.org language pack being ready. Standard language packs remain
supported for subsequent translation updates.

### First Directory Release: 1.0.0

1. Finish local verification and obtain publication approval. The owner-approved
   English v3 banner exports are in `wordpress-org/assets/`; earlier candidates
   are reference-only. Image approval is separate from publication approval.
2. Publish the 1.0.0 WordPress.org package to SVN trunk and tags/1.0.0; verify the
   resulting directory API and official downloadable ZIP independently.
3. Run `node scripts/prepare-wordpress-org-handoff.mjs 1.0.0 0.7.6 .local/handoff-1.0.0`.
4. Test the downloaded bytes, then publish GitHub v1.0.0 with those exact handoff
   assets. Do not publish the ordinary updater-enabled 1.0.0 ZIP first: an
   equal-version replacement would not be offered to installed users.
5. Re-download GitHub's assets and compare with the official ZIP and manifest.
   Keep the transition release available as the final GitHub update route.

Local rehearsal ZIPs and mocked update APIs do not prove that this public
transition has happened. The release record must distinguish these stages.

## Verification

Run the disposable suite with `scripts/test-wordpress-review.mjs` and explicit
fixture paths. It blocks production databases and external HTTP. It exercises a
normal native GitHub update, a simulated future handoff, then a fresh-process
directory update check. Future version numbers and API responses in this test
are fixtures, not released packages or proof of directory approval.

Set `AC_DIRECTORY_ZIP` to the absolute path of the downloaded official ZIP to
repeat the suite against the exact distributed bytes. Before installation, the
runner checks every regular file against the source build, allowing ZIP directory
entries but no changed or extra files. Set `AC_PREVIOUS_GITHUB_ZIP` to the original
published 0.7.6 ZIP to verify the actual-version native handoff as well. These
inputs are read-only; test databases, update responses and future versions remain
disposable fixtures.

Before each public release, download the uploaded assets again, compare hashes,
verify the tag/commit and plugin version, and verify WordPress.org artifacts
separately when that destination is requested. Never treat local tests as deployment.
