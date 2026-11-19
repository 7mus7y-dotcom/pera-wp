// Disposable client mailing forms with real WordPress nonces and authorization.
module.exports = async function ({browser}) {
 const fixture = await (await fetch('http://fixture.test/client-mailing-fixture.json')).json();
 const target = fixture.target.replace(/\/$/,'');
 const context = await browser.newContext();
 const page = await context.newPage();
 const clientURL=target+'/crm/client/'+fixture.client+'/';
 await page.goto(target+'/wp-login.php?redirect_to='+encodeURIComponent(clientURL));
 await page.locator('#user_login').fill('fixture-admin');
 await page.locator('#user_pass').fill('fixture-admin-only');
 await Promise.all([page.waitForURL(url=>!url.pathname.endsWith('wp-login.php')),page.locator('#wp-submit').click()]);
 const panel=page.locator('#crm-client-mailing-list');
 await page.goto(clientURL);
 await panel.locator('[data-client-mailing-status="missing"]').waitFor();
 for (const [name,width] of [['desktop',1440],['mobile',390]]) {
  await page.setViewportSize({width,height:1000});
  await panel.scrollIntoViewIfNeeded();
  const layout=await panel.evaluate(el=>{
   const group=el.parentElement;
   const danger=group.querySelector('.crm-danger-zone');
   const a=el.getBoundingClientRect(),b=danger?.getBoundingClientRect();
   const timeline=document.querySelector('[data-crm-panel="timeline"]');
   return {bottom:group.classList.contains('crm-client-bottom-panels'),afterTimeline:!!timeline && !!(timeline.compareDocumentPosition(group)&Node.DOCUMENT_POSITION_FOLLOWING),
    before:!!danger && !!(el.compareDocumentPosition(danger)&Node.DOCUMENT_POSITION_FOLLOWING),
    side:b && Math.abs(a.top-b.top)<2 && b.left>=a.right,
    stacked:b && b.top>=a.bottom && Math.abs(a.left-b.left)<2,
    paragraphMargins:[...el.querySelectorAll(':scope > p')].map(p=>getComputedStyle(p).marginBottom),gap:getComputedStyle(el).rowGap};
  });
  if(!layout.bottom || !layout.afterTimeline || !layout.before) throw Error('Mailing panel must precede Danger zone below the client timeline');
  if(name==='desktop'?!layout.side:!layout.stacked) throw Error('Incorrect mailing/Danger zone columns '+name);
  if(layout.gap!=='8px' || layout.paragraphMargins.some(m=>m!=='0px')) throw Error('Mailing spacing adds paragraph margins to grid gap');
  if(await panel.evaluate(el=>el.scrollWidth>el.clientWidth+1)) throw Error('Mailing panel overflow '+name);
  await panel.screenshot({path:'/evidence/client-mailing-'+name+'.png'});
 }
 const form=panel.locator('form');
 const denied=await context.request.post(target+'/wp-admin/admin-post.php',{form:{action:'peracrm_client_mailing',client_id:String(fixture.client),expected_email:'client-mailing-browser@example.test',permission_confirmed:'1',evidence_ref:'fixture reply'}});
 if(denied.status()!==403) throw Error('Client mailing accepted missing nonce');
 await form.locator('[name="evidence_ref"]').fill('synthetic client WhatsApp agreement');
 await form.locator('[name="permission_confirmed"]').check();
 await panel.getByRole('button',{name:'Add to mailing list',exact:true}).click();
 await panel.locator('[data-client-mailing-status="subscribed"]').waitFor();
 if((await panel.locator('[role="status"]').textContent())!=='Mailing permission saved.') throw Error('Permission save feedback missing');
 await panel.screenshot({path:'/evidence/client-mailing-subscribed-mobile.png'});
 if(await panel.getByRole('button',{name:'Add to mailing list',exact:true}).count()) throw Error('Existing subscriber exposes add action');
 await panel.getByRole('link',{name:'Manage subscriber',exact:true}).click();
 page.once('dialog',dialog=>dialog.accept());
 await Promise.all([
  page.waitForURL(url=>url.searchParams.get('subscriber_notice')==='saved'),
  page.getByRole('button',{name:'Withdraw marketing permission',exact:true}).click(),
 ]);
 await page.getByText('Suppressions: unsubscribe',{exact:true}).waitFor();
 await page.goto(clientURL);
 await panel.locator('[data-client-mailing-status="suppressed"]').waitFor();
 if(await panel.locator('[name="permission_confirmed"]').count()) throw Error('Withdrawn client exposes permission override');
 await page.goto(target+'/crm/client/'+fixture.blocked+'/');
 await panel.locator('[data-client-mailing-status="suppressed"]').waitFor();
 if(await panel.locator('form').count()) throw Error('Suppression-only client exposes permission override');
 await context.close();
};
