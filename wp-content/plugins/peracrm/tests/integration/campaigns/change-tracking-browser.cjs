// Acceptance for #1451's selector-only control finding. Exercise the actual editor and signed cursor.
module.exports = async function ({page,target}) {
  for(const [size,width,height] of [['desktop',1440,1000],['mobile',390,844]]) {
    await page.setViewportSize({width,height});
    await page.goto(target+'/crm/campaigns/new/');
    await page.getByLabel('Internal name',{exact:true}).fill('Change tracking '+size);
    await page.getByLabel('Subject',{exact:true}).fill('Saved tracking subject');
    await page.getByLabel('Explicit language (for example en)',{exact:true}).fill('en');
    for(const heading of ['First saved block','Second saved block']) {
      await page.getByRole('button',{name:'Add text block',exact:true}).click();
      const block=page.locator('[data-campaign-block]').last();
      await block.getByLabel('Heading',{exact:true}).fill(heading);
      await block.getByLabel('Text',{exact:true}).fill('Saved text');
    }
    await page.getByRole('button',{name:'Save draft',exact:true}).click();
    await page.locator('[data-campaign-status]').filter({hasText:'Saved'}).waitFor();
    const status=page.locator('[data-campaign-status]');
    const result=page.locator('[data-audience-result]');
    const next=page.locator('[data-audience-next]');
    const isAudience=response=>response.url().includes('/admin-ajax.php') && response.request().postData()?.includes('audience');
    async function prime() {
      const responsePromise=page.waitForResponse(isAudience);
      await page.getByRole('button',{name:'Preview audience counts',exact:true}).click();
      const json=await (await responsePromise).json();
      if(!json.success || json.data.complete || json.data.considered!==200 || !json.data.cursor) throw Error('Expected resumable 200-row audience batch');
      await result.filter({hasText:'Partial'}).waitFor();
      if(!(await next.isVisible())) throw Error('Partial progress has no continuation');
      return {cursor:json.data.cursor,status:await status.innerText(),text:await result.innerText()};
    }
    async function assertPreserved(snapshot,label) {
      if(await status.innerText()!==snapshot.status || await result.innerText()!==snapshot.text || !(await next.isVisible())) throw Error(label+' changed saved status or audience progress');
    }
    async function search(kind) {
      await page.getByLabel('Content kind',{exact:true}).selectOption(kind);
      await page.getByLabel('Search title',{exact:true}).fill('Phase3B');
      const responsePromise=page.waitForResponse(r=>r.url().includes('/admin-ajax.php') && r.request().postData()?.includes('search'));
      await page.getByRole('button',{name:'Search content',exact:true}).click();
      if(!(await (await responsePromise).json()).success) throw Error('Selector search failed');
      await page.locator('[data-content-results] button').first().filter({hasText:kind}).waitFor();
    }
    // Selector controls are not document fields. Verify preservation before, during and after HTTP search.
    const initial=await prime();
    await page.getByLabel('Search title',{exact:true}).fill('Phase3B');
    await assertPreserved(initial,'Typing search');
    await page.getByLabel('Content kind',{exact:true}).selectOption('property');
    await assertPreserved(initial,'Changing content kind');
    await search('property');
    await assertPreserved(initial,'Property search');
    await search('article');
    await assertPreserved(initial,'Article search');
    const pageResponse=page.waitForResponse(r=>r.url().includes('/admin-ajax.php') && r.request().postData()?.includes('search'));
    await page.getByRole('button',{name:'Next content page',exact:true}).click();
    await pageResponse;
    await page.waitForFunction(()=>document.querySelectorAll('[data-content-results] button').length===4);
    await assertPreserved(initial,'Selector pagination');
    const continuation=page.waitForResponse(isAudience);
    await next.click();
    const continuedResponse=await continuation;
    const continued=await continuedResponse.json();
    // The actual posted cursor must be the one issued before any selector interaction.
    const posted=continuedResponse.request().postData() || '';
    if(!posted.includes(initial.cursor) || !continued.success || continued.data.considered!==400 || await status.innerText()!==initial.status) throw Error('Selector interactions lost or restarted the signed audience cursor');
    console.log('PASS change tracking '+size+': selector typing/kind/search/pagination preserve saved status and exact audience cursor; continuation reaches 400');
    const mutations=[
      ['select article',async()=>{await search('article');await page.locator('[data-content-results] button').first().click();}],
      ['select property',async()=>{await search('property');await page.locator('[data-content-results] button').first().click();}],
      ['add text',async()=>page.getByRole('button',{name:'Add text block',exact:true}).click()],
      ['add CTA',async()=>page.getByRole('button',{name:'Add CTA block',exact:true}).click()],
      ['remove block',async()=>page.locator('[data-campaign-block]').first().getByRole('button',{name:'Remove block',exact:true}).click()],
      ['move up',async()=>page.locator('[data-campaign-block]').last().getByRole('button',{name:'Move block up',exact:true}).click()],
      ['move down',async()=>page.locator('[data-campaign-block]').first().getByRole('button',{name:'Move block down',exact:true}).click()],
      ['edit subject',async()=>page.getByLabel('Subject',{exact:true}).fill('Changed document field')],
      ['edit block text',async()=>page.locator('[data-campaign-block]').first().getByLabel('Text',{exact:true}).fill('Changed block field')],
      ['edit audience rule',async()=>page.getByLabel('Audience topic (exact key)',{exact:true}).fill('news')]
    ];
    for(const [label,mutate] of mutations) {
      await page.reload();
      await prime();
      await mutate();
      if(await status.innerText()!=='Unsaved changes' || !(await result.innerText()).includes('Form changed') || await next.isVisible()) throw Error(label+' did not mark dirty and invalidate audience progress');
      let requests=0;
      const record=request=>{if(request.url().includes('/admin-ajax.php') && request.postData()?.includes('audience')) requests++;};
      page.on('request',record);
      await page.getByRole('button',{name:'Preview audience counts',exact:true}).click();
      await result.filter({hasText:'Save the current draft and rules'}).waitFor();
      page.off('request',record);
      if(requests) throw Error(label+' allowed audience request with unsaved document');
      console.log('PASS change tracking '+size+': '+label+' marks dirty, clears continuation and refuses unsaved audience requests');
    }
    await page.reload();
  }
};
