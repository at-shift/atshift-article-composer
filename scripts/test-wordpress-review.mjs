import { spawnSync, spawn } from 'node:child_process';
import { mkdirSync, cpSync, writeFileSync, readFileSync, readdirSync, symlinkSync, mkdtempSync, existsSync, openSync, closeSync, renameSync } from 'node:fs';
import { randomBytes, createHash } from 'node:crypto';
import { join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { once } from 'node:events';
import assert from 'node:assert/strict';
import { zipSync, unzipSync } from 'fflate';
import { writeHandoff } from './prepare-wordpress-org-handoff.mjs';
const {chromium} = await import(pathToFileURL(process.env.AC_PLAYWRIGHT).href);

const repo=fileURLToPath(new URL('../', import.meta.url));
const core=process.env.AC_WP_CORE, wpcli=process.env.WP_CLI, php=process.env.PHP_BINARY || 'php';
const fixtures=process.env.AC_WP_FIXTURES || core;
const checker=process.env.AC_PLUGIN_CHECK || fixtures+'/wp-content/plugins/plugin-check';
const deps=process.env.AC_NODE_MODULES, adapter=process.env.AC_MCP_ADAPTER, base=process.env.AC_TEST_OUTPUT;
for(const path of [core,wpcli,deps,adapter,base]) assert.ok(path && resolve(path)===path,'Supply absolute test fixture paths.');
assert.ok(!base.split('/').includes('.local'),'Put AC_TEST_OUTPUT outside .local (for example under /private/tmp); Vite intentionally denies private paths.');
const version=JSON.parse(readFileSync(repo+'/package.json')).version;
const testTimeout=Number(process.env.AC_TEST_TIMEOUT_MS || 300000);
assert.ok(Number.isSafeInteger(testTimeout) && testTimeout >= 300000 && testTimeout <= 1800000,'Test timeout must be between five and thirty minutes.');
const out=mkdtempSync(base+'/review-'), root=out+'/checkout', wp=root+'/.local/wordpress';
const db='sf_deft_prefix_'+randomBytes(5).toString('hex'), origin='http://127.0.0.1:8128';
const env={...process.env,SF_DISTRIBUTION:'wordpress-org',WP_CLI:wpcli,WP_CLI_CACHE_DIR:out+'/cache',SF_RELEASE_DIR:out+'/packages',SF_TEST_ZIP:out+'/packages/atshift-article-composer-'+version+'.zip',
 SF_POST_TYPES_ARTIFACT_DIR:out,SF_POST_TYPES_FIXTURE:out+'/post-types-fixture.json',
 SF_TAXONOMIES_ARTIFACT_DIR:out,SF_TAXONOMIES_FIXTURE:out+'/taxonomies-fixture.json',
 SF_PUBLIC_STRUCTURE_ARTIFACT_DIR:out,SF_PUBLIC_STRUCTURE_FIXTURE:out+'/public-structure-fixture.json',
 SF_APPEARANCE_ARTIFACT_DIR:out,SF_APPEARANCE_FIXTURE:out+'/appearance-fixture.json'};
const summary={out,db,checks:[],databaseDeleted:false};
let owned=false,server,serverExit,fd,browser,frontend,frontendExit;
function run(name,cmd,args,options={}) {
 const r=spawnSync(cmd,args,{cwd:root,env,encoding:'utf8',timeout:testTimeout,maxBuffer:16*1024*1024,...options});
 writeFileSync(out+'/'+name+'.stdout',r.stdout||'',{mode:0o600});writeFileSync(out+'/'+name+'.stderr',r.stderr||'',{mode:0o600});
 summary.checks.push({name,status:r.status});if(r.error)throw r.error;if(r.status!==0)throw new Error(name+': '+(r.stderr+'\n'+r.stdout).slice(-5000));console.log(name+': passed');return r.stdout;
}
const cli=(name,args,options)=>run(name,php,[wpcli,...args,'--path='+wp,'--no-color'],options);
function suite(name, fn) {
 try { fn(); } catch(error) { console.error(name+': FAILED: '+error.message); }
}
function files(p){return readdirSync(p,{withFileTypes:true}).flatMap(e=>e.isDirectory()?files(join(p,e.name)):[join(p,e.name)]);}
const password=randomBytes(32).toString('hex');
try{
 assert.equal(await fetch(origin,{signal:AbortSignal.timeout(500)}).catch(()=>null),null,'Port is occupied; do not replace another server.');
 mkdirSync(root,{recursive:true});run('copy-checkout','rsync',['-a','--exclude=/.git/','--exclude=/.local/','--exclude=/node_modules','--exclude=/dist/',repo+'/',root+'/']);
 symlinkSync(deps,root+'/node_modules');run('copy-core','rsync',['-a','--exclude=/wp-config.php','--exclude=/wp-content/','--exclude=/site-content/','--exclude=/.htaccess','--exclude=*.log',core+'/',wp+'/']);
 mkdirSync(wp+'/wp-content/plugins',{recursive:true});mkdirSync(wp+'/wp-content/themes',{recursive:true});
 cpSync(fixtures+'/wp-content/themes/twentytwentyfive',wp+'/wp-content/themes/twentytwentyfive',{recursive:true});
 cpSync(adapter,wp+'/wp-content/plugins/mcp-adapter',{recursive:true});
 cpSync(checker,wp+'/wp-content/plugins/plugin-check',{recursive:true});
 for(const p of files(root+'/tests').filter(p=>p.endsWith('.php'))){const c=readFileSync(p,'utf8');const n=c.replaceAll("'sf_prototype_local'","'"+db+"'").replaceAll('sf_deft_post_types_','sf_deft_prefix_').replaceAll('sf_deft_(?:post_types|appearance)_','sf_deft_prefix_').replaceAll('sf_deft_(?:appearance|post_types)_','sf_deft_prefix_');if(c!==n)writeFileSync(p,n);}
 const extra="define('WP_ENVIRONMENT_TYPE','local');\ndefine('DISABLE_WP_CRON',true);\ndefine('WP_HTTP_BLOCK_EXTERNAL',true);\ndefine('WP_DEBUG',true);\ndefine('WP_DEBUG_LOG',true);\ndefine('WP_DEBUG_DISPLAY',false);\n";
 cli('config',['config','create','--dbname='+db,'--dbuser=root','--dbpass=','--dbhost=127.0.0.1:3306','--dbprefix=sfp_','--skip-salts','--skip-check','--extra-php'],{input:extra});
 cli('db-create',['db','create']);owned=true;
 cli('core-install',['core','install','--url='+origin,'--title=DeFt prefix QA','--admin_user=sf_local_admin','--admin_password='+password,'--admin_email=qa@example.invalid','--skip-email']);
 summary.wordpress=cli('core-version',['core','version']).trim();summary.php=run('php-version',php,['-r','echo PHP_VERSION;']).trim();
 writeFileSync(root+'/.local/wp-credentials.json',JSON.stringify({username:'sf_local_admin',password}),{mode:0o600});
 cli('theme',['theme','activate','twentytwentyfive']);
 cli('activate-adapter',['plugin','activate','mcp-adapter']);

 for(const channel of ['github','wordpress-org'])run('package-'+channel,process.execPath,['scripts/package-wordpress.mjs'],{env:{...env,SF_DISTRIBUTION:channel,SF_PACKAGE_DIR:out+'/packages'}});

 cli('install-after',['plugin','install',out+'/packages/atshift-article-composer-'+version+'-wordpress-org.zip','--force','--activate']);

 run('frontend-build','npm',['run','build']);
 run('node-static',process.execPath,['--test',...['prefixes','distribution','branding','update-handoff','connection-profiles','i18n','mcp-adapter-guidance','publication-destination-guidance','wordpress-media-schema','webmcp-registration','ai-kit','review-server'].map(n=>'tests/'+n+'.test.mjs')]);
 for(const n of ['runtime-guard','channel-guard'])run(n,php,['tests/'+n+'.php']);
 cli('php-review-076',['eval-file',root+'/tests/wordpress-review-076.php']);
 for(const n of ['integration','i18n','branding','connections','remote-scope','oauth-inputs','nonces','onboarding','notices','security','titles','publications','assets','private-blobs','media','personal','structures','behaviors','quotas','catalog-upgrade','transport'])suite(n,()=>run('php-'+n,php,['tests/wordpress-'+n+'.php']));
 mkdirSync(wp+'/wp-content/mu-plugins',{recursive:true});
 for(const n of ['post-types','taxonomies'])cpSync(root+'/tests/wordpress-'+n+'-fixture.php',wp+'/wp-content/mu-plugins/'+n+'-fixture.php');
 cpSync(root+'/tests/wordpress-review-access-fixture.php',wp+'/wp-content/mu-plugins/review-access-fixture.php');
 for(const n of ['publication-guidance','draft-trash','post-types','taxonomies','reservations','draft-collations','appearance'])suite(n,()=>cli('php-'+n,['eval-file',root+'/tests/wordpress-'+n+'.php']));
 {
   cli('org-mcp-active',['eval-file',root+'/tests/wordpress-mcp-prerequisite.php'],{env:{...env,SF_EXPECT_MCP_READY:'1'}});
   cli('disable-adapter',['plugin','deactivate','mcp-adapter']);
   cli('org-mcp-inactive',['eval-file',root+'/tests/wordpress-mcp-prerequisite.php'],{env:{...env,SF_EXPECT_MCP_READY:'0'}});
   cli('restore-adapter',['plugin','activate','mcp-adapter']);
   renameSync(wp+'/wp-content',wp+'/site-content');
   cli('relocated-content-dir',['config','set','WP_CONTENT_DIR',wp+'/site-content']);
   cli('relocated-content-url',['config','set','WP_CONTENT_URL',origin+'/site-content']);
   run('php-storage-portability',php,['tests/wordpress-storage-portability.php']);
   run('php-private-blobs',php,['tests/wordpress-private-blobs.php']);
 }
 fd=openSync(out+'/server.log','a',0o600);server=spawn(php,['-S','127.0.0.1:8128','-t',wp,root+'/scripts/wordpress-local-router.php'],{cwd:root,env,stdio:['ignore',fd,fd]});serverExit=once(server,'exit');
 let ready=false;for(let i=0;i<50;i++){const r=await fetch(origin+'/wp-login.php',{signal:AbortSignal.timeout(1000)}).catch(()=>null);if(r?.status===200){ready=true;break;}await new Promise(r=>setTimeout(r,100));}assert.ok(ready);
 run('http',process.execPath,['--test','--test-concurrency=1',...(['wordpress-http','wordpress-oauth','wordpress-mcp','wordpress-users','wordpress-workflow','wordpress-publications','wordpress-post-types-http','wordpress-taxonomies-http','wordpress-assets-http','wordpress-media-http','wordpress-quotas-http','wordpress-catalog','wordpress-appearance-http']).map(n=>'tests/'+n+'.test.mjs')]);
 browser=await chromium.launch({channel:'chrome',headless:true});
 const frontendOrigin='http://127.0.0.1:8140';
 assert.equal(await fetch(frontendOrigin,{signal:AbortSignal.timeout(500)}).catch(()=>null),null,'Frontend test port is occupied.');
 frontend=spawn(process.execPath,[root+'/node_modules/vite/bin/vite.js','--host','127.0.0.1','--port','8140','--strictPort'],{cwd:root,env,stdio:['ignore',fd,fd]});frontendExit=once(frontend,'exit');
 const frontendContext=await browser.newContext();
 await frontendContext.route('**/*',route=>new URL(route.request().url()).origin===frontendOrigin?route.continue():route.abort());
 const frontendPage=await frontendContext.newPage(), frontendErrors=[];
 frontendPage.on('pageerror',error=>frontendErrors.push(error.message));
 for(let i=0;i<50;i++){if(await fetch(frontendOrigin).catch(()=>null))break;await new Promise(resolve=>setTimeout(resolve,100));}
 for(const test of ['model','articles','article-tools']) {
   await frontendPage.goto(frontendOrigin+'/tests/'+test+'.test.html');
   await frontendPage.waitForFunction(()=>document.querySelector('#result')?.textContent.includes('failed'));
   const result=await frontendPage.locator('#result').innerText();
   assert.match(result,/\b0 failed\b/,test+': '+result);
   summary.checks.push({name:'browser-unit-'+test,status:0,result});
 }
 assert.deepEqual(frontendErrors,[]);await frontendContext.close();
 const context=await browser.newContext({viewport:{width:1440,height:1000}}), page=await context.newPage();const errors=[];
 context.setDefaultNavigationTimeout(120000);
 page.on('pageerror',e=>errors.push(e.message));await context.route('**/*',route=>new URL(route.request().url()).origin===origin?route.continue():route.abort());
 await page.goto(origin+'/wp-login.php');await page.locator('#user_login').fill('sf_local_admin');await page.locator('#user_pass').fill(password);
 await Promise.all([page.waitForURL('**/wp-admin/**'),page.locator('#wp-submit').click()]);
 for(const slug of ['structured-fields','structured-fields-catalog','structured-fields-connections']){
   await page.goto(origin+'/wp-admin/admin.php?page='+slug);await page.waitForLoadState('networkidle');
   assert.ok(await page.evaluate(()=>Boolean(window.atshiftDeftWordPress)));
   assert.equal(await page.evaluate(()=>typeof window.sfWordPress),'undefined');
   assert.ok(await page.locator('#atshift-deft-admin-js').count());
   if(slug.endsWith('connections'))assert.ok(await page.evaluate(()=>Boolean(window.atshiftDeftConnectionProfiles)));
   await page.screenshot({path:out+'/'+slug+'.png'});
 }
 assert.deepEqual(errors,[]);summary.checks.push({name:'browser-three-admin-pages',status:0});
 {
   await page.goto(origin+'/wp-admin/admin.php?page=structured-fields');await page.waitForLoadState('networkidle');
   if(await page.locator('#sf-ai-welcome[open]').count())await page.locator('#sf-ai-welcome-close').click();
   await page.locator('#sf-sample').click();await page.waitForFunction(()=>!document.querySelector('#sf-save').disabled);
   await page.locator('#sf-editor summary').click();await page.locator('#sf-article-title').fill('Disposable browser lifecycle');
   const savedResponse=page.waitForResponse(r=>r.request().method()==='POST'&&decodeURIComponent(r.url()).includes('/articles'));
   await page.locator('#sf-save').click();const savedHttp=await savedResponse;assert.ok(savedHttp.ok());const saved=await savedHttp.json();
   await page.waitForFunction(()=>document.querySelector('#sf-status').textContent==='Draft saved. Not published.');
   const preview=await context.newPage();await preview.goto(saved.previewUrl);await preview.waitForLoadState('networkidle');
   assert.ok((await preview.locator('body').innerText()).includes('Disposable browser lifecycle'));await preview.screenshot({path:out+'/sample-preview.png'});await preview.close();
   await page.screenshot({path:out+'/sample-saved-desktop.png'});
   await page.setViewportSize({width:390,height:844});await page.screenshot({path:out+'/sample-saved-mobile.png'});
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1),'Mobile page must fit horizontally');
   await page.setViewportSize({width:1440,height:1000});page.on('dialog',d=>d.accept());
   await page.getByRole('button',{name:'Move Disposable browser lifecycle to Trash',exact:true}).click();
   await page.waitForFunction(()=>document.querySelector('#sf-status').textContent==='Draft moved to Trash.');
   await page.locator('#sf-trash-tab').click();await page.getByRole('button',{name:'Restore Disposable browser lifecycle',exact:true}).click();
   await page.waitForFunction(()=>document.querySelector('#sf-status').textContent==='Draft restored. Not published.');
   assert.deepEqual(errors,[]);summary.checks.push({name:'browser-save-preview-trash-restore-desktop-mobile',status:0});
 }
 {
   cli('japanese-user-locale',['user','meta','update','sf_local_admin','locale','ja']);
   for(const slug of ['structured-fields','structured-fields-catalog','structured-fields-connections']) {
     await page.goto(origin+'/wp-admin/admin.php?page='+slug);await page.waitForLoadState('networkidle');
     const expected=slug==='structured-fields-catalog'?'部品':'AI用手順書をダウンロード';
     assert.ok((await page.locator('body').innerText()).includes(expected),'Japanese labels render on '+slug);
     for(const width of [1440,390]) {
       await page.setViewportSize({width,height:width===390?844:1000});
       await page.screenshot({path:out+'/'+slug+'-ja-'+width+'.png'});
       assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1),'Japanese page must fit at '+width+': '+slug);
     }
   }
   await page.setViewportSize({width:1440,height:1000});
   await page.goto(origin+'/wp-admin/admin.php?page=structured-fields');await page.waitForLoadState('networkidle');
   await page.locator('#sf-sample').click();await page.waitForFunction(()=>!document.querySelector('#sf-save').disabled);
   const response=page.waitForResponse(r=>r.request().method()==='POST'&&decodeURIComponent(r.url()).includes('/articles'));
   await page.locator('#sf-save').click();assert.ok((await response).ok());
   await page.waitForFunction(()=>document.querySelector('#sf-status').textContent==='下書きを保存しました。公開はされていません。');
   await page.screenshot({path:out+'/sample-saved-ja.png'});
   assert.deepEqual(errors,[]);summary.checks.push({name:'browser-japanese-three-pages-desktop-mobile-save',status:0});
   cli('restore-user-locale',['user','meta','delete','sf_local_admin','locale']);
 }
 const content=wp+'/site-content';
 const check=spawnSync(php,[wpcli,'plugin','check','atshift-article-composer','--mode=new','--slug=atshift-article-composer','--format=strict-json','--fields=file,line,column,type,code,message,docs','--path='+wp,'--no-color','--require='+content+'/plugins/plugin-check/cli.php'],{cwd:root,env,encoding:'utf8',timeout:300000,maxBuffer:16*1024*1024});
 writeFileSync(out+'/plugin-check.json',check.stdout||'',{mode:0o600});writeFileSync(out+'/plugin-check.stderr',check.stderr||'',{mode:0o600});
 if(check.error)throw check.error;
 const findings=JSON.parse(check.stdout);summary.pluginCheck={exit:check.status,errors:findings.filter(x=>x.type==='ERROR').length,warnings:findings.filter(x=>x.type==='WARNING').length};
 console.log('Plugin Check: '+JSON.stringify(summary.pluginCheck));
 assert.equal(summary.pluginCheck.errors,0,'Plugin Check errors must be resolved.');
 {
   cli('network-convert',['core','multisite-convert','--title=Disposable DeFt network']);
   cli('network-adapter',['plugin','activate','mcp-adapter','--network']);
   cli('network-deft',['plugin','activate','atshift-article-composer','--network']);
   run('network-remote-scope',php,['tests/wordpress-remote-scope.php']);
   cli('network-second-site',['site','create','--slug=second','--title=Disposable second site','--email=qa@example.invalid']);
   cli('network-seed',['eval-file',root+'/tests/wordpress-multisite.php'],{env:{...env,DEFT_SITE_PHASE:'seed'}});
   cli('network-verify',['eval-file',root+'/tests/wordpress-multisite.php','--url='+origin+'/second/'],{env:{...env,DEFT_SITE_PHASE:'verify'}});
 }
 {
   cli('org-network-deactivate',['plugin','deactivate','atshift-article-composer','--network']);
   if(process.env.AC_PREVIOUS_GITHUB_ZIP) {
     const previous=readFileSync(process.env.AC_PREVIOUS_GITHUB_ZIP);
     assert.equal(createHash('sha256').update(previous).digest('hex'),'cab3b51de465228cc567294f46c6a3ec0c97475d075eca32f46064e8c6bf6ce8','Use the original public 0.7.6 ZIP, not a synthetic predecessor.');
     cli('original-076-install',['plugin','install',process.env.AC_PREVIOUS_GITHUB_ZIP,'--force','--activate']);
     run('original-076-integration',php,['tests/wordpress-integration.php']);
     // Rehearse with local directory bytes. Public handoff must be rebuilt from the official download.
     await writeHandoff(readFileSync(out+'/packages/atshift-article-composer-'+version+'-wordpress-org.zip'),{version,previousVersion:'0.7.6'},out+'/handoff-actual-version-rehearsal');
     cli('original-076-to-100-native-upgrade',['eval-file',root+'/tests/wordpress-github-updater.php'],{env:{...env,SF_RELEASE_DIR:out+'/handoff-actual-version-rehearsal',SF_UPDATE_FROM_VERSION:'0.7.6',SF_UPDATE_HANDOFF:'1'}});
     cli('actual-version-directory-next-update',['eval-file',root+'/tests/wordpress-org-updates.php'],{env:{...env,SF_HANDOFF_VERSION:version,SF_DIRECTORY_NEXT_VERSION:version.split('.').map((n,i)=>i===2?Number(n)+1:n).join('.')}});
     run('actual-version-handoff-i18n',php,['tests/wordpress-i18n.php']);
     run('actual-version-handoff-integration',php,['tests/wordpress-integration.php']);
   }
   cli('github-install',['plugin','install',out+'/packages/atshift-article-composer-'+version+'.zip','--force','--activate']);
   const entry=content+'/plugins/atshift-article-composer/atshift-article-composer.php';
   const installed=readFileSync(entry,'utf8');
   assert.ok(installed.includes('Version: '+version));
   // Exercise native upgrade transport without restoring unsupported legacy data or code.
   writeFileSync(entry,installed.replace('Version: '+version,'Version: 0.7.5'));
   cli('github-native-updater',['eval-file',root+'/tests/wordpress-github-updater.php'],{env:{...env,SF_DISTRIBUTION:'github'}});
   run('github-i18n',php,['tests/wordpress-i18n.php'],{env:{...env,SF_DISTRIBUTION:'github'}});
   run('github-integration',php,['tests/wordpress-integration.php'],{env:{...env,SF_DISTRIBUTION:'github'}});
   const parts=version.split('.').map(Number), handoffVersion=[parts[0],parts[1],parts[2]+1].join('.');
   const nextVersion=[parts[0],parts[1],parts[2]+2].join('.');
   const official=unzipSync(readFileSync(out+'/packages/atshift-article-composer-'+version+'-wordpress-org.zip'));
   const main='atshift-article-composer/atshift-article-composer.php', readme='atshift-article-composer/readme.txt';
   official[main]=Buffer.from(Buffer.from(official[main]).toString().replace('Version: '+version,'Version: '+handoffVersion).replace("const VERSION = '"+version+"'", "const VERSION = '"+handoffVersion+"'"));
   official[readme]=Buffer.from(Buffer.from(official[readme]).toString().replace('Stable tag: '+version,'Stable tag: '+handoffVersion));
   // Future version numbers and directory/GitHub HTTP responses are disposable test fixtures only.
   await writeHandoff(zipSync(official),{version:handoffVersion,previousVersion:version},out+'/handoff');
   cli('github-to-directory-native-upgrade',['eval-file',root+'/tests/wordpress-github-updater.php'],{env:{...env,SF_DISTRIBUTION:'github',SF_RELEASE_DIR:out+'/handoff',SF_UPDATE_FROM_VERSION:version,SF_UPDATE_HANDOFF:'1'}});
   cli('directory-updates-fresh-process',['eval-file',root+'/tests/wordpress-org-updates.php'],{env:{...env,SF_HANDOFF_VERSION:handoffVersion,SF_DIRECTORY_NEXT_VERSION:nextVersion}});
   run('handoff-integration',php,['tests/wordpress-integration.php']);
 }
 const debug=existsSync(content+'/debug.log')?readFileSync(content+'/debug.log','utf8'):'';
 const unexpected=debug.split('\n').filter(l=>l&&!(l.includes('wp_version_check()')||l.includes('wp_update_plugins()')||l.includes('wp_update_themes()')));
 assert.deepEqual(unexpected,[]);summary.offlineCoreWarnings=debug.split('\n').filter(Boolean).length;
 assert.deepEqual(summary.checks.filter(check=>check.status!==0),[], 'Every required suite must pass.');
}finally{
 if(browser)await browser.close();if(frontend&&frontend.exitCode===null){frontend.kill('SIGTERM');await frontendExit;}if(server&&server.exitCode===null){server.kill('SIGTERM');await serverExit;}if(fd!==undefined)closeSync(fd);
 if(owned){assert.equal(cli('confirm-db',['config','get','DB_NAME']).trim(),db);cli('db-drop',['db','drop','--yes','--skip-plugins','--skip-themes']);summary.databaseDeleted=true;}
 writeFileSync(out+'/summary.json',JSON.stringify(summary,null,2),{mode:0o600});console.log('Evidence: '+out);console.log(JSON.stringify(summary,null,2));
}
