import assert from "node:assert/strict";
import { mkdir, readFile, writeFile, readdir } from "node:fs/promises";
import { createHash } from "node:crypto";
import { zipSync, unzipSync } from "fflate";
import { resolve, sep } from "node:path";
import { pathToFileURL } from "node:url";

const root = new URL("../", import.meta.url);
const pkg = JSON.parse(await readFile(new URL("package.json", root), "utf8"));
const channel = process.env.SF_DISTRIBUTION || JSON.parse(await readFile(new URL("distribution.json", root), "utf8")).channel;
assert.ok(["github", "wordpress-org"].includes(channel), "Unknown distribution channel");
const slug = "atshift-article-composer";
const lock = JSON.parse(await readFile(new URL("package-lock.json", root), "utf8"));
assert.match(pkg.version, /^\d+\.\d+\.\d+(?:-[a-z0-9.-]+)?$/);
assert.equal(pkg.version, lock.version);
assert.equal(pkg.version, lock.packages[""].version);
const source = new URL("wordpress/atshift-article-composer/", root);
const entry = await readFile(new URL("atshift-article-composer.php", source), "utf8");
assert.equal(entry.match(/\* Version:\s*(\S+)/)?.[1], pkg.version);
assert.equal(entry.match(/const VERSION = '([^']+)'/)?.[1], pkg.version);
const names = [
  "README.md", "readme.txt", "atshift-article-composer.php", "admin.js", "admin.css",
  "assets/icon-20x20.png",
  "includes/plugin.php", "includes/bootstrap.php", "includes/channel-guard.php", "includes/class-schema.php",
  "includes/i18n.php",
  "includes/runtime.php",
  "oauth.css",
  "includes/class-oauth.php", "includes/class-oauth-store.php", "includes/class-oauth-consent.php",
  "includes/oauth-entities.php", "includes/oauth-repositories.php",
  "includes/class-snapshot.php", "includes/class-store.php", "includes/class-drafts.php",
  "includes/class-assets.php", "includes/class-private-blobs.php", "includes/class-access.php",
  "includes/class-media.php", "schema/snapshot-v2.json", "media-sample.json",
  "includes/class-transfer.php", "includes/class-review.php",
  "includes/class-catalog.php", "includes/catalog-page.php", "catalog.js",
  "includes/class-component-builder.php", "includes/class-component-appearance.php", "includes/class-catalog-context.php",
  "includes/class-component-usage.php",
  "includes/class-behaviors.php", "schema/snapshot-v3.json",
  "includes/class-onboarding.php",
  "includes/class-ai-workflow.php",
  "includes/class-connections.php", "includes/class-mcp.php", "includes/connections-page.php", "connections.js", "connection-profiles.js",
  "includes/class-publications.php", "includes/class-reservations.php", "includes/class-publication-media.php", "includes/class-post-types.php", "includes/class-taxonomies.php",
  "includes/class-public-structure.php", "includes/class-publication-access.php",
  "behaviors/fade-in-v1.css", "behaviors/image-zoom-v1.css", "behaviors/image-zoom-v1.js",
  "behaviors/manifest.json",
  "AI-GUIDE.md", "docs/CONNECTIONS.md",
  "sample.json", "schema/snapshot.json",
];
const files = {};
if (channel === "github") names.push("GETTING-STARTED.ja.md");
for (const name of names) files[`atshift-article-composer/${name}`] = new Uint8Array(await readFile(new URL(name, source)));
files['atshift-article-composer/assets/admin-logo.png'] = new Uint8Array(await readFile(new URL('assets/icon-128x128.png', source)));
if (channel === "github") {
  assert.ok(!entry.includes("Update URI:") && !entry.includes("GitHubUpdater"));
  const bootstrap = "\\Atshift\\Deft\\register_translations( __FILE__ );";
  assert.equal(entry.split(bootstrap).length, 2);
  const githubEntry = entry.replace(" * Version:", " * Update URI: https://github.com/at-shift/atshift-article-composer\n * Version:")
    .replace(bootstrap, bootstrap + "\nrequire_once __DIR__ . '/includes/class-github-updater.php';\n\\Atshift\\Deft\\GitHubUpdater::register( __FILE__ );");
  files["atshift-article-composer/atshift-article-composer.php"] = new TextEncoder().encode(githubEntry);
  files["atshift-article-composer/includes/class-github-updater.php"] = new Uint8Array(await readFile(new URL("distribution/github/class-github-updater.php", root)));
}
for (const entry of await readdir(new URL("languages/", source), { withFileTypes: true })) {
  if (!entry.isFile() || !/^(?:README\.md|atshift-article-composer\.pot|atshift-article-composer-[A-Za-z][A-Za-z0-9_@-]*\.(?:po|mo)|atshift-article-composer-[A-Za-z][A-Za-z0-9_@-]*-[a-f0-9]{32}\.json)$/.test(entry.name)) continue;
  if (channel === "wordpress-org" && !["README.md", "atshift-article-composer.pot"].includes(entry.name)) continue;
  files[`atshift-article-composer/languages/${entry.name}`] = new Uint8Array(await readFile(new URL(`languages/${entry.name}`, source)));
}
for (const name of ["composer.json", "composer.lock", "LICENSE"]) files[`atshift-article-composer/${name}`] = new Uint8Array(await readFile(new URL(name, root)));
const manifestBytes = await readFile(new URL("vendor/dependency-manifest.json", source));
const manifest = JSON.parse(manifestBytes);
assert.equal(manifest.minimumPhp, "8.2.0");
assert.equal(manifest.namespacePrefix, "Atshift\\Deft\\Vendor\\");
assert.equal(manifest.composerLockSha256, createHash("sha256").update(await readFile(new URL("composer.lock", root))).digest("hex"));
assert.equal(manifest.scoperConfigSha256, createHash("sha256").update(await readFile(new URL("scoper.inc.php", root))).digest("hex"));
// Upstream PHAR build tooling is not runtime code and is rejected by the directory uploader.
const excludedVendorFiles = new Set(["paragonie/random_compat/build-phar.sh"]);
const packagedManifest = { ...manifest, files: {} };
for (const [name, hash] of Object.entries(manifest.files)) {
  assert.ok(!name.startsWith("/") && !name.split("/").includes("..") && !name.includes("\\"));
  const bytes = await readFile(new URL(`vendor/${name}`, source));
  assert.equal(createHash("sha256").update(bytes).digest("hex"), hash, `Rebuild PHP dependencies: ${name}`);
  if (excludedVendorFiles.has(name) || name.startsWith('defuse/php-encryption/dist/') || name.startsWith('defuse/php-encryption/bin/')) continue;
  packagedManifest.files[name] = hash;
  files[`atshift-article-composer/vendor/${name}`] = new Uint8Array(bytes);
}
files["atshift-article-composer/vendor/dependency-manifest.json"] = new TextEncoder().encode(JSON.stringify(packagedManifest, null, 2) + "\n");
const archive = zipSync(Object.fromEntries(Object.entries(files).map(([name, bytes]) => [
  name, [bytes, { mtime: new Date(1980, 0, 1, 0, 0, 0) }],
])), { level: 6 });
const unpacked = unzipSync(archive);
assert.deepEqual(Object.keys(unpacked).sort(), Object.keys(files).sort());
for (const [name, bytes] of Object.entries(files)) assert.deepEqual(unpacked[name], bytes);
const output = process.env.SF_PACKAGE_DIR
  ? pathToFileURL(resolve(process.env.SF_PACKAGE_DIR) + sep)
  : new URL(".local/releases/", root);
