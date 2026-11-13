import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { once } from 'node:events';
import { randomBytes, createHash } from 'node:crypto';
import { cpSync, mkdirSync, readFileSync, writeFileSync, existsSync, symlinkSync, openSync, closeSync, unlinkSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const source = fileURLToPath(new URL('../', import.meta.url));
const base = process.env.AC_TEST_OUTPUT, core = process.env.AC_WP_CORE, cli = process.env.WP_CLI;
const fixtures = process.env.AC_WP_FIXTURES || core;
const dependencies = process.env.AC_NODE_MODULES, upstream = process.env.AC_MEMBERS_PLUGINS;
for (const path of [base, core, cli, dependencies, upstream]) assert.ok(path && resolve(path) === path, 'Supply absolute fixture/dependency paths.');
const out = `${base}/membership-${randomBytes(5).toString('hex')}`, repo = `${out}/checkout`, wp = `${repo}/.local/wordpress`;
const db = `sf_deft_members_${randomBytes(6).toString('hex')}`, origin = 'http://127.0.0.1:8136';
const php = process.env.PHP_BINARY || 'php';
const env = {...process.env, WP_CLI_CACHE_DIR:`${out}/cache`, SF_PACKAGE_DIR:`${out}/packages`, SF_DISTRIBUTION:'wordpress-org'};
const summary = {database:db, checks:[], databaseDeleted:false};
let owned = false, server, exited, fd;
mkdirSync(out, {recursive:true});
function run(name, command, args, options = {}) {
  const result = spawnSync(command, args, {cwd:repo, env, encoding:'utf8', timeout:300000, maxBuffer:16*1024*1024, ...options});
  writeFileSync(`${out}/${name}.log`, `${result.stdout || ''}\n${result.stderr || ''}`, {mode:0o600});
  if (result.error || result.status !== 0) throw new Error(`${name}: ${result.error || (result.stderr + result.stdout).slice(-4000)}`);
  return result.stdout.trim();
}
const wpcli = (name,args,options) => run(name,php,[cli,...args,`--path=${wp}`,'--no-color'],options);
const fixture = (phase,mode='',user=0) => JSON.parse(wpcli(`fixture-${phase}-${mode}-${user}`,['eval-file',`${repo}/tests/wordpress-memberships-fixture.php`],{env:{...env,AC_MEMBERS_PHASE:phase,AC_MEMBERS_MODE:mode,AC_MEMBERS_USER:String(user)}}));
const check = (name, ok) => {assert.ok(ok,name);summary.checks.push(name);};
let data,cookies;
async function request(path,cookie='',method='GET') {
  return fetch(new URL(path,origin),{redirect:'manual',method,headers:cookie?{Cookie:cookie}:{},signal:AbortSignal.timeout(15000)});
}
async function verify(name,allowed,{page=true}={}) {
  for (const [who,can] of Object.entries(allowed)) {
    const cookie = who === 'guest' ? '' : cookies[who];
    for (const [key,meta] of Object.entries(data.files)) {
      const url = `/?sf_public_media=${data.publication}&sf_media_key=${key}`;
      for (const method of ['GET','HEAD']) {
        const response = await request(url,cookie,method);
        check(`${name}: ${who} ${meta.mime} ${method}`,response.status === (can?200:404));
        check(`${name}: ${who} ${method} private cache`,/no-store/.test(response.headers.get('cache-control')));
        if(can && method==='GET') check(`${name}: ${who} exact bytes`,createHash('sha256').update(Buffer.from(await response.arrayBuffer())).digest('hex')===meta.sha256);
        else await response.arrayBuffer();
      }
    }
    if(page) {
      const response = await request(`/?p=${data.post}`,cookie); const html = await response.text();
      const match = html.match(/<script type="application\/json" id="atshift-deft-structure">(.*?)<\/script>/s);
      check(`${name}: ${who} structure visibility`,Boolean(match)===can);
      if(match) check(`${name}: ${who} visible projection`,JSON.parse(match[1]).content.length>0);
      if(!can) check(`${name}: ${who} no metadata copy`,!html.includes('id="atshift-deft-json-ld"'));
    }
  }
  console.log(`${name}: passed`);
}
try {
  assert.equal(await fetch(origin,{signal:AbortSignal.timeout(500)}).catch(()=>null),null,'Test port is already occupied.');
  mkdirSync(repo,{recursive:true});
  run('copy','rsync',['-a','--exclude=/.git/','--exclude=/.local/','--exclude=/node_modules','--exclude=/dist/',`${source}/`,`${repo}/`]);
  symlinkSync(dependencies,`${repo}/node_modules`);
  run('core','rsync',['-a','--exclude=/wp-config.php','--exclude=/wp-content/','--exclude=/.htaccess',`${core}/`,`${wp}/`]);
  mkdirSync(`${wp}/wp-content/plugins`,{recursive:true});mkdirSync(`${wp}/wp-content/themes`,{recursive:true});
  cpSync(`${fixtures}/wp-content/themes/twentytwentyfive`,`${wp}/wp-content/themes/twentytwentyfive`,{recursive:true});
  for(const name of ['members','ultimate-member','atshift-members']) cpSync(`${upstream}/${name}`,`${wp}/wp-content/plugins/${name}`,{recursive:true});
  cpSync(`${repo}/tests/wordpress-memberships-unknown.php`,`${wp}/wp-content/plugins/unverified.php`);
  wpcli('config',['config','create',`--dbname=${db}`,'--dbuser=root','--dbpass=','--dbhost=127.0.0.1:3306','--skip-salts','--skip-check','--extra-php'],{input:"define('WP_ENVIRONMENT_TYPE','local');\ndefine('DISABLE_WP_CRON',true);\ndefine('WP_HTTP_BLOCK_EXTERNAL',true);\ndefine('WP_DEBUG',true);\ndefine('WP_DEBUG_LOG',true);\ndefine('WP_DEBUG_DISPLAY',false);\n"});
  wpcli('db-create',['db','create']);owned=true;
  wpcli('install',['core','install',`--url=${origin}`,'--title=Disposable membership tests','--admin_user=membership_admin',`--admin_password=${randomBytes(32).toString('hex')}`,'--admin_email=qa@example.invalid','--skip-email']);
  wpcli('theme',['theme','activate','twentytwentyfive']);
  summary.wordpress=wpcli('core-version',['core','version']);summary.php=run('php-version',php,['-r','echo PHP_VERSION;']);
  run('package',process.execPath,['scripts/package-wordpress.mjs']);
  const version = JSON.parse(readFileSync(`${repo}/package.json`)).version;
  wpcli('plugin',['plugin','install',`${out}/packages/atshift-article-composer-${version}-wordpress-org.zip`,'--activate']);
  data = fixture('seed'); cookies = fixture('cookies');
  const structureLog=wpcli('structure-tests',['eval-file',`${repo}/tests/wordpress-public-structure.php`]);
  summary.structureChecks=Number(structureLog.match(/Structure checks: (\d+)/)?.[1]);
  assert.ok(summary.structureChecks>0,'Structure regression checks must execute.');
  fd = openSync(`${out}/server.log`,'a',0o600);server=spawn(php,['-S','127.0.0.1:8136','-t',wp],{cwd:repo,env,stdio:['ignore',fd,fd]});exited=once(server,'exit');
  for(let i=0;i<50;i++){if(await request('/wp-login.php').catch(()=>null))break;await new Promise(r=>setTimeout(r,100));}
  await verify('public',{guest:true,reader:true,other:true,admin:true});
  check('raw structure REST endpoint removed',(await request(`/?rest_route=/structured-fields/v1/public-structure/${data.post}`)).status===404);
  for(const [plugin,mode] of [['atshift-members','atshift'],['members','members'],['ultimate-member','um']]) {
    wpcli(`activate-${plugin}`,['plugin','activate',plugin]);fixture('configure',mode);cookies=fixture('cookies');
    await verify(mode,{guest:false,reader:true,other:false,admin:true});
    if(mode==='atshift') {fixture('configure','audience');await verify('missing audience provider',{guest:false,reader:false,other:false,admin:true});}
    if(mode==='members') {fixture('configure','members-private');await verify('private site',{guest:false,reader:true,other:true,admin:true});}
    if(mode==='um') {
      fixture('configure','um-category');await verify('UM category',{guest:false,reader:true,other:false,admin:true});
      fixture('configure','um-private');await verify('UM global',{guest:false,reader:true,other:true,admin:true});
    }
    fixture('configure','public');wpcli(`deactivate-${plugin}`,['plugin','deactivate',plugin]);
  }
  wpcli('activate-combined',['plugin','activate','atshift-members','members']);fixture('configure','combined');cookies=fixture('cookies');
  await verify('deny wins across providers',{guest:false,reader:false,other:false,admin:true});
  fixture('configure','public');wpcli('deactivate-combined',['plugin','deactivate','atshift-members','members']);cookies=fixture('cookies');
  for(const mode of ['partial','block','block-spaces','block-tab','block-newline','password','withdrawn']) {fixture('configure',mode);await verify(mode,{guest:false,reader:false,admin:false});}
  fixture('configure','removed-media');await verify('removed media binding',{guest:false,reader:false,admin:false},{page:false});
  fixture('configure','public');wpcli('unknown-plugin',['plugin','activate','unverified']);
  await verify('unverified plugin',{guest:false,reader:false,admin:false});
  const notice = await (await request('/wp-admin/admin.php?page=structured-fields',cookies.admin)).text();
  check('admin sees hold reason',notice.includes('plugin:unverified.php') && notice.includes('delivery are paused'));
  if (process.env.AC_BROWSER_CHECK) run('browser',process.execPath,[process.env.AC_BROWSER_CHECK],{env:{...env,AC_BROWSER_OUTPUT:out,AC_BROWSER_ORIGIN:origin}});
  const ordinary = await (await request(`/?p=${data.post}`)).text();
  check('unknown integration preserves normal HTML',ordinary.includes('PRIVATE_IMAGE_SENTINEL'));
  check('unknown integration preserves normal article styling',ordinary.includes(`atshift-deft-publication-${data.post}-inline-css`));
  wpcli('unknown-off',['plugin','deactivate','unverified']);
  cpSync(`${repo}/tests/wordpress-memberships-dropin.php`,`${wp}/wp-content/fatal-error-handler.php`);
  await verify('unverified drop-in',{guest:false,reader:false,admin:false});
  const dropinNotice = await (await request('/wp-admin/admin.php?page=structured-fields',cookies.admin)).text();
  check('admin sees drop-in hold reason',dropinNotice.includes('dropin:fatal-error-handler.php'));
  unlinkSync(`${wp}/wp-content/fatal-error-handler.php`);
  wpcli('unknown-theme',['option','update','stylesheet','unverified-theme']);
  const probe=fixture('probe');check('unverified theme denies direct readers',probe.decision==='unknown'&&!probe.structure&&probe.media.every(x=>!x));
  wpcli('theme-restore',['option','update','stylesheet','twentytwentyfive']);
  await verify('public restored',{guest:true,reader:true});
  const debug=existsSync(`${wp}/wp-content/debug.log`)?readFileSync(`${wp}/wp-content/debug.log`,'utf8'):'';
  check('no plugin PHP fatal/warning',!debug.split('\n').some(line=>/PHP (?:Fatal|Warning|Parse)/.test(line)&&/atshift-article-composer/.test(line)));
  run('package-github',process.execPath,['scripts/package-wordpress.mjs'],{env:{...env,SF_DISTRIBUTION:'github'}});
  run('source-tests',process.execPath,['--test','tests/prefixes.test.mjs','tests/branding.test.mjs','tests/connection-profiles.test.mjs','tests/mcp-adapter-guidance.test.mjs','tests/publication-destination-guidance.test.mjs','tests/i18n.test.mjs'],{env:{...env,SF_TEST_ZIP:`${out}/packages/atshift-article-composer-${version}.zip`,SF_WORDPRESS_TEST_ROOT:wp}});
  summary.sourceTests='passed';
  summary.wordpress=wpcli('wordpress-version',['core','version']);
  summary.plugins=JSON.parse(wpcli('plugin-versions',['plugin','list','--fields=name,version,status','--format=json']));
  summary.result='passed';
} finally {
  if(server&&server.exitCode===null){server.kill('SIGTERM');await exited;}if(fd!==undefined)closeSync(fd);
  if(owned){assert.equal(wpcli('confirm-db',['config','get','DB_NAME']),db);wpcli('drop',['db','drop','--yes','--skip-plugins','--skip-themes']);summary.databaseDeleted=true;}
  writeFileSync(`${out}/summary.json`,JSON.stringify(summary,null,2),{mode:0o600});console.log(JSON.stringify({out,...summary,checks:summary.checks.length}));
}
