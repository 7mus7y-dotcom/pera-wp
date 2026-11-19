// Browser-only worker responses; real SQL/signing/queue invariance is exercised in production-runtime.php.
module.exports=async function ({page}) {
  const panel=page.locator('[data-draft-test]');
  const button=panel.locator('[data-test-send]');
  if(await button.count()!==1 || await panel.locator('[data-test-command]').count()) throw Error('Real test form did not replace CLI instructions');
  if(await panel.locator('[data-test-inbox]').evaluate(el=>el.form!==null)) throw Error('Test inbox validation can block draft saving');
  await button.evaluate(el=>{el.dataset.configured='1';el.disabled=false;});
  await panel.locator('[data-test-inbox]').fill('owner@example.test');
  let submits=0,recipient='',requestID='',revision=0,release;
  const gate=new Promise(resolve=>{release=resolve;});
  const handler=async route=>{
    const body=route.request().postData() || '';
    if(!body.includes('test-submit') && !body.includes('test-status')) return route.fallback();
    const field=name=>{const match=body.match(new RegExp('name="'+name+'"\\r\\n\\r\\n([^\\r\\n]*)'));return match?.[1] || '';};
    if(body.includes('test-submit')) {
      submits++; requestID=field('request_id'); recipient=field('recipient'); revision=Number(field('revision'));
      if(!/^[a-f0-9]{32}$/.test(requestID) || recipient!=='owner@example.test' || revision<1 || !field('document_json')) throw Error('Invalid frontend test request');
      await gate;
      return route.fulfill({json:{success:true,data:{state:'queued',recipient,request_id:requestID,revision}}});
    }
    return route.fulfill({json:{success:true,data:submits ? {state:'accepted',recipient,request_id:requestID,revision} : {state:'idle'}}});
  };
  await page.route('**/admin-ajax.php',handler);
  try {
    await button.click();
    if(!await button.isDisabled()) throw Error('Test button permits a double-click while submitting');
    release();
    await panel.locator('[data-test-feedback]').filter({hasText:'Test accepted by SES'}).waitFor();
    if(submits!==1 || await button.isDisabled()) throw Error('Test result did not release the control exactly once');
    const subject=page.getByLabel('Subject',{exact:true});
    await subject.fill((await subject.inputValue())+' unsaved');
    if(!await button.isDisabled()) throw Error('Unsaved draft permits test send');
  } finally { release(); await page.unroute('**/admin-ajax.php',handler); }
  await page.reload();
};
