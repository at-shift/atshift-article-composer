import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {createHash} from 'node:crypto';
import {unzipSync} from 'fflate';
import {prepareHandoff} from '../scripts/prepare-wordpress-org-handoff.mjs';

const root = new URL('../', import.meta.url);
const {version} = JSON.parse(readFileSync(new URL('package.json', root)));
const directory = process.env.SF_RELEASE_DIR ? new URL('file://' + process.env.SF_RELEASE_DIR.replace(/\/$/, '') + '/') : new URL('.local/releases/', root);
const githubBytes = readFileSync(new URL(`atshift-article-composer-${version}.zip`, directory));
const regularFiles = archive => Object.fromEntries(Object.entries(archive).filter(([name]) => !name.endsWith('/')));
const github = regularFiles(unzipSync(githubBytes));
const orgBytes = readFileSync(new URL(`atshift-article-composer-${version}-wordpress-org.zip`, directory));
const orgRaw = unzipSync(orgBytes);
assert.ok(Object.keys(orgRaw).every(name => name.startsWith('atshift-article-composer/')));
assert.ok(orgRaw['atshift-article-composer/atshift-article-composer.php']);
assert.ok(!orgRaw['atshift-article-composer/atshift-deft.php']);
const org = regularFiles(orgRaw);
const text = bytes => Buffer.from(bytes).toString();

const githubOnly = name => name === 'atshift-article-composer/includes/class-github-updater.php';

test('distribution channels differ only in the GitHub updater', () => {
  const adapter = 'atshift-article-composer/includes/class-github-updater.php';
  assert.ok(github[adapter]);
  assert.equal(org[adapter], undefined);
  assert.deepEqual(Object.keys(github).filter(name => !githubOnly(name)).sort(), Object.keys(org).sort());
  for (const name of Object.keys(org)) {
    if (name.endsWith('/atshift-article-composer.php')) continue;
    assert.deepEqual(github[name], org[name], name);
  }
  const entry = 'atshift-article-composer/atshift-article-composer.php';
  assert.match(text(github[entry]), /Update URI: https:\/\/github.com\/at-shift\/atshift-article-composer/);
  assert.match(text(github[entry]), /GitHubUpdater::register/);
  assert.doesNotMatch(text(org[entry]), /Update URI:|GitHubUpdater/);
  for (const [name, bytes] of Object.entries(org)) {
    if (name.endsWith('.php')) assert.doesNotMatch(text(bytes), /update_plugins_github\.com|api\.github\.com\/repos/);
  }
});

test('release manifest pins exact installable ZIP bytes and requirements', () => {
  const m = JSON.parse(readFileSync(new URL('atshift-article-composer-update.json', directory)));
  assert.equal(m.schemaVersion, 1);
  assert.equal(m.channel, 'github');
  assert.equal(m.version, version);
  assert.equal(m.plugin, 'atshift-article-composer/atshift-article-composer.php');
  assert.equal(m.package, `https://github.com/at-shift/atshift-article-composer/releases/download/v${version}/atshift-article-composer-${version}.zip`);
  assert.equal(m.sha256, createHash('sha256').update(githubBytes).digest('hex'));
  assert.equal(m.bytes, githubBytes.length);
  const header = text(github['atshift-article-composer/atshift-article-composer.php']);
  assert.equal(m.requires, header.match(/Requires at least:\s*(\S+)/)[1]);
  assert.equal(m.requires_php, header.match(/Requires PHP:\s*(\S+)/)[1]);
});