await mkdir(output, { recursive: true });
const filename = `${slug}-${pkg.version}${channel === "wordpress-org" ? "-wordpress-org" : ""}.zip`;
await writeFile(new URL(filename, output), archive);
const checksum = createHash("sha256").update(archive).digest("hex");
await writeFile(new URL(`${filename}.sha256`, output), `${checksum}  ${filename}\n`);
if (channel === "github") {
  const readme = await readFile(new URL("readme.txt", source), "utf8");
  const metadata = {
    schemaVersion: 1, channel, plugin: "atshift-article-composer/atshift-article-composer.php", version: pkg.version,
    requires: entry.match(/Requires at least:\s*(\S+)/)[1],
    requires_php: entry.match(/Requires PHP:\s*(\S+)/)[1],
    tested: readme.match(/Tested up to:\s*(\S+)/)[1],
    url: `https://github.com/at-shift/atshift-article-composer/releases/tag/v${pkg.version}`,
    package: `https://github.com/at-shift/atshift-article-composer/releases/download/v${pkg.version}/${filename}`,
    bytes: archive.length, sha256: checksum,
  };
  await writeFile(new URL("atshift-article-composer-update.json", output), JSON.stringify(metadata, null, 2) + "\n");
}
console.log(`${channel}: ${filename}: ${Object.keys(files).length} files, ${archive.length} bytes, SHA-256 ${checksum}`);
