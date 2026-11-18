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
 if(await panel.getByRole('button',{name:'Add to mailing list',exact:true}).count()) throw Error('Existing subscriber exposes add action');
 await panel.getByRole('link',{name:'Manage subscriber',exact:true}).click();
 await page.getByRole('button',{name:'Withdraw marketing permission',exact:true}).click();
 await page.goto(clientURL);
 await panel.locator('[data-client-mailing-status="suppressed"]').waitFor();
 if(await panel.locator('[name="permission_confirmed"]').count()) throw Error('Withdrawn client exposes permission override');
 await page.goto(target+'/crm/client/'+fixture.blocked+'/');
 await panel.locator('[data-client-mailing-status="suppressed"]').waitFor();
 if(await panel.locator('form').count()) throw Error('Suppression-only client exposes permission override');
 await context.close();
};
