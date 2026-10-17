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
 await publicPage.reload(); // Scanner/repeated GET must leave deliberate POST available.
 await page.goto(staff+'&search=phase2-real');
 if((await page.locator('tbody tr').filter({hasText:'phase2-real@example.test'}).locator('td').nth(1).innerText())!=='subscribed') throw Error('Scanner GET mutated consent');
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
 await page.goto(staff+'&search=phase2-real');
 if((await page.locator('tbody tr').filter({hasText:'phase2-real@example.test'}).locator('td').nth(1).innerText())!=='unsubscribed') throw Error('Public POST failed to withdraw');
 await page.screenshot({path:'/evidence/subscribers-after-public-post.png',fullPage:true});
 if(failures.length) throw Error(failures.join('\n'));
 // Emit small synthetic-only review images so an API-only reviewer can inspect them.
 for(const name of ['subscribers-desktop','subscribers-mobile','detail-mobile','import-mobile','suppression-mobile','preferences-mobile']) {
  const review = await browser.newPage({viewport:{width:name.includes('desktop')?720:390,height:844}});
  await review.setContent('<html><body style="margin:0"><img style="width:100%;display:block" src="data:image/png;base64,'+fs.readFileSync('/evidence/'+name+'.png').toString('base64')+'"></body></html>');
  await review.locator('img').evaluate(img=>img.decode());
  console.log('RENDER_REVIEW '+name+' '+(await review.screenshot({type:'jpeg',quality:30,fullPage:true})).toString('base64'));
  await review.close();
 }
 await browser.close(); console.log('PASS browser: desktop/mobile subscribers, import, suppression; default theme; public GET/privacy, repeated POST, invalid token and anonymous route denial');
})().catch(error=>{console.error(error);process.exit(1)});
