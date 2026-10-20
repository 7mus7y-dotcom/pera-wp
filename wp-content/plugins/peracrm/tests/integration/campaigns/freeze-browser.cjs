// Actual plugin UI + HTTP contracts on the bundled default theme; synthetic fixtures only.
module.exports = async function ({page,target,context,employeeContext,browser}) {
 const endpoint=target+'/wp-admin/admin-ajax.php';
 page.removeAllListeners('dialog');
 let confirmation='accept';
 page.on('dialog',dialog=>confirmation==='dismiss' && dialog.type()==='confirm' ? dialog.dismiss() : dialog.accept());
 const fixture=async(operation,extra={})=>(await (await context.request.post(endpoint,{form:{action:'peracrm_freeze_fixture',operation,...extra}})).json()).data;
 const before=await fixture('evidence');
 const anon=await browser.newContext();
 for(const requester of [anon.request,employeeContext.request]) {
  if((await requester.post(endpoint,{form:{action:'peracrm_campaign_freeze',operation:'state'}})).status()!==403) throw Error('Unauthorized freeze route accepted');
 }
 await anon.close();
 if((await context.request.post(endpoint,{form:{action:'peracrm_campaign_freeze',operation:'state'}})).status()!==403) throw Error('Freeze missing nonce accepted');
 const action=(name)=>page.locator('[data-campaign-freeze]').getByRole('button',{name,exact:true});
 const feedback=()=>page.locator('[data-freeze-feedback]');
 const responseFor=p=>p.url().includes('admin-ajax.php') && p.request().postData()?.includes('peracrm_campaign_freeze');
 async function click(name,ok=true) {
  if(name==='Start new editable revision') {
    const revision=Number(await page.locator('input[name=revision]').inputValue());
    const pending=page.waitForResponse(responseFor);
    await Promise.all([page.waitForNavigation({waitUntil:'load'}),action(name).click()]);
    const response=await pending;
    if(!response.ok() || !/no-store/.test(response.headers()['cache-control']||'')) throw Error('New revision response failed or cacheable');
    if(Number(await page.locator('input[name=revision]').inputValue())!==revision+1) throw Error('New editable revision did not increment exactly once');
    return {reload:true};
  }
  const pending=page.waitForResponse(responseFor);
  if(name==='Review saved revision') { await action(name).focus(); await page.keyboard.press('Enter'); } else { await action(name).click(); }
  const response=await pending;
  const json=await response.json();
  if(json.success!==ok) throw Error(name+' response '+JSON.stringify(json));
  if(!/no-store/.test(response.headers()['cache-control']||'')) throw Error('Freeze response cacheable');
  await page.waitForFunction(()=>document.querySelector('[data-freeze-feedback]').textContent!=='Processing one bounded request…');
  if(!json.data?.reload && !(await feedback().evaluate(el=>el===document.activeElement))) throw Error('Action feedback lost keyboard focus');
  return json.data;
 }
 async function screenshot(label,size) {
  if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)) {
    await page.screenshot({path:'/evidence/freeze-overflow-'+label+'-'+size+'.png',fullPage:true});
    console.log('Freeze overflow elements',await page.evaluate(()=>[...document.querySelectorAll('body *')].filter(el=>el.getBoundingClientRect().right>innerWidth+1).slice(0,10).map(el=>({tag:el.tagName,cls:el.className,right:el.getBoundingClientRect().right,whiteSpace:getComputedStyle(el).whiteSpace}))));
    throw Error('Freeze overflow '+label+' '+size);
  }
  const html=page.locator('[data-campaign-freeze] details').filter({has:page.getByText('Resolved HTML preview',{exact:true})}).first();
  const overview=['frozen','history'].includes(label);
  if(overview) await html.evaluate(el=>{el.open=false;});
  await page.locator('[data-campaign-freeze]').screenshot({path:'/evidence/freeze-'+label+'-'+size+'.png',style:'#site-header, #wpadminbar { visibility:hidden !important; }'});
  if(overview) await html.evaluate(el=>{el.open=true;});
 }
 async function draft(size,zero=false) {
  await page.goto(target+'/crm/campaigns/new/');
  await page.getByLabel('Internal name',{exact:true}).fill('Freeze acceptance '+size);
  await page.getByLabel('Subject',{exact:true}).fill('Immutable saved subject '+size);
  await page.getByLabel('Preheader',{exact:true}).fill('Saved preview context');
  await page.getByLabel('Explicit language (for example en)',{exact:true}).fill('en');
  await page.locator('select[name="document[sender_id]"]').selectOption('browser');
  await page.getByLabel('Main text',{exact:true}).fill('Stored long content '+('word '.repeat(240))+'x'.repeat(300));
  await page.getByRole('button',{name:'Add CTA block',exact:true}).click();
  await page.getByLabel('Button label',{exact:true}).fill('Read captured story');
  await page.getByLabel('Link URL',{exact:true}).fill('https://example.org/story?a=1&b=2');
  if(zero) await page.getByLabel('Audience topic (exact key)',{exact:true}).fill('nobody-has-this');
  await page.getByRole('button',{name:'Save draft',exact:true}).click();
  await action('Review saved revision').waitFor();
 }
 async function finish() {
  for(let batch=0;await action('Continue next 200 candidates').count();batch++) {
   if(batch>30) throw Error('Unbounded continuation loop');
   await click('Continue next 200 candidates');
  }
 }
 for(const [size,width,height] of [['desktop',1440,1000],['mobile',390,844]]) {
  await page.setViewportSize({width,height});
  await draft(size);
  const saved=page.url();
  await page.getByLabel('Subject',{exact:true}).fill('Unsaved retained subject');
  await action('Review saved revision').click();
  await feedback().filter({hasText:'Save your changes'}).waitFor();
  if(await page.getByLabel('Subject',{exact:true}).inputValue()!=='Unsaved retained subject') throw Error('Review discarded unsaved text');
  await page.reload();
  const reviewed=await click('Review saved revision');
  await page.locator('[data-freeze-review]').getByText('Immutable saved subject '+size,{exact:true}).waitFor();
  const reviewFrame=page.locator('[data-freeze-review] iframe').first();
  if(await reviewFrame.getAttribute('sandbox')!=='' || await reviewFrame.getAttribute('referrerpolicy')!=='no-referrer') throw Error('Saved preview isolation lost');
  const frameContent=page.frameLocator('[data-freeze-review] iframe').first();
  await frameContent.getByText('Read captured story',{exact:true}).last().waitFor();
  if(await frameContent.locator('a, [href], script, form').count()) throw Error('Saved preview contains active navigation');
  const originalURL=page.url();
  await frameContent.getByText('Read captured story',{exact:true}).last().click();
  if(page.url()!==originalURL) throw Error('Saved preview navigated');
  const previewWidth=await frameContent.locator('body').evaluate(el=>({scroll:el.scrollWidth,width:el.clientWidth}));
  if(previewWidth.scroll>previewWidth.width+1) throw Error('Saved email preview overflows '+size);
  await screenshot('review',size);
  // Server rejects an exact old review after a competing tab saves a new revision.
  const staleData=await page.evaluate(()=>{
   const form=document.querySelector('[data-campaign-editor]'), box=document.querySelector('[data-campaign-freeze]');
   return {fields:[...new FormData(form)],nonce:box.dataset.nonce,id:box.dataset.id,service_nonce:box.dataset.serviceNonce};
  });
  const other=await context.newPage(); await other.goto(saved);
  await other.getByLabel('Subject',{exact:true}).fill('Competing saved subject '+size);
  await other.getByRole('button',{name:'Save draft',exact:true}).click();
  await other.locator('[data-campaign-status]').filter({hasText:'Saved'}).waitFor();
  const denied=await context.request.post(endpoint,{form:{...Object.fromEntries(staleData.fields),action:'peracrm_campaign_freeze',operation:'review',nonce:staleData.nonce,id:staleData.id}});
  if(denied.status()!==409) throw Error('Stale saved review accepted');
  const staleProof=await context.request.post(endpoint,{form:{...Object.fromEntries(staleData.fields),action:'peracrm_campaign_freeze',operation:'start',nonce:staleData.nonce,id:staleData.id,service_nonce:staleData.service_nonce,proof:reviewed.proof,capture_nonce:reviewed.capture_nonce}});
  if(staleProof.status()!==409) throw Error('Stale review proof accepted');
  await other.close(); await page.reload();
  await click('Review saved revision');
  await click('Freeze revision');
  // Repeated clicks cannot run simultaneous continuations.
  let inFlight=0,maxInFlight=0;
  const started=req=>{if(req.url().includes('admin-ajax.php') && req.postData()?.includes('peracrm_campaign_freeze')) {inFlight++;maxInFlight=Math.max(maxInFlight,inFlight);}};
  const ended=req=>{if(req.url().includes('admin-ajax.php') && req.postData()?.includes('peracrm_campaign_freeze')) inFlight--;};
  page.on('request',started);page.on('requestfinished',ended);page.on('requestfailed',ended);
  const next=page.waitForResponse(responseFor);
  await action('Continue next 200 candidates').evaluate(button=>{button.click();button.click();button.click();});
  await next; await feedback().filter({hasText:'Server state loaded'}).waitFor();
  if(maxInFlight!==1) throw Error('Multiple continuation requests in flight');
  page.off('request',started);page.off('requestfinished',ended);page.off('requestfailed',ended);
  await screenshot('progress',size); const count=await page.locator('[data-freeze-counts]').innerText();
  await page.reload(); if(await page.locator('[data-freeze-counts]').innerText()!==count) throw Error('Reload lost progress');
  // A real SQL failure rolls back and appears after explicit state refresh.
  await context.addCookies([{name:'freeze_fixture_failure',value:'1',url:target}]);
  await click('Continue next 200 candidates',false);
  await screenshot('error',size);
  await context.clearCookies({name:'freeze_fixture_failure'});
  await click('Reload current state');
  await action('Resume materialization').waitFor();
  await screenshot('failed',size); await click('Resume materialization');
  // Competing page holds a stale version; server rejects it and UI requires refresh.
  const rival=await context.newPage(); await rival.goto(saved);
  const stale=await rival.evaluate(()=>{const b=document.querySelector('[data-campaign-freeze]'),a=b.querySelector('[data-freeze-attempt]');return {action:'peracrm_campaign_freeze',operation:'continue',nonce:b.dataset.nonce,service_nonce:b.dataset.serviceNonce,id:b.dataset.id,attempt:a.dataset.freezeAttempt,version:a.dataset.freezeVersion};});
  await click('Continue next 200 candidates');
  if((await context.request.post(endpoint,{form:stale})).status()!==409) throw Error('Competing stale continuation accepted');
  await rival.close();
  await finish();
  await action('Publish frozen revision').waitFor();
  let publicationRequests=0;
  const recordPublish=request=>{if(request.url().includes('admin-ajax.php') && request.postData()?.includes('publish')) publicationRequests++;};
  page.on('request',recordPublish); confirmation='dismiss';
  await action('Publish frozen revision').click();
  page.off('request',recordPublish); confirmation='accept';
  if(publicationRequests || await page.getByRole('heading',{name:/Stored immutable frozen revision/}).count()) throw Error('Dismissed publication proceeded');
  await click('Publish frozen revision');
  await page.getByRole('heading',{name:/Stored immutable frozen revision/}).waitFor();
  await screenshot('frozen',size);
  await context.addCookies([{name:'freeze_fixture_no_subscribers',value:'1',url:target}]);
  await page.reload();
  if(await page.getByText('Historical recipient membership (up to 200)',{exact:true}).count()) throw Error('Campaign-only user sees recipients');
  const restricted=await page.evaluate(()=>{const b=document.querySelector('[data-campaign-freeze]'),a=b.querySelector('[data-freeze-attempt]');return {action:'peracrm_campaign_freeze',operation:'recipients',nonce:b.dataset.nonce,id:b.dataset.id,attempt:a.dataset.freezeAttempt};});
  if((await context.request.post(endpoint,{form:restricted})).status()!==403) throw Error('Recipient handler accepted campaign-only capability');
  await context.clearCookies({name:'freeze_fixture_no_subscribers'}); await page.reload();

  const frame=page.frameLocator('[data-freeze-panel] iframe');
  await frame.getByText('Stored long content',{exact:false}).waitFor();
  if(await page.locator('[data-freeze-panel] iframe').getAttribute('sandbox')!=='') throw Error('Stored HTML missing sandbox');
  await page.locator('[data-freeze-panel]').getByText('Resolved plain-text preview',{exact:true}).click();
  if(!(await page.locator('[data-freeze-panel] pre').textContent()).includes('{{recipient_unsubscribe_url}}')) throw Error('Stored plain text missing controlled placeholder');
  if(await frame.locator('script,a,form,iframe').count()) throw Error('Stored preview has active elements');
  if(!(await frame.locator('meta[http-equiv="Content-Security-Policy"]').getAttribute('content')).includes("script-src 'none'")) throw Error('Stored preview CSP missing');
  await page.locator('[data-freeze-panel] iframe').scrollIntoViewIfNeeded();
  await page.locator('[data-freeze-panel] iframe').screenshot({path:'/evidence/freeze-content-'+size+'.png',style:'#site-header, #wpadminbar { visibility:hidden !important; }'});
  const frozenText=await page.locator('[data-freeze-panel] pre').textContent();
  await page.getByText('Historical recipient membership (up to 200)',{exact:true}).click();
  const firstPage=await page.locator('[data-freeze-attempt] ul li').allTextContents();
  if(firstPage.length>200) throw Error('Unbounded recipient page');
  if(await action('Next recipient page').isVisible()) {
    await click('Next recipient page');
    const secondPage=await page.locator('[data-freeze-attempt] ul li').allTextContents();
    if(secondPage.length>200 || secondPage.some(row=>firstPage.includes(row))) throw Error('Recipient pagination overlap or unbounded page');
    await click('Reload current state');
  }

  const frozenMembers=await page.locator('[data-freeze-attempt] ul').textContent();
  await click('Start new editable revision');
  await page.waitForLoadState('load'); await action('Review saved revision').waitFor();
  await page.screenshot({path:'/evidence/freeze-newdraft-'+size+'.png',fullPage:true});
  await page.getByLabel('Main text',{exact:true}).fill('New editable content');
  await page.getByRole('button',{name:'Save draft',exact:true}).click();
  await page.locator('[data-freeze-panel] pre').waitFor({state:'attached'});
  if(await page.locator('[data-freeze-panel] pre').textContent()!==frozenText) throw Error('New draft changed frozen content');
  if(await page.locator('[data-freeze-attempt] ul').textContent()!==frozenMembers) throw Error('New draft changed frozen membership');
  await screenshot('history',size);
  // Cancellation is terminal and never appears frozen.
  await click('Review saved revision'); await click('Freeze revision');
  await click('Cancel materialization');
  await page.getByText('Cancelled permanently.',{exact:false}).waitFor(); await screenshot('cancelled',size);
  if(await action('Publish frozen revision').count()) throw Error('Cancelled attempt publish control');
  const historyName=await page.locator('[data-freeze-action=history]').filter({hasText:'· frozen ·'}).first().innerText();
  await click(historyName);
  if(await page.locator('[data-freeze-panel] pre').textContent()!==frozenText) throw Error('History action did not return stored bytes');
  await click('Reload current state');
  // Empty audience cannot publish, including a crafted POST.
  await draft(size,true); await click('Review saved revision'); await click('Freeze revision'); await finish();
  if(await action('Publish frozen revision').count()) throw Error('Zero audience publication control');
  const zero=await page.evaluate(()=>{const b=document.querySelector('[data-campaign-freeze]'),a=b.querySelector('[data-freeze-attempt]');return {action:'peracrm_campaign_freeze',operation:'publish',nonce:b.dataset.nonce,service_nonce:b.dataset.serviceNonce,id:b.dataset.id,attempt:a.dataset.freezeAttempt,version:a.dataset.freezeVersion};});
  if((await context.request.post(endpoint,{form:zero})).ok()) throw Error('Zero included publication accepted');
  await click('Cancel materialization');
  console.log('PASS Phase 3C '+size+': saved/unsaved/stale review, bounded repeated/multitab continuation, reload/resume/SQL failure/cancel, zero denial, explicit publication, stored HTML/text and new draft history');
 }
 // Real actor impersonation denies the HTTP adapter; recipient capability is independent.
 await fixture('impersonate');
 if((await context.request.post(endpoint,{form:{action:'peracrm_campaign_freeze',operation:'state'}})).status()!==403) throw Error('Impersonated freeze accepted');
 await page.reload();
 if(await page.locator('[data-campaign-freeze]').count()) throw Error('Impersonated page exposes freeze controls');
 await fixture('restore');
 // Administrators on multisite are super administrators; capability checks are asserted
 // with a target-only campaign role in service fixtures, and scoped UI via user_has_cap below.
 const after=await fixture('evidence');
 if(before.protected!==after.protected) throw Error('Browser freeze mutated subscribers, consent, suppression or tokens');
 console.log('PASS Phase 3C browser: authority/nonce/no-store; protected subscriber/evidence/token bytes unchanged; no sending enabled');
};
