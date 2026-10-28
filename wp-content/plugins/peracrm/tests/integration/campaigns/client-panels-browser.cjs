// Disposable real WordPress browser checks; synthetic fixture credentials only.
module.exports = async function ({browser}) {
 const fixture = await (await fetch('http://fixture.test/client-panels-fixture.json')).json();
 const target = fixture.target.replace(/\/$/,'');
 const context = await browser.newContext();
 const page = await context.newPage();
 const errors=[];
 const clientURL=target+'/crm/client/'+fixture.client+'/';
 page.on('pageerror',error=>errors.push(page.url()+'\n'+(error.stack||error.message)));
 // Login directly to the workspace being tested, avoiding unrelated dashboard scripts.
 await page.goto(target+'/wp-login.php?redirect_to='+encodeURIComponent(clientURL));
 await page.locator('#user_login').fill('panel-employee');
 await page.locator('#user_pass').fill('synthetic-panel-only');
 await Promise.all([page.waitForURL(url=>!url.pathname.endsWith('wp-login.php')),page.locator('#wp-submit').click()]);
 const panel=key=>page.locator('[data-crm-optional-panel="'+key+'"]');
 const toggle=key=>page.locator('[data-crm-panel-toggle="'+key+'"]');
 const settle=async()=>{await page.waitForFunction(()=>document.querySelector('[data-crm-manage-panels]').getAttribute('aria-busy')==='false');};
 await page.goto(clientURL);
 await page.locator('[data-crm-manage-panels]').waitFor();
 const manager=page.locator('[data-crm-manage-panels]');
 if(await manager.locator('summary').count() || await manager.evaluate(el=>el.tagName!=='SECTION')) throw Error('Manage panels must be a permanent section');
 if(!await toggle('deals').isVisible()) throw Error('Visibility controls require expansion');
 for(const key of ['crm-client-summary','profile','status','reminders','notes','timeline']) {
  if(await page.locator('[data-crm-panel="'+key+'"]').getAttribute('hidden')!==null) throw Error('Protected panel hidden '+key);
  if(await page.locator('[data-crm-panel="'+key+'"] > header [data-crm-panel-hide]').count()) throw Error('Protected close button '+key);
 }
 const close=page.getByRole('button',{name:'Hide Deals panel',exact:true});
 await close.focus(); await page.keyboard.press('Enter'); await settle();
 if(await panel('deals').isVisible()) throw Error('Keyboard dismissal failed');
 if(!await manager.locator('.crm-section__title').evaluate(el=>el===document.activeElement)) throw Error('Dismissal focus must reach Manage panels heading');
 await page.reload(); await settle();
 if(await panel('deals').isVisible()) throw Error('Hidden preference did not survive reload');
 await toggle('deals').focus(); await page.keyboard.press('Space'); await settle();
 if(!await panel('deals').isVisible()) throw Error('Keyboard restore failed');
 await page.route('**/admin-ajax.php',async route=>{
  if((route.request().postData()||'').includes('peracrm_client_panels')) { await route.fulfill({status:500,contentType:'application/json',body:JSON.stringify({success:false,data:{message:'Synthetic save failure'}})}); }
  else { await route.continue(); }
 });
 await close.click(); await settle();
 if(!await panel('deals').isVisible() || !(await page.locator('[data-crm-panels-feedback]').innerText()).includes('Synthetic save failure')) throw Error('Failed save did not restore visible state and feedback');
 await page.unroute('**/admin-ajax.php');
 const ajax=async(data)=>page.evaluate(async({target,data})=>{
  const manager=document.querySelector('[data-crm-manage-panels]');
  const body=new URLSearchParams({action:'peracrm_client_panels',client_id:manager.dataset.clientId,nonce:manager.dataset.nonce,operation:'hide',panel:'deals',...data});
  const response=await fetch(target+'/wp-admin/admin-ajax.php',{method:'POST',body});
  return {status:response.status,json:await response.json()};
 },{target,data});
 if((await ajax({panel:'profile'})).status!==400) throw Error('Protected AJAX key accepted');
 if((await ajax({panel:'invalid'})).status!==400) throw Error('Unknown AJAX key accepted');
 if((await ajax({nonce:''})).status!==403 || (await ajax({nonce:'wrong'})).status!==403) throw Error('Missing/invalid AJAX nonce accepted');
 // Hide a property panel, then change type and stage with the existing status form.
 await page.getByRole('button',{name:'Hide Properties for sale panel',exact:true}).click(); await settle();
 await page.locator('#crm-status-form select[name="peracrm_client_type"]').selectOption('landlord');
 await page.locator('#crm-status-form select[name="lead_pipeline_stage"]').selectOption('qualified');
 await page.getByRole('button',{name:'Save status',exact:true}).click();
 await page.waitForFunction(()=>!document.querySelector('[data-crm-optional-panel="deals"]') && document.querySelector('[data-crm-optional-panel="landlord_properties"]'));
 if(await toggle('deals').count() || await toggle('seller_properties').count()) throw Error('Ineligible panels have toggles');
 if(!await page.getByText('Synthetic panel property',{exact:true}).first().isVisible()) throw Error('Historical instruction lost after type change');
 await page.locator('#crm-status-form select[name="peracrm_client_type"]').selectOption('seller');
 await page.locator('#crm-status-form select[name="lead_pipeline_stage"]').selectOption('deal_closed');
 await page.getByRole('button',{name:'Save status',exact:true}).click();
 await page.waitForFunction(()=>document.querySelector('[data-crm-optional-panel="deals"]') && document.querySelector('[data-crm-optional-panel="seller_properties"]'));
 if(await panel('seller_properties').isVisible() || !await panel('deals').isVisible()) throw Error('Type/stage change reset preferences');
 await page.getByRole('button',{name:'Restore all panels',exact:true}).click(); await settle();
 if(!await panel('seller_properties').isVisible()) throw Error('Restore all failed');
 for (const [name,width,height,scheme] of [['desktop',1440,1000,'light'],['tablet',820,1100,'light'],['mobile',390,844,'light'],['mobile-dark',390,844,'dark']]) {
  await page.setViewportSize({width,height}); await page.emulateMedia({colorScheme:scheme});
  if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)) throw Error('Client panels horizontal overflow '+name);
  for(const key of ['deals','seller_properties','enquiries','theme_portfolio']) {
   const header=panel(key).locator(':scope > .crm-section__header');
   const heading=await header.locator(':scope > :first-child').boundingBox();
   const button=await header.locator('[data-crm-panel-hide]').boundingBox();
   if(!heading||!button||heading.x+heading.width>button.x+1) throw Error('Dismiss overlaps heading '+key+' '+name);
  }
  if(!await toggle('deals').isVisible() || !await page.getByRole('button',{name:'Restore all panels',exact:true}).isVisible()) throw Error('Manage controls hidden '+name);
  for(const key of ['deals','seller_properties','enquiries','theme_portfolio']) {
   const row=toggle(key).locator('..');
   const label=await row.locator('label').boundingBox();
   const checkbox=await toggle(key).boundingBox();
   if(!label||!checkbox||label.x+label.width>checkbox.x+1) throw Error('Manage row overlaps '+key+' '+name);
   if(!await toggle(key).getAttribute('aria-describedby')) throw Error('Missing state description '+key);
  }
  await page.screenshot({path:'/evidence/client-panels-'+name+'.png',fullPage:true});
 }
 // Another user and another client must retain the default workspace.
 await page.getByRole('button',{name:'Hide Deals panel',exact:true}).click(); await settle();
 await page.goto(target+'/crm/client/'+fixture.other+'/'); await settle();
 if(!await panel('deals').isVisible()) throw Error('Visibility leaked across clients');
 const adminContext=await browser.newContext(); const admin=await adminContext.newPage();
 await admin.goto(target+'/wp-login.php?redirect_to='+encodeURIComponent(clientURL)); await admin.locator('#user_login').fill('fixture-admin'); await admin.locator('#user_pass').fill('fixture-admin-only');
 await Promise.all([admin.waitForURL(url=>!url.pathname.endsWith('wp-login.php')),admin.locator('#wp-submit').click()]);
 await admin.goto(clientURL); await admin.locator('[data-crm-optional-panel="deals"]').waitFor();
 await adminContext.close();
 if(errors.length) throw Error(errors.join('\n'));
 await context.close();
 console.log('PASS browser: client panels persistence, protected controls, nonce checks, keyboard, rollback, eligibility and responsive/dark layouts');
};
