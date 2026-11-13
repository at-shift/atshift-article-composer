import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { unzipSync } from 'fflate';

const slug = 'atshift-article-composer';
const repository = `https://github.com/at-shift/${slug}`;
export function officialPackageUrl(version) {
  assert.match(version, /^\d{1,6}\.\d{1,6}\.\d{1,6}$/);
  return `https://downloads.wordpress.org/plugin/${slug}.${version}.zip`;
}

// Reuse the official ZIP byte-for-byte; no external updater enters the target package.
export function prepareHandoff(bytes, { version, previousVersion }) {
  const officialUrl = officialPackageUrl(version);
  officialPackageUrl(previousVersion);
  const current = version.split('.').map(Number), previous = previousVersion.split('.').map(Number);
  const different = current.findIndex((part, index) => part !== previous[index]);
  assert.ok(different >= 0 && current[different] > previous[different], 'Handoff must be newer than the highest installed GitHub release.');
  assert.ok(bytes.length > 0 && bytes.length <= 26214400, 'Invalid ZIP size.');
  const files = unzipSync(bytes), names = Object.keys(files);
  assert.ok(names.length > 0);
  for (const name of names) {
    assert.ok(name.startsWith(slug + '/') && !name.includes('\\') && !name.split('/').includes('..'), 'Unexpected ZIP path: ' + name);
    assert.ok(!/class-github-updater\.php$/.test(name), 'Handoff must not contain the GitHub updater.');
    if (name.endsWith('.php')) assert.doesNotMatch(Buffer.from(files[name]).toString(), /GitHubUpdater|update_plugins_github\.com|api\.github\.com\/repos/);
  }
  const entry = Buffer.from(files[`${slug}/${slug}.php`] || []).toString();
  const readme = Buffer.from(files[`${slug}/readme.txt`] || []).toString();
  assert.equal(entry.match(/\* Version:\s*(\S+)/)?.[1], version, 'Official plugin version mismatch.');
  assert.equal(entry.match(/const VERSION = '([^']+)'/)?.[1], version);
  assert.equal(entry.match(/Text Domain:\s*(\S+)/)?.[1], slug);
  assert.equal(readme.match(/Stable tag:\s*(\S+)/)?.[1], version);
  assert.doesNotMatch(entry, /Update URI:/);
  const filename = `${slug}-${version}.zip`;
  const metadata = {
    schemaVersion: 1, channel: 'github', plugin: `${slug}/${slug}.php`, version,
    requires: entry.match(/Requires at least:\s*(\S+)/)?.[1],
    requires_php: entry.match(/Requires PHP:\s*(\S+)/)?.[1],
    tested: readme.match(/Tested up to:\s*(\S+)/)?.[1],
    url: `${repository}/releases/tag/v${version}`,
    package: `${repository}/releases/download/v${version}/${filename}`,
    bytes: bytes.length, sha256: createHash('sha256').update(bytes).digest('hex'),
  };
  for (const key of ['requires', 'requires_php', 'tested']) assert.match(metadata[key] || '', /^\d+\.\d+(?:\.\d+)?$/);
  return { filename, metadata, officialUrl };
}

export async function writeHandoff(bytes, options, directory) {
  const result = prepareHandoff(bytes, options);
  await mkdir(directory, { recursive: true });
  await writeFile(resolve(directory, result.filename), bytes);
  await writeFile(resolve(directory, result.filename + '.sha256'), `${result.metadata.sha256}  ${result.filename}\n`);
  await writeFile(resolve(directory, `${slug}-update.json`), JSON.stringify(result.metadata, null, 2) + '\n');
  await writeFile(resolve(directory, 'wordpress-org-handoff.json'), JSON.stringify({
    officialUrl: result.officialUrl, previousVersion: options.previousVersion,
    version: options.version, sha256: result.metadata.sha256,
  }, null, 2) + '\n');
  return result;
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
  const [version, previousVersion, output] = process.argv.slice(2);
  assert.ok(output && process.argv.length === 5, 'Usage: node scripts/prepare-wordpress-org-handoff.mjs VERSION PREVIOUS_GITHUB_VERSION OUTPUT_DIRECTORY');
  const url = officialPackageUrl(version);
  const response = await fetch(url, { redirect: 'error', signal: AbortSignal.timeout(60000) });
  assert.ok(response.ok, 'The official WordPress.org ZIP is not available. Do not switch channels.');
  const parts = []; let size = 0;
  for await (const part of response.body) {
    size += part.length;
    assert.ok(size <= 26214400, 'Official ZIP exceeds the allowed size.');
    parts.push(part);
  }
  const result = await writeHandoff(Buffer.concat(parts), { version, previousVersion }, resolve(output));
  console.log(JSON.stringify(result, null, 2));
}
