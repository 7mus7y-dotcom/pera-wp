// Real administrator page, real POSTs, synthetic artifact digests only. No provider access.
module.exports = async function ({browser,page,target,context,employeeContext}) {
 const url=target+'/wp-admin/admin.php?page=peracrm-campaign-readiness';
 const cli=await (await context.request.get('http://fixture.test/readiness-cli.json')).json();
 const anon=await browser.newContext();
 for (const requester of [anon.request,employeeContext.request]) {
  const read=await requester.get(url,{maxRedirects:0});
  if(read.status()!==403 && read.status()!==302) throw Error('Unauthorized readiness read');
  const write=await requester.post(url,{form:{digest:'0'.repeat(64)},maxRedirects:0});
  if(write.status()!==403 && write.status()!==302) throw Error('Unauthorized readiness write');
 }
 await anon.close();
 const manager=await browser.newContext(); const mp=await manager.newPage();
 await mp.goto(target+'/wp-login.php');
 await mp.locator('#user_login').fill('campaign-fixture-manager');
 await mp.locator('#user_pass').fill('fixture-manager-only');
 await Promise.all([mp.waitForURL(u=>!u.pathname.endsWith('wp-login.php')),mp.locator('#wp-submit').click()]);
 if((await manager.request.get(url)).status()!==403 || (await manager.request.post(url,{form:{}})).status()!==403) throw Error('Manager readiness access');
 await manager.close();
 async function fields() { return page.locator('[data-readiness-form]').evaluate(f=>Object.fromEntries(new FormData(f))); }
 async function capture(name,size) {
  if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)) throw Error('Readiness overflow '+size);
  await page.locator('[data-readiness-page]').screenshot({path:'/evidence/readiness-'+name+'-'+size+'.png'});
  console.log('READINESS_REVIEW '+name+' '+size+' '+(await page.locator('[data-readiness-page]').screenshot({type:'jpeg',quality:40})).toString('base64'));
 }
 for(const [size,width,height] of [['desktop',1440,1000],['mobile',390,844]]) {
  await page.setViewportSize({width,height});
  let response=await page.goto(url);
  if(!/no-store/.test(response.headers()['cache-control']||'')) throw Error('Cacheable readiness page');
  await page.getByRole('heading',{name:'Campaign readiness',exact:true}).waitFor();
  if(size==='desktop') {
   const rows=await page.locator('[data-readiness-check]').evaluateAll(rows=>Object.fromEntries(rows.map(r=>[r.dataset.readinessCheck,{state:r.dataset.state,source:r.dataset.source}])));
   for(const [name,check] of Object.entries(cli.checks)) {
    if(rows[name]?.state!==check.state || rows[name]?.source!==check.source) throw Error('UI/actual CLI disagreement '+name);
   }
   const blockers=await page.locator('[data-readiness-blocker]').evaluateAll(rows=>rows.map(r=>r.dataset.readinessBlocker));
   if(JSON.stringify(blockers)!==JSON.stringify(cli.blockers)) throw Error('UI/actual CLI blockers disagree');
  }
  for(const label of ['Prerequisite','Evidence type','Redacted artifact SHA-256']) {
   if(!(await page.getByLabel(label,{exact:true}).count())) throw Error('Missing accessible label '+label);
  }
  const styles=await page.locator('link[rel=stylesheet]').evaluateAll(links=>links.map(l=>l.href));
  if(!styles.some(s=>s.includes('/peracrm/assets/frontend/crm.css')) || styles.some(s=>s.includes('/themes/'))) throw Error('Readiness asset ownership');
  if(await page.getByRole('button',{name:/send|activate|sync/i}).count()) throw Error('Activation controls exposed');
  await capture('initial',size);
  const digest=page.getByLabel('Redacted artifact SHA-256',{exact:true});
  await page.getByLabel('Prerequisite',{exact:true}).selectOption('retention');
  await page.getByLabel('Evidence type',{exact:true}).selectOption('operator_attestation');
  await digest.fill('a'.repeat(64));
  await page.getByRole('checkbox').check();
  const form=await fields();
  for(const patch of [{nonce:'bad'},{digest:'https://credential.invalid/token'},{actor:'999'},{verified_at:'1'},{notes:'arbitrary'},{digest:['array']}]) {
   // Multipart/form parser represents arrays via bracket syntax, never an accepted scalar.
   const bad={...form,...patch}; if(Array.isArray(bad.digest)) { delete bad.digest; bad['digest[]']='array'; }
   if((await context.request.post(url,{form:bad})).status()!==422) throw Error('Unsafe form accepted');
  }
  await context.addCookies([{name:'readiness_fixture_failure',value:'1',url:target}]);
  await digest.focus(); await page.keyboard.press('Tab'); await page.keyboard.press('Tab');
  if(!(await page.getByRole('button',{name:'Record evidence',exact:true}).evaluate(b=>b===document.activeElement))) throw Error('Keyboard submit unreachable');
  await Promise.all([page.waitForNavigation(),page.keyboard.press('Enter')]);
  await page.getByRole('alert').filter({hasText:'Evidence was not saved'}).waitFor();
  if(!(await page.locator('[data-readiness-feedback]').evaluate(e=>document.activeElement===e))) throw Error('Failure feedback not focused');
  if(await digest.inputValue()!=='') throw Error('Rejected input echoed');
  await capture('failed-save',size);
  await context.clearCookies({name:'readiness_fixture_failure'});
  await page.getByLabel('Prerequisite',{exact:true}).selectOption('retention');
  await page.getByLabel('Evidence type',{exact:true}).selectOption('operator_attestation');
  await digest.fill((size==='desktop'?'b':'c').repeat(64)); await page.getByRole('checkbox').check();
  await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Record evidence',exact:true}).click()]);
  await page.getByRole('status').filter({hasText:'Evidence saved'}).waitFor();
  if(await page.locator('[data-readiness-check=retention]').getAttribute('data-state')!=='recorded') throw Error('Save not reflected in service');
  await capture('saved',size);
  const stale=await fields(); stale.confirmed='1'; stale.digest='d'.repeat(64); stale.prerequisite='retention'; stale.provenance='operator_attestation';
  await context.request.post(target+'/wp-admin/admin-ajax.php',{form:{action:'peracrm_readiness_fixture',operation:'change_configuration'}});
  if((await context.request.post(url,{form:stale})).status()!==422) throw Error('Stale configuration accepted');
  await page.goto(url);
  if(await page.locator('[data-readiness-check=retention]').getAttribute('data-state')!=='unverified') throw Error('Changed config evidence still valid');
  await page.getByRole('heading',{name:'Historical configuration binding — not applicable'}).first().waitFor();
  await page.getByText((size==='desktop'?'b':'c').repeat(64),{exact:false}).first().waitFor();
  await capture('invalidated-history',size);
 }
 await context.request.post(target+'/wp-admin/admin-ajax.php',{form:{action:'peracrm_freeze_fixture',operation:'impersonate'}});
 if((await context.request.get(url)).status()!==403 || (await context.request.post(url,{form:{}})).status()!==403) throw Error('Impersonation readiness access');
 await context.request.post(target+'/wp-admin/admin-ajax.php',{form:{action:'peracrm_freeze_fixture',operation:'restore'}});
 // Native form works without script execution too.
 const nojs=await browser.newContext({javaScriptEnabled:false,storageState:await context.storageState()});
 const np=await nojs.newPage(); await np.goto(url);
 await np.getByLabel('Prerequisite',{exact:true}).selectOption('retention');
 await np.getByLabel('Evidence type',{exact:true}).selectOption('operator_attestation');
 await np.getByLabel('Redacted artifact SHA-256',{exact:true}).fill('e'.repeat(64)); await np.getByRole('checkbox').check();
 await Promise.all([np.waitForNavigation(),np.getByRole('button',{name:'Record evidence',exact:true}).click()]);
 await np.getByRole('status').filter({hasText:'Evidence saved'}).waitFor(); await nojs.close();
 console.log('PASS readiness browser: administrator-only, nonce and server validation, actual CLI agreement, desktop/mobile labels, keyboard, successful/failed native saves, stale configuration, history, impersonation, no-JavaScript');
};
