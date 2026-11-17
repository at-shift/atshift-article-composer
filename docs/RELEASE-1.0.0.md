# 1.0.0 Release Record

The owner authorized both GitHub and WordPress.org publication on November 17,
2026. WordPress.org 1.0.0 was committed in SVN revision **3807381**. The public
listing, directory API, official ZIP and four CDN images were verified. GitHub
v1.0.0 uses the official ZIP unchanged for the final update-channel handoff.
Historical v0.7.6 and migration-helper-v0.1.0 artifacts remain unchanged. No
existing WordPress installation was manually updated.

The new repository already has only `main`, so no branch merge or deletion is
needed. The owner confirmed that old DeFt repository branches are outside this
cleanup. Both distribution packages continue to be built from the same source.

## Scope

- Bundle the reviewed Japanese gettext catalog, MO, four JavaScript JSON catalogs
  and Japanese user guide in the official directory package as well as test builds.
- Keep the approved runtime identity, storage, credentials and permission model.
- Use the official WordPress.org package as GitHub's final transition update.
- Reorganize directory copy with a short setup flow and grouped capabilities.
- Use the owner-approved English v3 banner with "Just talk. Then publish." and
  smaller feature copy. Standard/high-density exports are prepared in
  `wordpress-org/assets/`; earlier proposals are not publication assets.

## Distribution

- [WordPress.org](https://wordpress.org/plugins/atshift-article-composer/)
- [GitHub v1.0.0](https://github.com/at-shift/atshift-article-composer/releases/tag/v1.0.0)
- [Official ZIP](https://downloads.wordpress.org/plugin/atshift-article-composer.1.0.0.zip)
- Official ZIP: **788636 bytes**, 426 regular files.
- SHA-256: `bfbb8d9e0c79d13873a6cb425bc23d2c44520bab783519c537b7d66a183cfcef`.

SVN trunk and tags/1.0.0 match the tested local candidate file-for-file. The
directory archive has different ZIP container bytes but identical file contents.
It was installed and the complete disposable suite rerun successfully; see
[verification](VERIFICATION-1.0.0.md). GitHub's three assets are the official ZIP,
its SHA-256 file and the update manifest, not the updater-enabled development ZIP.

The directory page reported a six-hour delay before delivering updates to sites.
The listing, API and versioned ZIP were already accessible. Update notifications
can therefore lag behind publication; this is not a reason to republish the tag.

## Update Handoff

Follow [Release and Update Channels](RELEASING.md). First publish and verify the
WordPress.org 1.0.0 ZIP, then generate the GitHub handoff from those exact bytes.
Do not attach an ordinary updater-enabled 1.0.0 development ZIP to the public
release. Existing GitHub 0.7.6 users must be offered a newer version that removes
the external updater and retains Japanese. Subsequent updates use WordPress.org.

The legacy migration helper remains separately versioned and pinned to original
DeFt 0.7.4 -> Article Composer 0.7.6. It is not changed to accept 1.0.0; a supported
migration reaches 0.7.6 first and then uses the normal update path.

## Release Summary

Focus on your message. Let the AI you already use handle the rest.

This first directory release includes Japanese interface translations and the
Japanese user guide. Download the AI guide, install and activate the official
MCP Adapter 0.7.1 or newer, then connect a compatible AI client. Ask it to assemble
your article and layout, confirm the destination and existing categories/tags,
review the result, and explicitly request immediate or scheduled publication
through a separately authorized publishing connection.

For existing GitHub installations, this transition update installs the official
WordPress.org package without changing the plugin identity or automatic-update
preference. Japanese remains included; later updates come from WordPress.org.
Back up before updating. Existing limitations and permission boundaries remain
documented in the README.
