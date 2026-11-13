import assert from 'node:assert/strict';
import { readFile, writeFile, mkdir, readdir } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { resolve, join } from 'node:path';
import { unzipSync, zipSync } from 'fflate';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = new URL('../', import.meta.url);
const [oldZip, newZip, output = '.local/migration'] = process.argv.slice(2);
assert.ok(oldZip && newZip, 'Usage: node scripts/package-migration.mjs OLD_074_ZIP NEW_076_GITHUB_ZIP [OUTPUT]');
const inputs = [
  ['old', oldZip, 'atshift-deft', '390477f887993c1244c7ad2051f55df8c7ea56c885a8f1b3d8d09f16613f23c5'],
  ['new', newZip, 'atshift-article-composer', 'cab3b51de465228cc567294f46c6a3ec0c97475d075eca32f46064e8c6bf6ce8'],
];
const hash = bytes => createHash('sha256').update(bytes).digest('hex');
const manifest = {};
for (const [key, path, slug, digest] of inputs) {
  const bytes = await readFile(path);
  assert.equal(hash(bytes), digest, `Use the verified published ${key} ZIP, not a rebuilt or edited package.`);
  const files = {};
  for (const [name, data] of Object.entries(unzipSync(bytes))) {
    assert.ok(name.startsWith(slug + '/') && !name.includes('\\') && !name.split('/').includes('..'));
    if (name.endsWith('/')) continue;
    files[name.slice(slug.length + 1)] = hash(data);
  }
  manifest[key] = { slug, zipSha256: digest, files };
}
const prefix = 'atshift-composer-migration/';
const files = {};
async function add(dir, relative = '') {
  for (const entry of await readdir(dir, { withFileTypes: true })) {
    const name = relative + entry.name;
    if (entry.isDirectory()) await add(new URL(entry.name + '/', dir), name + '/');
    else if (entry.isFile()) {
      if (entry.name.endsWith('.php')) execFileSync(process.env.PHP_BINARY || 'php', ['-l', fileURLToPath(new URL(entry.name, dir))]);
      files[prefix + name] = new Uint8Array(await readFile(new URL(entry.name, dir)));
    }
    else throw new Error('Symlinks are not package inputs');
  }
}
await add(new URL('migration/atshift-composer-migration/', root));
files[prefix + 'packages.json'] = new TextEncoder().encode(JSON.stringify(manifest, null, 2) + '\n');
files[prefix + 'AI-MIGRATION.md'] = new Uint8Array(await readFile(new URL('docs/AI-MIGRATION.md', root)));
files[prefix + 'MIGRATION.md'] = new Uint8Array(await readFile(new URL('docs/MIGRATION.md', root)));
files[prefix + 'LICENSE'] = new Uint8Array(await readFile(new URL('LICENSE', root)));
const zip = zipSync(Object.fromEntries(Object.entries(files).map(([name, bytes]) => [name, [bytes, { mtime: new Date(1980, 0, 1) }]])), { level: 6 });
const dir = resolve(output), name = 'atshift-composer-migration-0.1.0.zip';
await mkdir(dir, { recursive: true });
await writeFile(join(dir, name), zip);
await writeFile(join(dir, name + '.sha256'), `${hash(zip)}  ${name}\n`);
console.log(`${join(dir, name)}: ${zip.length} bytes, SHA-256 ${hash(zip)}`);
