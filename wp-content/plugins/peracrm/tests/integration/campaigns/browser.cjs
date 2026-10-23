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
  if(name==='mobile') {
   await page.locator('[data-crm-nav-toggle]').click();
   await page.locator('[data-crm-nav-drawer]').waitFor({state:'visible'});
   await page.locator('[data-crm-nav-close]').click();
   await page.locator('[data-crm-nav-drawer]').waitFor({state:'hidden'});
  }
  if (await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)) {
   await page.screenshot({path:'/evidence/overflow-'+name+'.png',fullPage:true});
   console.log(await page.evaluate(()=>JSON.stringify([...document.querySelectorAll('body *')].filter(el=>el.getBoundingClientRect().right>innerWidth+1).slice(0,12).map(el=>({tag:el.tagName,cls:el.className,right:el.getBoundingClientRect().right})))));
   throw Error('Subscriber page horizontal overflow '+name);
  }
  await page.screenshot({path:'/evidence/subscribers-'+name+'.png',fullPage:true});
  await page.locator('.peracrm-subscriber-table tbody a').first().click();
  await page.getByRole('button',{name:'Save profile',exact:true}).waitFor();
  await page.screenshot({path:'/evidence/detail-'+name+'.png',fullPage:true});
  await page.getByRole('link',{name:'CSV preview and import',exact:true}).click();
  await page.getByRole('heading',{name:'Safe subscriber intake'}).waitFor();
  await page.screenshot({path:'/evidence/import-'+name+'.png',fullPage:true});
  await page.getByRole('link',{name:'Suppression intake',exact:true}).first().click();
  await page.getByRole('heading',{name:'Record a historical suppression'}).waitFor();
  await page.screenshot({path:'/evidence/suppression-'+name+'.png',fullPage:true});
 }
 // Real staff forms: unrelated edits and crafted requests preserve masked links.
 const scopedContext=await browser.newContext();
 const scoped=await scopedContext.newPage();
 await scoped.goto(target+'/wp-login.php');
 await scoped.locator('#user_login').fill('fixture-subscriber-scoped');
 await scoped.locator('#user_pass').fill('fixture-scoped-only');
 await Promise.all([scoped.waitForURL(url=>!url.pathname.endsWith('wp-login.php')),scoped.locator('#wp-submit').click()]);
 const hiddenURL=target+'/crm/subscribers/'+fixture.hidden_id+'/';
 await scoped.goto(hiddenURL);
 if(await scoped.getByRole('button',{name:'Unlink client',exact:true}).count()) throw Error('Hidden client exposes unlink control');
 await scoped.locator('input[name="language"]').fill('de');
 await scoped.locator('input[name="topics"]').fill('news|property');
 await scoped.getByRole('button',{name:'Save profile',exact:true}).click();
 await scoped.locator('input[name="language"][value="de"]').waitFor().catch(async(error)=>{throw Error('Scoped profile submission: '+scoped.url()+' '+(await scoped.locator('body').innerText()).slice(0,1000)+' '+error.message);});
 const crafted=async(operation,client)=>scoped.evaluate(async({operation,client})=>{
  const form=document.querySelector('input[name="operation"][value="profile"]').form;
  const body=new URLSearchParams(new FormData(form));
  body.set('operation',operation); body.set('client_id',String(client)); body.set('client_hidden','0');
  await fetch(form.action,{method:'POST',body});
 },{operation,client});
 await crafted('profile','');
 await crafted('unlink_client','');
 await crafted('link_client',fixture.replacement);
 await page.goto(hiddenURL);
 if(!(await page.getByRole('link',{name:'Open linked client',exact:true}).count())) throw Error('Crafted scoped request removed hidden client');
 if(await page.locator('input[name="language"]').inputValue()!=='de') throw Error('Profile edit missing');
 await page.getByRole('button',{name:'Unlink client',exact:true}).click();
 if(await page.getByRole('link',{name:'Open linked client',exact:true}).count()) throw Error('Authorized explicit unlink failed');
 if(!(await page.locator('main').innerText()).includes('unsubscribe')) throw Error('Unlink cleared suppression');
 const staleURL=target+'/crm/subscribers/'+fixture.stale_id+'/';
 await scoped.goto(staleURL);
 if(await scoped.getByRole('button',{name:'Unlink client',exact:true}).count()) throw Error('Scoped staff exposed deleted-link repair');
 await crafted('unlink_client','');
 for(const [name,width,height] of [['desktop',1440,1000],['mobile',390,844]]) {
  await page.setViewportSize({width,height});
  await page.goto(staleURL);
  await page.getByRole('button',{name:'Unlink client',exact:true}).waitFor();
  if(await page.getByRole('link',{name:'Open linked client',exact:true}).count()) throw Error('Deleted link exposes client URL');
  if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)) throw Error('Deleted-link repair horizontal overflow '+name);
  await page.screenshot({path:'/evidence/deleted-link-'+name+'.png',fullPage:true});
 }
 await page.getByRole('button',{name:'Unlink client',exact:true}).click();
 await page.reload();
 if(await page.getByRole('button',{name:'Unlink client',exact:true}).count()) throw Error('Deleted-link repair did not persist');
 if(!(await page.locator('main').innerText()).includes('unknown')) throw Error('Deleted-link repair changed consent');
 await scopedContext.close();
 // Exercise the real upload/nonce/preview/apply path, with a formula-like mailbox.
 await page.goto(target+'/crm/subscribers/import/');
 await page.locator('input[name="csv"]').setInputFiles({name:'synthetic.csv',mimeType:'text/csv',buffer:Buffer.from('email,state,source,source_ref,evidence_ref,policy_version,occurred_at\n=browser@example.test,subscribed,fixture,src,consent,v1,2020-01-01 00:00:00\n')});
 for(const [i,key] of ['email','state','source','source_ref','evidence_ref','policy_version','occurred_at'].entries()) await page.locator('input[name="mapping['+key+']"]').fill(String(i));
 await page.locator('input[name="synthetic"]').check();
 await page.locator('input[name="suppression_complete"]').check();
 await page.getByRole('button',{name:'Create preview only',exact:true}).click();
 await page.getByRole('heading',{name:'Batch preview',exact:true}).waitFor();
 await page.screenshot({path:'/evidence/actual-upload-preview.png',fullPage:true});
 const previewURL=page.url();
 await page.goto(staff+'&search=browser%40example.test');
 if(!(await page.locator('main').innerText()).includes('0 subscribers')) throw Error('Upload preview changed subscriber list');
 await page.goto(previewURL);
 const downloadEvent=page.waitForEvent('download');
 await page.getByRole('button',{name:'Download safe preview CSV',exact:true}).click();
 const download=await downloadEvent;
 const csv=fs.readFileSync(await download.path(),'utf8');
 if(!csv.includes("'=browser@example.test")) throw Error('Download formula injection defense missing');
 await page.getByRole('button',{name:'Apply next 25 rows',exact:true}).click();
 if(!(await page.locator('main').innerText()).includes('Status: complete')) throw Error('Browser batch apply did not complete');
 await page.goto(staff+'&search=browser%40example.test');
 if(!(await page.locator('main').innerText()).includes('1 subscribers')) throw Error('Browser batch apply missing subscriber');
 const missingNonce=await context.request.post(target+'/wp-admin/admin-post.php',{form:{action:'peracrm_subscribers',operation:'withdraw',subscriber_id:'1'}});
 if(missingNonce.status()!==403) throw Error('Missing staff nonce accepted');
 const employeeContext=await browser.newContext(); const employeePage=await employeeContext.newPage();
 await employeePage.goto(target+'/wp-login.php');
 await employeePage.locator('#user_login').fill('campaign-fixture-employee');
 await employeePage.locator('#user_pass').fill('fixture-employee-only');
 await Promise.all([employeePage.waitForURL(url=>!url.pathname.endsWith('wp-login.php')),employeePage.locator('#wp-submit').click()]);
 if((await employeePage.goto(staff)).status()!==403) throw Error('Employee subscriber route accepted');
 const employeePost=await employeeContext.request.post(target+'/wp-admin/admin-post.php',{form:{action:'peracrm_subscribers',operation:'preview'}});
 if(employeePost.status()!==403) throw Error('Employee import handler accepted');
 const anon=await browser.newContext(); const publicPage=await anon.newPage();
 const url=target+'/?peracrm_email_preferences=1&token='+fixture.token;
 let response=await publicPage.goto(url);
 if (!response.headers()['cache-control'].includes('no-store') || response.headers()['referrer-policy']!=='no-referrer') throw Error('Public privacy headers');
 await publicPage.getByRole('button',{name:'Unsubscribe from all marketing email'}).waitFor();
 if(!(await publicPage.locator('main').evaluate(el=>getComputedStyle(el).fontFamily.includes('system-ui')))) throw Error('Public plugin stylesheet was not applied');
 await publicPage.reload(); // Scanner/repeated GET must leave deliberate POST available.
 await page.goto(staff+'&search=phase2-real');
 if((await page.locator('tbody tr').filter({hasText:'phase2-real@example.test'}).locator('td').nth(1).innerText())!=='subscribed') throw Error('Scanner GET mutated consent');
 await publicPage.setViewportSize({width:390,height:844});
 if(await publicPage.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)) throw Error('Public preference page horizontal overflow');
 await publicPage.screenshot({path:'/evidence/preferences-mobile.png',fullPage:true});
 await publicPage.getByLabel('Property updates',{exact:true}).uncheck();
 await publicPage.getByRole('button',{name:'Save topic choices',exact:true}).click();
 await publicPage.getByRole('status').filter({hasText:'topic preferences have been saved'}).waitFor();
 await page.goto(staff+'&search=phase2-real');
 if((await page.locator('tbody tr').filter({hasText:'phase2-real@example.test'}).locator('td').nth(1).innerText())!=='subscribed') throw Error('Topic POST changed global consent');
 // Submit to the canonical form action; pretty endpoint must resolve on the target blog.
 await publicPage.getByRole('button',{name:'Unsubscribe from all marketing email'}).click();
 await publicPage.getByRole('status').filter({hasText:'withdrawn'}).waitFor();
 await publicPage.getByRole('button',{name:'Unsubscribe from all marketing email'}).click();
 await publicPage.getByRole('status').filter({hasText:'withdrawn'}).waitFor();
 await publicPage.goto(target+'/?peracrm_email_preferences=1&token='+'0'.repeat(64));
 if (await publicPage.getByRole('button',{name:'Unsubscribe from all marketing email'}).count()) throw Error('Invalid token revealed form');
 response=await publicPage.goto(staff);
 if (response.status()!==403) throw Error('Anonymous staff read accepted');
 await page.goto(staff+'&search=phase2-real');
 if((await page.locator('tbody tr').filter({hasText:'phase2-real@example.test'}).locator('td').nth(1).innerText())!=='unsubscribed') throw Error('Public POST failed to withdraw');
 await page.screenshot({path:'/evidence/subscribers-after-public-post.png',fullPage:true});
 for (const route of ['/crm/campaigns/','/crm/campaigns/new/','/crm/campaigns/configuration/','/?pera_crm=1&pera_crm_view=campaigns']) {
  if ((await employeePage.goto(target+route)).status()!==403) throw Error('Employee campaign read accepted '+route);
 }
 const employeeCampaignPost=await employeeContext.request.post(target+'/crm/campaigns/new/',{form:{operation:'save',revision:'0','document[subject]':'Forbidden'}});
 if(employeeCampaignPost.status()!==403) throw Error('Employee campaign write accepted');
 const campaignNonce=await context.request.post(target+'/crm/campaigns/new/',{form:{operation:'save',revision:'0','document[subject]':'No nonce'}});
 if(campaignNonce.status()!==403) throw Error('Campaign save without nonce accepted');
 await require('./draft-browser.cjs')({browser,page,target});
 await require('./preview-browser.cjs')({page,target,employeeContext,context});
 await require('./change-tracking-browser.cjs')({page,target});
 await require('./freeze-browser.cjs')({page,target,context,employeeContext,browser});
 await require('./queue-browser.cjs')({page,target,context,employeeContext,browser});
 await require('./withdrawal-browser.cjs')({browser});
 await require('./recipient-preferences-browser.cjs')({browser});
 if(failures.length) throw Error(failures.join('\n'));
 // Emit small synthetic-only review images so an API-only reviewer can inspect them.
 for(const name of ['freeze-review-desktop','freeze-review-mobile','campaign-audience-desktop','campaign-audience-mobile','campaign-content-desktop','campaign-content-mobile','campaign-preview-desktop','campaign-preview-mobile','campaign-missing-desktop','campaign-missing-mobile','subscribers-desktop','subscribers-mobile','detail-mobile','import-mobile','suppression-mobile','preferences-mobile','campaign-list-desktop','campaign-list-mobile','campaign-editor-desktop','campaign-editor-mobile','campaign-configuration-desktop','campaign-configuration-mobile','campaign-conflict-mobile','campaign-approved-mobile']) {
  const review = await browser.newPage({viewport:{width:name.includes('desktop')?720:390,height:844}});
  await review.setContent('<html><body style="margin:0"><img style="width:100%;display:block" src="data:image/png;base64,'+fs.readFileSync('/evidence/'+name+'.png').toString('base64')+'"></body></html>');
  await review.locator('img').evaluate(img=>img.decode());
  console.log('RENDER_REVIEW '+name+' '+(await review.screenshot({type:'jpeg',quality:30,fullPage:true})).toString('base64'));
  await review.close();
 }
 await browser.close(); console.log('PASS browser: desktop/mobile subscribers, import, suppression; default theme; public GET/privacy, repeated POST, invalid token and anonymous route denial');
})().catch(error=>{console.error(error);process.exit(1)});
