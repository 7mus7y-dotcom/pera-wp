/** PHP_BIN=/path/to/php node tests/property-layout-responsive-test.js
 * Requires Playwright and Chromium (CHROMIUM_PATH may select the executable).
 * Uses actual template output and both production stylesheets; no live services.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
const theme = path.resolve(__dirname, '..');
const fixtures = JSON.parse(execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'property-editorial-test.php'), '--fixtures'], {encoding:'utf8'}));
const css = ['main.css', 'property.css'].map(file => fs.readFileSync(path.join(theme, 'css', file), 'utf8')).join('\n');
const renderer = fs.readFileSync(path.join(theme, 'inc/modules/nearby-amenities.php'), 'utf8');
// Mirror the unchanged renderer's direct link + span row, including bidi isolation.
assert.match(renderer, /<li><a href=.*?<bdi>.*?<\/bdi><\/a> <span class="text-soft text-nowrap"><bdi>.*?<\/bdi><\/span><\/li>/s);
const names = ['Osmanbey (M2)', 'Şişli-Mecidiyeköy', 'Şişli (M2)', 'Tramway (T1)', 'Funicular (F1)', 'A very long station name that must wrap safely within its own column', 'محطة ذات اسم طويل لاختبار التفاف النص بجوار المسافة'];
const categories = ['Metro', 'Marmaray', 'Metrobüs', 'Bus', 'Tramway', 'Funicular', 'Airports', 'Education', 'Healthcare', 'Parks and sport', 'Shopping'];
const poi = categories.map(category => `<div class="card-shell p-sm"><h3>${category}</h3><ul class="poi-list">${names.map((name,i) => `<li><a href="https://example.test/station/${i}"><bdi>${name}</bdi></a> <span class="text-soft text-nowrap"><bdi>${i ? '1.3 km' : '663 m'}</bdi></span></li>`).join('')}</ul></div>`).join('');
const locationFixtures = JSON.parse(execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'property-layout-test.php'), '--fixtures'], {encoding:'utf8'}));
const location = () => locationFixtures[0].replace('<button role="tab">Transport</button><div role="tabpanel">POI fixture</div>', `<div class="grid-2">${poi}</div>`);
const near = (a,b) => Math.abs(a-b)<1;
(async () => {
 const browser = await chromium.launch({headless:true, executablePath:process.env.CHROMIUM_PATH || '/usr/bin/chromium', args:['--no-sandbox']});
 try {
 const page = await browser.newPage();
 await page.route('**/*', route => route.abort());
 const load = async (html,width,dir='ltr') => {
   await page.setViewportSize({width,height:1000});
   await page.setContent(`<html dir="${dir}"><head><style>${css}</style></head><body>${html}</body></html>`);
 };
 const cards = () => page.locator('.property-editorial-grid > .card-shell').evaluateAll(nodes => nodes.map(n => {const r=n.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height,end:r.bottom,faq:n.querySelector('h2').textContent==='Property questions'};}));
 // Every optional-content combination must auto-place without holes, with FAQ below.
 for (let mask=1;mask<128;mask++) {
   await load(fixtures[mask],1280);
   const boxes=await cards(), regular=boxes.filter(b=>!b.faq), faq=boxes.find(b=>b.faq);
   for(let i=0;i<regular.length;i++) {
     if(i%2) { assert(near(regular[i].y,regular[i-1].y));assert(near(regular[i].w,regular[i-1].w));assert(regular[i].x>regular[i-1].x); }
     if(i>=2) assert(regular[i].y>=Math.max(...regular.slice(i-i%2-2,i-i%2).map(b=>b.end)));
   }
   if(faq) {const grid=await page.locator('.property-editorial-grid').boundingBox();assert(near(faq.w,grid.width));assert(regular.every(b=>faq.y>=b.end));}
 }
 await load(fixtures[23],1280);
 let boxes=await cards();assert.equal(boxes.length,4);assert(near(boxes[0].y,boxes[1].y));assert(near(boxes[2].y,boxes[3].y));assert(boxes[0].h>boxes[1].h,'natural unequal card heights');
 // Both mobile modes, both directions, every category and requested width.
 for(const width of [320,375,430]) for(const dir of ['ltr','rtl']) for(const dark of [false,true]) {
   await load(fixtures[127]+location(),width,dir);
   await page.emulateMedia({colorScheme:dark?'dark':'light'});
   boxes=await cards();for(let i=1;i<boxes.length;i++){assert(boxes[i].y>=boxes[i-1].end);assert(near(boxes[i].w,boxes[0].w));}
   const rows=await page.locator('.poi-list > li').evaluateAll(nodes=>nodes.map(n=>{
     const a=n.querySelector('a'),s=n.querySelector('span'), r=a.getBoundingClientRect(),d=s.getBoundingClientRect(),li=n.getBoundingClientRect();
     return {a:{x:r.x,right:r.right,top:r.top,w:r.width,h:r.height},d:{x:d.x,right:d.right,top:d.top,h:d.height},li:{x:li.x,right:li.right},nowrap:getComputedStyle(s).whiteSpace,line:parseFloat(getComputedStyle(a).lineHeight)};
   }));
   assert.equal(rows.length,categories.length*names.length);
   for(const r of rows){assert(near(r.a.top,r.d.top),'distance stays on first line');assert.equal(r.nowrap,'nowrap');assert(r.a.w>0);assert(r.a.right<=r.d.x || r.d.right<=r.a.x,'no overlap');assert(r.li.x>=0 && r.li.right<=width);if(dir==='ltr')assert(near(r.d.right,r.li.right));else assert(near(r.d.x,r.li.x));}
   assert(rows.some(r=>r.a.h>r.line*1.5),'long names wrap');
   const map=await page.locator('iframe').boundingBox();assert(near(map.width/map.height,1.6),'mobile map unchanged');
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'no page overflow');
 }
 // Actual location output: paired 40/60 columns and approved map geometry.
 for(const width of [1024,1280,1440]) for(const dir of ['ltr','rtl']) for(const dark of [false,true]) {
   await load(fixtures[127]+location(),width,dir);
   await page.emulateMedia({colorScheme:dark?'dark':'light'});
   const grid=await page.locator('.property-location__grid').boundingBox();
   const map=await page.locator('iframe').boundingBox();
   const gap=await page.locator('.property-location__grid').evaluate(n=>parseFloat(getComputedStyle(n).columnGap));
   assert(near(map.width,(grid.width-gap)*.4),'40/60 split');
   assert(near(map.width/map.height,1.28),'desktop map 32/25');
   assert.equal(await page.locator('.property-map-card__actions').evaluate(n=>getComputedStyle(n).marginTop),'16px');
   assert.equal(await page.locator('.property-location h2').count(),1);
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'no desktop overflow');
 }
 await load(locationFixtures[1],1280);
 assert.equal(await page.locator('iframe').count(),0,'missing coordinates omit map');
 assert.equal(await page.locator('.property-location__grid--paired').count(),0,'POI-only layout uses full width');
 console.log('Responsive layout passed: 127 optional combinations; 6 widths, LTR/RTL, light/dark, actual location output, 40/60 split, map ratios, POI rows, FAQ and overflow.');
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
