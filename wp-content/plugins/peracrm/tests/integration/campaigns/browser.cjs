// Disposable fixture only, no production URL or credentials.
const { chromium } = require('playwright');
const fs = require('fs');
(async () => {
 const fixture = JSON.parse(await (await fetch('http://fixture.test/phase2-fixture.json')).text());
 const browser = await chromium.launch({args:['--no-sandbox']});
 const context = await browser.newContext();
 const page = await context.newPage();
 const failures = [];
 page.on('pageerror',e=>failures.push(e.message));
 const target = fixture.target.replace(/\/$/,'');
 await page.goto(target+'/wp-login.php');
 await page.locator('#user_login').fill('fixture-admin');
 await page.locator('#user_pass').fill('fixture-admin-only');
 await Promise.all([page.waitForURL(url=>!url.pathname.endsWith('wp-login.php')),page.locator('#wp-submit').click()]);
 // Actual plugin routes with WordPress's query-var fallback also exercise authorization.
 const staff = target+'/?pera_crm=1&pera_crm_view=subscribers';
 for (const [name,width,height] of [['desktop',1440,1000],['mobile',390,844]]) {
  await page.setViewportSize({width,height});
  await page.goto(staff);
  await page.getByRole('heading',{name:'Subscribers',exact:true}).waitFor();
  if (await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)) throw Error('Subscriber page horizontal overflow '+name);
  await page.screenshot({path:'/evidence/subscribers-'+name+'.png',fullPage:true});
  await page.getByRole('link',{name:'CSV preview and import',exact:true}).click();
  await page.getByRole('heading',{name:'Safe subscriber intake'}).waitFor();
  await page.screenshot({path:'/evidence/import-'+name+'.png',fullPage:true});
  await page.getByRole('link',{name:'Suppression intake',exact:true}).first().click();
  await page.getByRole('heading',{name:'Record a historical suppression'}).waitFor();
  await page.screenshot({path:'/evidence/suppression-'+name+'.png',fullPage:true});
 }
 const anon=await browser.newContext(); const publicPage=await anon.newPage();
 const url=target+'/?peracrm_email_preferences=1&token='+fixture.token;
 let response=await publicPage.goto(url);
 if (!response.headers()['cache-control'].includes('no-store') || response.headers()['referrer-policy']!=='no-referrer') throw Error('Public privacy headers');
 await publicPage.getByRole('button',{name:'Unsubscribe from all marketing email'}).waitFor();
 await publicPage.reload(); // Scanner/repeated GET must leave deliberate POST available.
 await publicPage.setViewportSize({width:390,height:844});
 await publicPage.screenshot({path:'/evidence/preferences-mobile.png',fullPage:true});
 // Submit to the canonical form action; pretty endpoint must resolve on the target blog.
 await publicPage.getByRole('button',{name:'Unsubscribe from all marketing email'}).click();
 await publicPage.getByRole('status').filter({hasText:'withdrawn'}).waitFor();
 await publicPage.getByRole('button',{name:'Unsubscribe from all marketing email'}).click();
 await publicPage.getByRole('status').filter({hasText:'withdrawn'}).waitFor();
 await publicPage.goto(target+'/?peracrm_email_preferences=1&token='+'0'.repeat(64));
 if (await publicPage.getByRole('button',{name:'Unsubscribe from all marketing email'}).count()) throw Error('Invalid token revealed form');
 response=await publicPage.goto(staff);
 if (response.status()!==403) throw Error('Anonymous staff read accepted');
 await page.goto(target+'/?pera_crm=1&pera_crm_view=subscribers');
 await page.screenshot({path:'/evidence/subscribers-after-public-post.png',fullPage:true});
 if(failures.length) throw Error(failures.join('\n'));
 await browser.close(); console.log('PASS browser: desktop/mobile subscribers, import, suppression; default theme; public GET/privacy, repeated POST, invalid token and anonymous route denial');
})().catch(error=>{console.error(error);process.exit(1)});
