import { spawnSync, spawn } from 'node:child_process';
import { mkdtempSync, mkdirSync, cpSync, writeFileSync, readFileSync, openSync, closeSync } from 'node:fs';
import { randomBytes } from 'node:crypto';
import { once } from 'node:events';
import { resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import assert from 'node:assert/strict';

const repo = fileURLToPath(new URL('../', import.meta.url));
const { AC_WP_CORE: core, AC_WP_FIXTURES: fixtures, WP_CLI: wpcli, AC_MCP_ADAPTER: adapter, AC_OLD_ZIP: oldZip, AC_NEW_ZIP: newZip, AC_PLAYWRIGHT: playwright } = process.env;
for (const path of [core, fixtures, wpcli, adapter, oldZip, newZip, playwright]) assert.ok(path && resolve(path) === path, 'Supply absolute fixture paths.');
const { chromium } = await import(pathToFileURL(playwright).href);
const out = mkdtempSync('/private/tmp/ac-migration-'), wp = out + '/wordpress';
const db = 'ac_migration_' + randomBytes(5).toString('hex'), origin = 'http://127.0.0.1:8131';
const php = process.env.PHP_BINARY || 'php';
const password = randomBytes(32).toString('hex');
const env = { ...process.env, WP_CLI_CACHE_DIR: out + '/cache' };
let owns = false, passed = false, deleted = false, server, exited, fd, browser;
const results = [];
function run(name, command, args, extra = {}) {
  const r = spawnSync(command, args, { cwd: repo, env, encoding: 'utf8', timeout: 180000, maxBuffer: 8 * 1024 * 1024, ...extra });
  writeFileSync(out + '/' + name + '.stdout', r.stdout || '', { mode: 0o600 });
  writeFileSync(out + '/' + name + '.stderr', r.stderr || '', { mode: 0o600 });
  if (r.error) throw r.error;
  assert.equal(r.status, 0, name + ': ' + (r.stdout + r.stderr).slice(-7000));
  results.push(name); console.log(name + ': passed'); return r.stdout;
}
const cli = (name, args, extra) => run(name, php, [wpcli, ...args, '--path=' + wp, '--no-color'], extra);
const helper = (name, args) => cli(name, ['atshift-composer-migration', ...args, '--user=migration_admin']);
const phase = name => cli(name, ['eval-file', repo + '/tests/wordpress-migration.php'], { env: { ...env, AC_MIGRATION_PHASE: name } });
try {
  assert.equal(await fetch(origin, { signal: AbortSignal.timeout(500) }).catch(() => null), null, 'Do not replace an existing server.');
  mkdirSync(wp);
  run('copy-core', 'rsync', ['-a', '--exclude=wp-config.php', '--exclude=wp-content/', '--exclude=.htaccess', '--exclude=*.log', core + '/', wp + '/']);
  mkdirSync(wp + '/wp-content/plugins', { recursive: true }); mkdirSync(wp + '/wp-content/themes', { recursive: true });
  cpSync(fixtures + '/wp-content/themes/twentytwentyfive', wp + '/wp-content/themes/twentytwentyfive', { recursive: true });
  cpSync(adapter, wp + '/wp-content/plugins/mcp-adapter', { recursive: true });
  cli('config', ['config', 'create', '--dbname=' + db, '--dbuser=root', '--dbpass=', '--dbhost=127.0.0.1:3306', '--dbprefix=acm_', '--skip-check', '--skip-salts', '--extra-php'], {
    input: "define('WP_ENVIRONMENT_TYPE','local');\ndefine('DISABLE_WP_CRON',true);\ndefine('WP_HTTP_BLOCK_EXTERNAL',true);\ndefine('WP_DEBUG',true);\ndefine('WP_DEBUG_LOG',true);\ndefine('WP_DEBUG_DISPLAY',false);\n",
  });
  cli('create-db', ['db', 'create']); owns = true;
  cli('install', ['core', 'install', '--url=' + origin, '--title=Disposable migration QA', '--admin_user=migration_admin', '--admin_password=' + password, '--admin_email=qa@example.invalid', '--skip-email']);
  cli('theme', ['theme', 'activate', 'twentytwentyfive']); cli('adapter', ['plugin', 'activate', 'mcp-adapter']);
  run('package-helper', process.execPath, ['scripts/package-migration.mjs', oldZip, newZip, out + '/packages']);
  cli('old-package', ['plugin', 'install', oldZip, '--activate']);
  cli('new-inactive', ['plugin', 'install', newZip]);
  cli('helper-package', ['plugin', 'install', out + '/packages/atshift-composer-migration-0.1.0.zip', '--activate']);
  phase('seed');
  cli('pause-old', ['plugin', 'deactivate', 'atshift-deft']);
  phase('guards');
  cli('backup-fixture', ['db', 'export', out + '/before.sql', '--skip-plugins', '--skip-themes']);
  const before = JSON.parse(helper('inspect-cli', ['inspect'])); assert.equal(before.supported, true);
  helper('prepare-cli', ['prepare', '--fingerprint=' + before.fingerprint, '--backup-reference=disposable-rehearsal', '--confirm-preconditions']);
  phase('prepared'); helper('activate-cli', ['activate']);
  cli('interrupted-activation-receipt', ['eval', "$r=get_option('atshift_composer_migration_074_076');$r['stage']='activating';update_option('atshift_composer_migration_074_076',$r,false);"]);
  helper('verify-cli', ['verify']); helper('verify-idempotent', ['verify']); phase('behavior');
  // Restore only this owned disposable fixture to exercise the browser route independently.
  assert.equal(cli('confirm-restore-db', ['config', 'get', 'DB_NAME']).trim(), db);
  cli('restore-fixture', ['db', 'import', out + '/before.sql', '--skip-plugins', '--skip-themes']);
  fd = openSync(out + '/server.log', 'a', 0o600);
  server = spawn(php, ['-S', '127.0.0.1:8131', '-t', wp], { env, stdio: ['ignore', fd, fd] }); exited = once(server, 'exit');
  let ready = false;
  for (let i = 0; i < 50; i++) { if ((await fetch(origin + '/wp-login.php').catch(() => null))?.ok) { ready = true; break; } await new Promise(r => setTimeout(r, 100)); }
  assert.ok(ready, 'The disposable HTTP server must become ready.');
  browser = await chromium.launch({ channel: 'chrome', headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
  const page = await context.newPage(), errors = []; page.on('pageerror', e => errors.push(e.message));
  await page.goto(origin + '/wp-login.php'); await page.waitForLoadState('networkidle');
  await page.locator('#user_login').fill('migration_admin'); await page.locator('#user_pass').fill(password);
  assert.equal(new URL(await page.locator('#loginform').getAttribute('action')).origin, origin, 'Login must stay on the disposable site.');
  try { await Promise.all([page.waitForURL('**/wp-admin/**'), page.locator('#wp-submit').click()]); }
  catch (error) {
    await page.screenshot({ path: out + '/login-failure.png' });
    writeFileSync(out + '/login-failure.txt', await page.locator('body').innerText(), { mode: 0o600 });
    throw error;
  }
  const adminPost = origin + '/wp-admin/admin-post.php';
  const csrf = await context.request.post(adminPost, { form: { action: 'atshift_composer_migrate', operation: 'prepare', fingerprint: before.fingerprint, backup: 'unauthorized' } }); assert.equal(csrf.status(), 403);
  const anonymous = await browser.newContext();
  const denied = await anonymous.request.post(adminPost, { form: { action: 'atshift_composer_migrate', operation: 'prepare' } }); assert.ok([400, 403].includes(denied.status())); await anonymous.close();
  await page.goto(origin + '/wp-admin/tools.php?page=atshift-composer-migration');
  assert.equal(await page.locator('.notice-error.inline').count(), 0);
  await page.waitForLoadState('networkidle'); await page.screenshot({ path: out + '/migration-desktop.png', fullPage: true, animations: 'disabled' });
  await page.setViewportSize({ width: 390, height: 844 });
  assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));
  assert.equal(await page.getByRole('heading', { name: 'Article Composer migration', exact: true }).count(), 1);
  await page.screenshot({ path: out + '/migration-mobile.png', animations: 'disabled' });
  await page.locator('#backup').scrollIntoViewIfNeeded();
  await page.screenshot({ path: out + '/migration-mobile-controls.png', animations: 'disabled' });
  await page.setViewportSize({ width: 1440, height: 1000 });
  const download = page.waitForEvent('download'); await page.getByRole('button', { name: 'Download diagnostic report' }).click();
  const file = await download; await file.saveAs(out + '/browser-report.json');
  const report = JSON.parse(readFileSync(out + '/browser-report.json')); assert.equal(report.supported, true);
  assert.ok(!JSON.stringify(report).includes('secret_hash') && !JSON.stringify(report).includes('Migration <test>'));
  await page.locator('#backup').fill('verified-copy-backup'); await page.locator('[name=confirmed]').check();
  await page.getByRole('button', { name: 'Prepare migration', exact: true }).click(); await page.getByRole('heading', { name: 'State: prepared', exact: true }).waitFor();
  await page.getByRole('button', { name: 'Activate Article Composer', exact: true }).click(); await page.getByRole('heading', { name: 'State: activated', exact: true }).waitFor();
  await page.getByRole('button', { name: 'Verify saved data', exact: true }).click(); await page.getByRole('heading', { name: 'State: verified', exact: true }).waitFor();
  await page.waitForLoadState('networkidle'); await page.screenshot({ path: out + '/migration-verified.png', fullPage: true, animations: 'disabled' });
  assert.deepEqual(errors, []); results.push('browser-csrf-anonymous-download-prepare-activate-verify-desktop-mobile');
  await page.goto(origin + '/wp-admin/admin.php?page=structured-fields');
  assert.ok((await page.locator('body').innerText()).includes('migration-draft')); results.push('browser-migrated-draft-list');
  cli('deactivate-helper-for-public-access', ['plugin', 'deactivate', 'atshift-composer-migration']);
  const publicUrl = cli('public-fixture-url', ['eval', "echo get_option('ac_migration_fixture')['url'];"]).trim();
  const publicPage = await context.newPage(); await publicPage.goto(publicUrl);
  const mediaUrls = await publicPage.locator('img[src*="sf_public_media"],a[href*="sf_public_media"]').evaluateAll(elements => elements.map(e => e.src || e.href));
  assert.equal(mediaUrls.length, 2);
  for (const url of mediaUrls) {
    assert.equal(new URL(url).origin, origin);
    for (const method of ['GET', 'HEAD']) {
      const response = await fetch(url, { method }); assert.equal(response.status, 200);
      assert.ok(Number(response.headers.get('content-length')) > 0);
    }
  }
  results.push('public-managed-image-pdf-get-head-after-helper-removal');
  console.log('Browser migration route: passed');
  passed = true;
} finally {
  if (browser) await browser.close(); if (server && server.exitCode === null) { server.kill('SIGTERM'); await exited; } if (fd !== undefined) closeSync(fd);
  if (owns) { assert.equal(cli('confirm-cleanup-db', ['config', 'get', 'DB_NAME']).trim(), db); cli('drop-disposable-db', ['db', 'drop', '--yes', '--skip-plugins', '--skip-themes']); deleted = true; }
  writeFileSync(out + '/summary.json', JSON.stringify({ passed, results, databaseDeleted: deleted, out }, null, 2), { mode: 0o600 }); console.log('Evidence: ' + out);
}
