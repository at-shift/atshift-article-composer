# 1.0.0 Release Preparation

Status: 1.0.0 source preparation is maintained on `main`; it is not a published
plugin release. The owner authorized updating the new GitHub repository on
November 17, 2026. GitHub v0.7.6 and migration-helper-v0.1.0 remain unchanged.
No 1.0.0 tag/Release, SVN publication or site deployment is authorized by this
document. The owner reported WordPress.org approval of the 0.7.6 submission on
November 17, 2026.

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

## Publication Gates

Follow [Release and Update Channels](RELEASING.md). First publish and verify the
WordPress.org 1.0.0 ZIP, then generate the GitHub handoff from those exact bytes.
Do not attach an ordinary updater-enabled 1.0.0 development ZIP to the public
release. Existing GitHub 0.7.6 users must be offered a newer version that removes
the external updater and retains Japanese. Subsequent updates use WordPress.org.

The legacy migration helper remains separately versioned and pinned to original
DeFt 0.7.4 -> Article Composer 0.7.6. It is not changed to accept 1.0.0; a supported
migration reaches 0.7.6 first and then uses the normal update path.

## Draft Release Copy

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
