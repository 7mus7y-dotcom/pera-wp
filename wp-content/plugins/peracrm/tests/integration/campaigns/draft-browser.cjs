// Invoked in the existing disposable default-theme browser session.
module.exports = async function ({browser,page,target}) {
  const configuration=target+'/crm/campaigns/configuration/';
  const assertNoOverflow=async(label)=>{if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)) throw Error('Campaign overflow '+label);};
  page.on('dialog',dialog=>dialog.accept());
  for(const [name,width,height] of [['desktop',1440,1000],['mobile',390,844]]) {
    await page.setViewportSize({width,height});
    await page.goto(target+'/crm/campaigns/');
    await page.getByRole('heading',{name:'Draft campaigns',exact:true}).waitFor();
    await assertNoOverflow('list '+name);
    await page.screenshot({path:'/evidence/campaign-list-'+name+'.png',fullPage:true});
    await page.getByRole('link',{name:'Create campaign',exact:true}).click();
    await page.getByRole('heading',{name:'New draft',exact:true}).waitFor();
    await page.getByLabel('Internal name',{exact:true}).fill('Browser draft '+name);
    await page.getByLabel('Subject',{exact:true}).fill('Browser subject '+name);
    await page.getByLabel('Preheader',{exact:true}).fill('A preview line');
    await page.getByLabel('Explicit language (for example en)',{exact:true}).fill('en');
    await page.getByLabel('Introduction',{exact:true}).fill('Introduction from the structured editor.');
    await page.getByLabel('Main text',{exact:true}).fill('Main content saved without sender/footer approval.');
    await page.getByRole('button',{name:'Add text block',exact:true}).click();
    await page.getByLabel('Heading',{exact:true}).fill('Text heading');
    await page.getByLabel('Text',{exact:true}).fill('A bounded plain text block.');
    await page.getByRole('button',{name:'Add CTA block',exact:true}).click();
    await page.getByLabel('Button label',{exact:true}).fill('Read more');
    await page.getByLabel('Link URL',{exact:true}).fill('https://example.test/news');
    await page.locator('[data-campaign-block]').last().getByRole('button',{name:'Move block up',exact:true}).click();
    if(await page.locator('[data-campaign-block]').first().locator('input[type=hidden]').inputValue()!=='cta') throw Error('Move up control failed');
    await page.locator('[data-campaign-block]').first().getByRole('button',{name:'Move block down',exact:true}).click();
    if(await page.locator('[data-campaign-block]').first().locator('input[type=hidden]').inputValue()!=='text') throw Error('Move down control failed');
    await page.getByRole('button',{name:'Add text block',exact:true}).click();
    await page.locator('[data-campaign-block]').last().getByRole('button',{name:'Remove block',exact:true}).click();
    if(await page.locator('[data-campaign-block]').count()!==2) throw Error('Remove block control failed');
    await page.locator('[data-campaign-status]').filter({hasText:'Unsaved changes'}).waitFor();
    await page.getByRole('button',{name:'Save draft',exact:true}).click();
    await page.locator('[data-campaign-status]').filter({hasText:'Saved'}).waitFor();
    await page.reload();
    if(await page.getByLabel('Subject',{exact:true}).inputValue()!=='Browser subject '+name || await page.locator('[data-campaign-block]').count()!==2) throw Error('Draft reload lost content');
    // No approval is necessary for the first draft save.
    if(name==='desktop' && !(await page.locator('[data-campaign-missing]').innerText()).includes('Approved mandatory footer')) throw Error('Missing footer requirement absent');
    await assertNoOverflow('editor '+name);
    await page.screenshot({path:'/evidence/campaign-editor-'+name+'.png',fullPage:true});
    const draftURL=page.url().split('?')[0];
    // Invalid URL server validation: field content and stale revision are retained.
    await page.getByLabel('Link URL',{exact:true}).fill('javascript:alert(1)');
    const invalidResponse=page.waitForResponse(r=>r.request().method()==='POST' && r.url().startsWith(draftURL));
    await page.getByRole('button',{name:'Save draft',exact:true}).click();
    if((await invalidResponse).status()!==422) throw Error('Invalid draft did not return 422');
    await page.locator('[data-campaign-error]').waitFor();
    if(await page.getByLabel('Link URL',{exact:true}).inputValue()!=='javascript:alert(1)' || await page.getByLabel('Subject',{exact:true}).inputValue()!=='Browser subject '+name) throw Error('Validation lost user input');
    await page.getByLabel('Link URL',{exact:true}).fill('https://example.test/corrected');
    await page.getByRole('button',{name:'Save draft',exact:true}).click();
    // Two actual editor tabs: stale browser input must not replace saved content.
    const other=await page.context().newPage();
    await other.goto(draftURL);
    await page.getByLabel('Subject',{exact:true}).fill('Winner '+name);
    await page.getByRole('button',{name:'Save draft',exact:true}).click();
    await other.getByLabel('Subject',{exact:true}).fill('Retained stale input '+name);
    const conflictResponse=other.waitForResponse(r=>r.request().method()==='POST' && r.url().startsWith(draftURL));
    await other.getByRole('button',{name:'Save draft',exact:true}).click();
    if((await conflictResponse).status()!==409) throw Error('Stale browser save did not conflict');
    await other.locator('[data-campaign-error]').filter({hasText:'newer revision'}).waitFor();
    if(await other.getByLabel('Subject',{exact:true}).inputValue()!=='Retained stale input '+name) throw Error('Conflict discarded user input');
    await other.screenshot({path:'/evidence/campaign-conflict-'+name+'.png',fullPage:true});
    await other.close();
    await page.reload();
    if(await page.getByLabel('Subject',{exact:true}).inputValue()!=='Winner '+name) throw Error('Conflict overwrote winner');
    await page.getByText('Immutable revision history',{exact:true}).click();
    await page.getByText('Revision 1 ·',{exact:false}).first().waitFor();
    // Real duplication control on list creates revision one without source history.
    await page.getByRole('link',{name:'Campaigns',exact:true}).first().click();
    const row=page.locator('tbody tr').filter({hasText:'Browser draft '+name}).first();
    await row.getByRole('button',{name:'Duplicate',exact:true}).click();
    if(!(await page.getByLabel('Internal name',{exact:true}).inputValue()).endsWith(' (copy)') || await page.locator('input[name=revision]').inputValue()!=='1') throw Error('Duplicate did not create fresh draft');
    await page.goto(configuration);
    await page.getByRole('heading',{name:'Administrator approvals',exact:true}).waitFor();
    await page.getByLabel('Stable sender key',{exact:true}).first().fill('browser');
    await page.getByLabel('From name',{exact:true}).first().fill('Pera editorial');
    await page.getByLabel('From email',{exact:true}).first().fill('editorial@example.test');
    await page.getByLabel('Reply-to email',{exact:true}).first().fill('reply@example.test');
    await page.getByLabel('Approve this identity and reply-to',{exact:true}).first().check();
    await page.getByRole('button',{name:'Add identity',exact:true}).click();
    await page.locator('[data-campaign-sender]').last().getByRole('button',{name:'Remove identity',exact:true}).click();
    await page.getByLabel('Mandatory footer: legal identity, postal address and required wording',{exact:true}).fill('Pera legal identity\n123 Example Street\nApproved editorial footer');
    await page.getByLabel('Approve mandatory footer',{exact:true}).check();
    await page.getByRole('button',{name:'Save configuration',exact:true}).click();
    await page.locator('[data-campaign-status]').filter({hasText:'Saved'}).waitFor();
    await assertNoOverflow('configuration '+name);
    await page.screenshot({path:'/evidence/campaign-configuration-'+name+'.png',fullPage:true});
    await page.goto(draftURL);
    await page.getByLabel('Approved sender identity',{exact:true}).selectOption('browser');
    await page.getByRole('button',{name:'Save draft',exact:true}).click();
    if(!(await page.locator('[data-campaign-missing]').innerText()).includes('Draft fields complete')) throw Error('Approved configuration not reflected');
    await page.screenshot({path:'/evidence/campaign-approved-'+name+'.png',fullPage:true});
  }
  const anon=await browser.newPage();
  for(const route of ['/crm/campaigns/','/crm/campaigns/new/','/crm/campaigns/configuration/','/?pera_crm=1&pera_crm_view=campaigns']) {
    const response=await anon.goto(target+route); if(response.status()!==403) throw Error('Anonymous campaign route accepted '+route);
  }
  await anon.close();
  console.log('PASS Phase 3A browser: actual list/create/edit/duplicate/save/reload, blocks add/move/remove, configuration approvals, validation retention and two-tab revision conflicts; desktop/mobile; bundled default theme');
};
