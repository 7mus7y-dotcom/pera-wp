// Synthetic fixture only. No authenticated browser context, nonce or cookies.
module.exports = async ({browser}) => {
 const f = await (await fetch('http://fixture.test/withdrawal-fixture.json')).json();
 const target = f.target.replace(/\/$/,'');
 const evidence = async () => (await fetch(target+'/?peracrm_recipient_withdrawal=evidence')).text();
 const route = (token,machine=false) => target+'/?peracrm_recipient_withdrawal='+(machine?'machine':'visible')+'&token='+encodeURIComponent(token);
 const expect = (ok,label) => { if(!ok) throw Error('Withdrawal HTTP: '+label); };
 const before = await evidence();
 for(const method of ['GET','HEAD']) {
  const r = await fetch(route(f.machine),{method});
  expect(r.status===200,'scanner '+method);
  expect((r.headers.get('cache-control')||'').includes('no-store') && r.headers.get('referrer-policy')==='no-referrer','privacy headers');
  if(method==='HEAD') expect((await r.text())==='','HEAD has no body');
  expect((await fetch(route(f.machine,true),{method})).status===405,'machine rejects scanner');
 }
 for(const token of ['malformed',f.preferences,f.revoked]) {
  const r=await fetch(route(token,true),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'List-Unsubscribe=One-Click'});
  expect(r.status===400,'invalid token generic failure');
  expect(!(await r.text()).includes(token),'error redaction');
 }
 expect(await evidence()===before,'scanner and invalid requests SQL unchanged');
 const ctx = await browser.newContext(); const page = await ctx.newPage();
 const requests=[]; page.on('request',r=>requests.push(r));
 for(const [name,width,height] of [['desktop',1440,1000],['mobile',390,844]]) {
  await page.setViewportSize({width,height});
  const unchanged=await evidence();
  await page.goto(route(f[name]));
  await page.getByRole('button',{name:'Unsubscribe from all marketing email'}).waitFor();
  expect(await evidence()===unchanged,'visible confirmation GET read only');
  expect(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),'responsive confirmation');
  expect(requests.every(r=>r.resourceType()==='document'),'no assets or analytics');
  await page.screenshot({path:'/evidence/withdrawal-'+name+'.png',fullPage:true});
  await page.getByRole('button',{name:'Unsubscribe from all marketing email'}).click();
  expect((await page.getByRole('status').innerText()).includes('has been withdrawn'),'human confirmation commits');
  expect(!page.url().includes('token='),'POST action has no bearer URL');
  const committed=await evidence();
  const r=await fetch(target+'/email-unsubscribe/',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({token:f[name],confirm:'withdraw'})});
  expect(r.status===200 && await evidence()===committed,'duplicate visible POST idempotent');
 }
 const post = () => fetch(route(f.machine,true),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'List-Unsubscribe=One-Click'});
 expect((await post()).status===200,'anonymous machine POST');
 const committed=await evidence();
 expect((await post()).status===200 && await evidence()===committed,'duplicate machine POST idempotent');
 expect((await ctx.cookies()).length===0,'no login or cookie dependency');
 await ctx.close();
 console.log('PASS withdrawal HTTP: scanner GET/HEAD, machine POST, generic errors, SQL invariance, duplicate requests, desktop/mobile explicit confirmation, no assets/cookies');
};
