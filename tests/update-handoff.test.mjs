import test from 'node:test';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { zipSync } from 'fflate';
import { prepareHandoff, officialPackageUrl } from '../scripts/prepare-wordpress-org-handoff.mjs';

const slug = 'atshift-article-composer';
const header = `<?php
/**
 * Version: 0.7.7
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Text Domain: ${slug}
 */
const VERSION = '0.7.7';
`;
const files = {
  [`${slug}/${slug}.php`]: Buffer.from(header),
  [`${slug}/readme.txt`]: Buffer.from('Stable tag: 0.7.7\nTested up to: 7.1\n'),
};
const options = { version: '0.7.7', previousVersion: '0.7.6' };
test('handoff metadata delivers the unchanged official ZIP through the same GitHub identity', () => {
  const zip = zipSync(files), result = prepareHandoff(zip, options);
  assert.equal(result.metadata.plugin, `${slug}/${slug}.php`);
  assert.equal(result.metadata.channel, 'github');
  assert.equal(result.metadata.sha256, createHash('sha256').update(zip).digest('hex'));
  assert.equal(result.metadata.bytes, zip.length);
  assert.equal(result.officialUrl, `https://downloads.wordpress.org/plugin/${slug}.0.7.7.zip`);
});
test('handoff refuses equal, older, malformed and non-release versions', () => {
  for (const previousVersion of ['0.7.7', '0.7.8', '1.0.0', '0.7.6.1', '../x', '0.7.6-beta']) {
    assert.throws(() => prepareHandoff(zipSync(files), { ...options, previousVersion }));
  }
  assert.throws(() => officialPackageUrl('0.7.7?redirect=x'));
});
test('handoff refuses other plugin identities, versions and any remaining GitHub updater', () => {
  for (const changed of [
    { ...files, 'other/plugin.php': Buffer.from('') },
    { ...files, [`${slug}/../escape.php`]: Buffer.from('') },
    { ...files, [`${slug}/${slug}.php`]: Buffer.from(header.replace('Version: 0.7.7', 'Version: 0.7.6')) },
    { ...files, [`${slug}/readme.txt`]: Buffer.from('Stable tag: 0.7.6\nTested up to: 7.1\n') },
    { ...files, [`${slug}/${slug}.php`]: Buffer.from(header + '\n// Update URI: https://example.invalid') },
    { ...files, [`${slug}/includes/class-github-updater.php`]: Buffer.from('') },
    { ...files, [`${slug}/includes/hidden.php`]: Buffer.from('<?php add_filter("update_plugins_github.com", "x");') },
  ]) assert.throws(() => prepareHandoff(zipSync(changed), options));
});
