// Actual default-theme desktop/mobile queue UI. Outcomes are seeded exclusively by CLI.
module.exports = async function ({page,target,context,employeeContext,browser}) {
 const seeds=await (await context.request.get('http://fixture.test/queue-ui-fixture.json')).json();
 const endpoint=target+'/wp-admin/admin-ajax.php';
 const evidence=async()=> (await (await context.request.post(endpoint,{form:{action:'peracrm_queue_ui_evidence'}})).json()).data;
 const before=await evidence();
 const fixture=async operation=>context.request.post(endpoint,{form:{action:'peracrm_freeze_fixture',operation}});
 page.removeAllListeners('dialog'); let confirm=true;
 page.on('dialog',d=>d.type()==='confirm' && !confirm ? d.dismiss() : d.accept());
 const anon=await browser.newContext();
 for (const requester of [anon.request,employeeContext.request]) {
  if((await requester.get(target+'/crm/campaigns/queue/')).status()!==403) throw Error('Unauthorized queue page');
  if((await requester.post(endpoint,{form:{action:'peracrm_campaign_queue',operation:'state'}})).status()!==403) throw Error('Unauthorized queue adapter');
 }
 await anon.close();
 if((await context.request.post(endpoint,{form:{action:'peracrm_campaign_queue',operation:'state'}})).status()!==403) throw Error('Queue missing nonce accepted');
 const box=()=>page.locator('[data-campaign-queue]');
 const feedback=()=>page.locator('[data-queue-feedback]');
 const button=name=>box().getByRole('button',{name,exact:true});
 const matches=r=>r.url().includes('admin-ajax.php') && r.request().postData()?.includes('peracrm_campaign_queue');
 async function click(name,success=true) {
  const pending=page.waitForResponse(matches); await button(name).click(); const response=await pending; const json=await response.json();
  if(json.success!==success) throw Error(name+' '+JSON.stringify(json));
  if(!/no-store/.test(response.headers()['cache-control']||'')) throw Error('Cacheable queue response');
  await page.waitForFunction(()=>document.querySelector('[data-queue-feedback]')?.textContent!=='Processing one bounded queue request…');
  if(!(await feedback().evaluate(el=>document.activeElement===el))) throw Error('Queue feedback focus lost');
  return json;
 }
 async function capture(label,size) {
  if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)) throw Error('Queue horizontal overflow '+label+' '+size);
  await page.locator('.peracrm-queue').screenshot({path:'/evidence/queue-'+label+'-'+size+'.png',style:'#site-header, #wpadminbar { visibility:hidden !important; }'});
  console.log('QUEUE_REVIEW '+label+' '+size+' '+(await page.locator('.peracrm-queue').screenshot({type:'jpeg',quality:50,style:'#site-header, #wpadminbar { visibility:hidden !important; }'})).toString('base64'));
 }
 async function fields() {
  return page.evaluate(()=>{const b=document.querySelector('[data-campaign-queue]'),j=b.querySelector('[data-queue-job]');return {action:'peracrm_campaign_queue',id:b.dataset.id,nonce:b.dataset.nonce,service_nonce:b.dataset.serviceNonce,job:j?.dataset.queueJob || '0',version:j?.dataset.queueVersion || '0'};});
 }
 for(const [size,width,height] of [['desktop',1440,1000],['mobile',390,844]]) {
  await page.setViewportSize({width,height});
  await page.goto(target+'/crm/campaigns/queue/');
  await page.getByText('Dispatch disabled — scheduled work will not send.',{exact:true}).waitFor();
  if(await page.getByRole('button',{name:/^(Send|Enable dispatch|Process fixture)$/i}).count()) throw Error('Forbidden dispatch control');
  const table=page.locator('.peracrm-subscriber-table table');
  if(await table.evaluate(el=>getComputedStyle(el).tableLayout)!=='fixed') throw Error('Inherited shell table layout prevents bounded long-label wrapping');
  if(await table.locator('tbody td').first().evaluate(el=>getComputedStyle(el).whiteSpace)!=='normal') throw Error('Inherited shell nowrap clips campaign labels');
  if(await table.evaluate(el=>el.scrollWidth>Math.max(650,el.parentElement.clientWidth)+2)) throw Error('Long label creates excessive table scrolling');
  await capture('overview',size);
  await page.goto(target+'/crm/campaigns/'+seeds[size].campaign+'/');
  await page.getByRole('link',{name:'Prepare or inspect queue for this published revision',exact:true}).click();
  const preparation=await fields();
  for(const operation of ['send','enable','fixture','accept','reconcile']) {
   if((await context.request.post(endpoint,{form:{...preparation,operation}})).ok()) throw Error('Unsupported operational authority exposed '+operation);
  }
  for(const revision of ['0','99999999']) {
   const denied=await context.request.post(endpoint,{form:{...preparation,operation:'prepare',revision}});
   if(denied.ok()) throw Error('Unpublished/draft queue prepared');
  }
  let max=0,inflight=0;
  const start=req=>{if(req.postData()?.includes('peracrm_campaign_queue')) max=Math.max(max,++inflight);};
  const end=req=>{if(req.postData()?.includes('peracrm_campaign_queue')) inflight--;};
  page.on('request',start);page.on('requestfinished',end);page.on('requestfailed',end);
  const pending=page.waitForResponse(matches);
  await box().locator('[data-queue-action=prepare]').evaluate(b=>{b.click();b.click();b.click();});
  const preparedResponse=await pending;
  if(!preparedResponse.ok() || !/no-store/.test(preparedResponse.headers()['cache-control']||'')) throw Error('Preparation response failed or cacheable');
  await page.waitForURL(url=>/\/queue\/[0-9]+\/$/.test(url.pathname));
  await button('Continue next 200 recipients').waitFor();
  if(max!==1) throw Error('Duplicate preparation requests');
  page.off('request',start);page.off('requestfinished',end);page.off('requestfailed',end);
  const duplicate=await context.request.post(endpoint,{form:{...preparation,operation:'prepare',revision:seeds[size].revision}});
  if((await duplicate.json()).data.url!==page.url()) throw Error('Idempotent prepare created another job');
  const saved=page.url();
  // Actual SQL failure, rollback, reload, explicit resume and multi-batch continuation.
  const failedJobs=()=>page.locator('[data-queue-health] dt').filter({hasText:/^Failed jobs$/}).evaluate(el=>Number(el.nextElementSibling.textContent));
  const failedBefore=await failedJobs();
  await context.addCookies([{name:'queue_fixture_failure',value:'1',url:target}]);
  await click('Continue next 200 recipients',false); await capture('error',size);
  await context.clearCookies({name:'queue_fixture_failure'});
  await click('Reload current state');
  await button('Resume preparation').waitFor();
  if(await failedJobs()!==failedBefore+1) throw Error('Job refresh left global failed-job health stale');
  await capture('failed-job',size);
  if(!/Prepared: 0 \/ /.test(await page.locator('[data-queue-totals]').innerText())) throw Error('Failed batch did not roll back');
  await click('Resume preparation');
  if(await failedJobs()!==failedBefore) throw Error('Resumed job health did not refresh');
  const stale=await fields();
  await click('Continue next 200 recipients');
  if(!/Prepared: 200 \/ /.test(await page.locator('[data-queue-totals]').innerText())) throw Error('First bounded batch not exactly 200');
  const totals=await page.locator('[data-queue-totals]').innerText(); await page.reload();
  if(totals!==await page.locator('[data-queue-totals]').innerText()) throw Error('Reload lost queue progress');
  if((await context.request.post(endpoint,{form:{...stale,operation:'continue'}})).status()!==409) throw Error('Stale continuation accepted');
  await click('Continue next 200 recipients');
  await button('Record schedule intent').waitFor();
  // HTML validation and server validation do not lose unsaved local input.
  const schedule=page.locator('[data-queue-schedule]');
  await schedule.fill('2020-01-01T12:00'); await click('Record schedule intent',false);
  if(await schedule.inputValue()!=='2020-01-01T12:00') throw Error('Validation lost schedule input');
  await capture('schedule-error',size); await click('Reload current state');
  await schedule.fill('2099-06-01T12:34');
  await click('Record schedule intent');
  await page.getByText(/2099-06-01 12:34:00.*Europe\/Istanbul/).first().waitFor();
  await capture('scheduled',size);
  const rival=await context.newPage(); await rival.goto(saved);
  const rivalFields=await rival.evaluate(()=>{const b=document.querySelector('[data-campaign-queue]'),j=b.querySelector('[data-queue-job]');return {action:'peracrm_campaign_queue',id:b.dataset.id,nonce:b.dataset.nonce,service_nonce:b.dataset.serviceNonce,job:j.dataset.queueJob,version:j.dataset.queueVersion};});
  await schedule.fill('2099-06-02T13:45');
  await click('Pause job');
  await rival.locator('[data-queue-feedback]').filter({hasText:'Another tab changed'}).waitFor();
  if(await schedule.inputValue()!=='2099-06-02T13:45') throw Error('Pause discarded input');
  if((await context.request.post(endpoint,{form:{...rivalFields,operation:'schedule',schedule:'2099-06-03T10:00'}})).status()!==409) throw Error('Competing stale schedule accepted');
  await click('Reload current state');
  if(await schedule.inputValue()!=='2099-06-02T13:45') throw Error('Refresh discarded input');
  await click('Resume job');
  await click('Record schedule intent'); // Explicitly save the retained input.
  await rival.locator('[data-queue-action=state]').click();
  await rival.locator('[data-queue-feedback]').filter({hasText:'Server state loaded'}).waitFor();
  if(await rival.locator('[data-queue-schedule]').inputValue()!=='2099-06-02T13:45') throw Error('Untouched stale tab did not load the newer server schedule');
  await rival.close();
  await click('View recipient details');
  const first=await page.locator('[data-queue-recipients] li').allTextContents();
  if(first.length!==200) throw Error('Recipient page not bounded at 200');
  await click('Next recipient page');
  const second=await page.locator('[data-queue-recipients] li').allTextContents();
  if(!second.length || second.length>200 || second.some(s=>first.includes(s))) throw Error('Recipient pages overlap');
  await context.addCookies([{name:'freeze_fixture_no_subscribers',value:'1',url:target}]);
  await page.reload();
  if(await button('View recipient details').count()) throw Error('Campaign-only details control');
  const scoped=await fields();
  if((await context.request.post(endpoint,{form:{...scoped,operation:'recipients'}})).status()!==403) throw Error('Campaign-only recipient handler accepted');
  await capture('campaign-only',size);
  await context.clearCookies({name:'freeze_fixture_no_subscribers'}); await page.reload();
  confirm=false; await button('Cancel job permanently').click();
  if(!await button('Cancel job permanently').count()) throw Error('Dismissed cancel changed state');
  confirm=true; await click('Cancel job permanently');
  await page.getByText(/Terminal cancellation/).waitFor(); await capture('cancelled',size);
  const cancelled=await fields();
  if((await context.request.post(endpoint,{form:{...cancelled,operation:'resume'}})).ok()) throw Error('Cancelled job resumed');
  await page.goto(target+'/crm/campaigns/'+seeds.outcomes.campaign+'/queue/'+seeds.outcomes.job+'/');
  await page.getByText(/Unknown outcomes require reconciliation/).first().waitFor();
  if(await page.locator('[data-queue-count=unknown]').innerText()!=='1' || await page.locator('[data-queue-count=failed]').innerText()!=='1') throw Error('Failed/unknown counts absent');
  await page.getByText('Failed job: no. Failed deliveries: 1. These are separate conditions.',{exact:true}).waitFor();
  await capture('unknown',size);
  await click('Pause job'); await click('Resume job'); // Evidence hash below proves unknown rows are never reset.
  // Empty page uses bounded keyset cursor; no unbounded offset scan.
  await page.goto(target+'/crm/campaigns/'+seeds[size].campaign+'/queue/?before=1&revisions_before=1');
  await page.getByText('No queue jobs on this page.',{exact:false}).waitFor(); await capture('empty',size);
  console.log('PASS Phase 4D '+size+': navigation, published-only/idempotent prepare, bounded batches/rollback/reload/recovery, Istanbul schedule, stale tabs, pause/resume/terminal cancel, recipient privacy, unknown/failed visibility and empty states');
 }
 await fixture('impersonate');
 if((await context.request.get(target+'/crm/campaigns/queue/')).status()!==403) throw Error('Impersonated queue page accepted');
 if((await context.request.post(endpoint,{form:{action:'peracrm_campaign_queue',operation:'state'}})).status()!==403) throw Error('Impersonated queue handler accepted');
 await fixture('restore');
 const after=await evidence();
 if(JSON.stringify(before)!==JSON.stringify(after) || after.mail!==0 || after.http!==0) throw Error('Queue UI changed protected data, frozen history, worker events, unresolved ledger, cron/metadata or attempted delivery '+JSON.stringify({before,after}));
 console.log('PASS Phase 4D browser: unchanged protected/frozen/worker/unknown ledger/cron/scheduler snapshots; no worker invocation, token issuance, mail or HTTP delivery');
};