test('both channels bundle identical Japanese catalogs and the Japanese guide', () => {
  const files = Object.keys(github).filter(name => name.includes('/languages/'));
  assert.ok(files.some(name => name.endsWith('-ja.mo')));
  assert.ok(files.some(name => /-ja-[a-f0-9]{32}\.json$/.test(name)));
  assert.ok(github['atshift-article-composer/GETTING-STARTED.ja.md']);
  assert.deepEqual(github['atshift-article-composer/GETTING-STARTED.ja.md'], org['atshift-article-composer/GETTING-STARTED.ja.md']);
  assert.deepEqual(Object.keys(org).filter(name => name.includes('/languages/')).sort(),
    files.sort());
  for (const name of files) assert.deepEqual(github[name], org[name], name);
  assert.deepEqual(github['atshift-article-composer/languages/atshift-article-composer.pot'], org['atshift-article-composer/languages/atshift-article-composer.pot']);
  assert.match(text(org['atshift-article-composer/atshift-article-composer.php']), /Text Domain: atshift-article-composer/);
  for (const file of ['atshift-article-composer.pot', 'atshift-article-composer-ja.po']) {
    assert.ok(text(github['atshift-article-composer/languages/' + file]).includes(`Project-Id-Version: atshift Article Composer ${version}\\n`));
  }
  for (const archive of [github, org]) assert.ok(!Object.keys(archive).some(name => /\.po~$|\.env$|node_modules|\.git\//.test(name)));
});

test('1.0.0 handoff rehearsal preserves the entire directory ZIP and Japanese files', () => {
  const handoff = prepareHandoff(orgBytes, { version, previousVersion: '0.7.6' });
  assert.equal(handoff.metadata.sha256, createHash('sha256').update(orgBytes).digest('hex'));
  assert.equal(handoff.metadata.bytes, orgBytes.length);
  assert.ok(org['atshift-article-composer/GETTING-STARTED.ja.md']);
  assert.ok(org['atshift-article-composer/languages/atshift-article-composer-ja.mo']);
  assert.equal(Object.keys(org).filter(name => /-ja-[a-f0-9]{32}\.json$/.test(name)).length, 4);
});

test('distributions omit upstream shell build tools and retain an exact dependency manifest', () => {
  for (const archive of [github, org]) {
    assert.ok(!Object.keys(archive).some(name => /\.sh$/i.test(name)));
    const base = 'atshift-article-composer/vendor/';
    const manifest = JSON.parse(text(archive[base + 'dependency-manifest.json']));
    assert.deepEqual(Object.keys(manifest.files).sort(), Object.keys(archive)
      .filter(name => name.startsWith(base) && name !== base + 'dependency-manifest.json')
      .map(name => name.slice(base.length)).sort());
    for (const [name, hash] of Object.entries(manifest.files)) {
      assert.equal(createHash('sha256').update(archive[base + name]).digest('hex'), hash, name);
    }
    assert.ok(archive[base + 'paragonie/random_compat/LICENSE']);
    assert.ok(archive[base + 'paragonie/random_compat/lib/random.php']);
  }
});

test('distributions exclude development-only and obsolete compatibility files', () => {
  for (const archive of [github, org]) {
    for (const name of Object.keys(archive)) {
      assert.ok(name.startsWith('atshift-article-composer/'), name);
      const relative = name.slice('atshift-article-composer/'.length);
      assert.doesNotMatch(relative, /^(?:src|server|scripts|tests|ai-kit|article-kit|node_modules|dist|public|wordpress-org|\.local|\.git)\//, name);
      assert.doesNotMatch(relative, /^docs\/branding\//, name);
      assert.doesNotMatch(relative, /^(?:package(?:-lock)?\.json|vite\.config\.js|index\.html)$/, name);
      assert.doesNotMatch(relative, /(?:~|\.(?:bak|old|orig|rej|sqlite|sqlite-shm|sqlite-wal))$/, name);
    }
    for (const file of ['includes/class-schema.php', 'includes/runtime.php', 'includes/class-private-blobs.php',
      'includes/oauth-entities.php', 'includes/oauth-repositories.php',
      'schema/snapshot.json', 'schema/snapshot-v2.json', 'schema/snapshot-v3.json']) {
      assert.ok(archive['atshift-article-composer/' + file], file);
    }
    assert.ok(archive['atshift-article-composer/docs/CONNECTIONS.md']);
    for (const removed of ['includes/legacy-guard.php', 'includes/class-prefix-migration.php', 'assets/icon-128x128.png', 'assets/icon-256x256.png']) assert.ok(!archive['atshift-article-composer/' + removed]);
    assert.ok(!Object.keys(archive).some(name => /defuse\/php-encryption\/(?:dist|bin)\//.test(name)));
  }
});
