module.exports=async ({browser})=>{
 const f=await (await fetch('http://fixture.test/recipient-preferences-fixture.json')).json();
 const target=f.target.replace(/\/$/,'');
 const route=(token,withdrawal='')=>target+'/email-preferences/?'+new URLSearchParams({token,...(withdrawal?{withdrawal_token:withdrawal}:{})});
 const evidence=async ()=>(await fetch(target+'/?peracrm_recipient_withdrawal=evidence')).text();
 const expect=(ok,label)=>{if(!ok)throw Error('Preference HTTP: '+label);};
 const before=await evidence();
 for(const token of [f.desktop,f.expired,f.revoked,f.wrong]) {
  for(const method of ['GET','HEAD']) {
   const r=await fetch(route(token),{method});
   expect(r.status===(token===f.desktop?200:400),'valid and invalid '+method);
   expect(r.headers.get('referrer-policy')==='no-referrer' && r.headers.get('cache-control').includes('no-store'),'private headers');
   if(method==='HEAD')expect(await r.text()==='','empty HEAD');
  }
 }
 expect(await evidence()===before,'reads and invalid links leave SQL unchanged');
 const context=await browser.newContext();const page=await context.newPage();
 const requests=[];page.on('request',r=>requests.push(r));
 for(const [name,width,height] of [['desktop',1440,1000],['mobile',390,844]]) {
  await page.setViewportSize({width,height});const unchanged=await evidence();
  await page.goto(route(f[name]));
  await page.getByRole('button',{name:'Save preferences',exact:true}).waitFor();
  expect(await evidence()===unchanged,'editor GET read only');
  expect(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),'responsive editor');
  expect(requests.every(r=>r.resourceType()==='document'||(r.resourceType()==='stylesheet'&&r.url().includes('/wp-content/plugins/peracrm/assets/frontend/crm.css'))),'only CRM-owned assets');
  const proof=await page.locator('[name=proof]').inputValue();
  const anonymousCookies=await context.cookies();
  const cookie=anonymousCookies.filter(c=>c.name.startsWith('peracrm_pref_')).map(c=>c.name+'='+c.value).join('; ');
  expect(anonymousCookies.every(c=>c.name.startsWith('peracrm_pref_')&&c.httpOnly&&c.sameSite==='Strict'),'only anonymous strict HttpOnly cookies');
  const body=new URLSearchParams({token:f[name],proof,language:'en','topics[]':'news'});
  for(const origin of ['http://evil.test','']) {
   const r=await fetch(target+'/email-preferences/',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded',Origin:origin,Cookie:cookie},body});
   expect(r.status===400,'cross-site edit denied');
  }
  expect((await fetch(target+'/email-preferences/',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded',Origin:'null','Sec-Fetch-Site':'cross-site',Cookie:cookie},body})).status===400,'cross-site metadata denied even with correct cookie/proof');
  const noProof=new URLSearchParams({token:f[name],language:'en','topics[]':'news'});
  expect((await fetch(target+'/email-preferences/',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded',Origin:'http://fixture.test'},body:noProof})).status===400,'form proof required');
  expect(await evidence()===unchanged,'cross-site and missing-proof edits SQL invariant');
  // Explicit header cases above cover foreign origins. The next direct request
  // uses null Origin and valid proof but omits the bound anonymous cookie.
  const unbound=await fetch(target+'/email-preferences/',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded',Origin:'null'},body});
  expect(unbound.status===400 && await evidence()===unchanged,'stolen proof without strict cookie denied');
  const rollback=await fetch(target+'/email-preferences/',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded',Origin:'http://fixture.test',Cookie:cookie+'; recipient_preferences_fail=1'},body});
  expect(rollback.status===400 && await evidence()===unchanged,'HTTP audit failure rolls back profile');
  await page.getByLabel('Preferred language tag').fill('tr');
  await page.getByLabel('News',{exact:true}).check();
  await page.screenshot({path:'/evidence/recipient-preferences-'+name+'.png',fullPage:true});
  const submission=page.waitForRequest(r=>r.method()==='POST');
  await page.getByRole('button',{name:'Save preferences',exact:true}).click();
  const submittedHeaders=await (await submission).allHeaders();
  expect((await page.getByRole('status').innerText()).includes('have been saved'),'anonymous edit succeeds; origin='+submittedHeaders.origin+'; fetch-site='+submittedHeaders['sec-fetch-site']);
  expect(!page.url().includes('token='),'clean form action');
  expect(await page.getByLabel('Preferred language tag').inputValue()==='tr' && await page.getByLabel('News',{exact:true}).isChecked(),'saved choices visible');
  const committed=await evidence();await page.getByRole('button',{name:'Save preferences',exact:true}).click();
  expect(await evidence()===committed,'duplicate edit SQL idempotent');
 }
 const expiredBefore=await evidence();
 await page.goto(route(f.expired));expect(await page.getByRole('button',{name:'Save preferences'}).count()===0,'expired link has no editor');
 expect((await page.locator('main').innerText()).includes('separate unsubscribe link'),'expired-only recovery instructions');
 await page.goto(route(f.expired,f.expired));expect(await page.getByRole('link',{name:'Unsubscribe from all marketing email'}).count()===0,'expired preference cannot act as withdrawal authority');
 await page.goto(route(f.expired,f.independent));
 await page.getByRole('link',{name:'Unsubscribe from all marketing email'}).waitFor();
 expect(await evidence()===expiredBefore,'expired navigation read only');
 await page.screenshot({path:'/evidence/recipient-preferences-expired.png',fullPage:true});
 await page.getByRole('link',{name:'Unsubscribe from all marketing email'}).click();
 await page.getByRole('button',{name:'Unsubscribe from all marketing email'}).waitFor();
 expect(await evidence()===expiredBefore,'withdrawal confirmation GET read only');
 await page.getByRole('button',{name:'Unsubscribe from all marketing email'}).click();
 expect((await page.getByRole('status').innerText()).includes('has been withdrawn'),'independent withdrawal from expired page');
 expect((await context.cookies()).every(c=>c.name.startsWith('peracrm_pref_')),'no WordPress login required');
 await context.close();console.log('PASS recipient preference HTTP/SQL: valid edits, expiry, wrong-purpose/revoked, CSRF, duplicate edits, independent withdrawal and desktop/mobile flows');
};
