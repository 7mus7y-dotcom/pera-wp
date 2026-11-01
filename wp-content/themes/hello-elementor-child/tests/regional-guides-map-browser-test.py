"""Exercise real Leaflet offline with neutral synthetic tiles (no OSM requests)."""
import json
from pathlib import Path
import subprocess
import tempfile
import shutil

chrome = shutil.which('google-chrome') or shutil.which('chromium')
if not chrome:
    raise SystemExit('Chromium or Google Chrome required')

root = Path(__file__).resolve().parents[1]
script = (root / 'js/regional-guides-map.js').read_text()
leaflet = (root / 'vendor/leaflet/leaflet.js').read_text()
css = (root / 'vendor/leaflet/leaflet.css').read_text()
data = dict(guides=[dict(lat=41.05, lng=29.05, title='<img src=x onerror=alert(1)> Guide', label='', url='https://example.test/ar/guide/'), dict(lat=41.05, lng=29.05, title='Second guide', label='', url='https://example.test/ar/second/')], landmarks=[dict(lat=41.02566,lng=28.97416,title='Galata Tower'),dict(lat=41.01152,lng=28.98338,title='Topkapı Palace'),dict(lat=41.00546,lng=28.97682,title='Sultanahmet Mosque')],read='Read guide')
fixture = '''<meta charset="utf-8"><style>CSS\n.regional-map__canvas{height:480px;width:100%}</style>
<section data-regional-map><div data-map-canvas class="regional-map__canvas"></div><p data-map-status>Fallback</p><a href="https://example.test/ar/guide/">Guide</a></section>
<script>LEAFLET</script><script>
const root=document.querySelector('section');root.dataset.mapConfig=JSON.stringify(DATA);
const maps=[]; const originalMap=L.map; L.map=(...args)=>{const map=originalMap(...args);maps.push(map);return map;};
const Grid=L.GridLayer.extend({createTile(){const tile=document.createElement('div');tile.textContent='Offline test tile';return tile;}});L.tileLayer=()=>new Grid();
window.matchMedia=()=>({matches:true});
</script><script>SCRIPT</script><script>
function check(ok,label){if(!ok)throw Error(label);}
try {
const map=maps[0];check(!!map,'map created');check(!map.scrollWheelZoom.enabled(),'wheel disabled');check(map.touchZoom.enabled(),'two-finger gestures immediately enabled');
const markers=Array.from(document.querySelectorAll('.regional-map__marker--guide'));check(markers.length===1,'nearby guides grouped');check(document.querySelectorAll('.regional-map__marker--landmark').length===3,'distinct landmarks');
check(markers[0].getAttribute('tabindex')==='0','keyboard marker');markers[0].click();
check(document.querySelectorAll('.regional-map__popup a').length===2,'both guide links in popup');check(!document.querySelector('.regional-map__popup img'),'title treated as text');
check(document.querySelector('.regional-map__popup a').href==='https://example.test/ar/guide/','translated link retained');
check(!root.querySelector('button'),'no activation button');
const canvas=root.querySelector('[data-map-canvas]');
canvas.dispatchEvent(new PointerEvent('pointerdown',{pointerType:'touch',bubbles:true}));
check(!map.dragging.enabled(),'touch drag disabled');
canvas.dispatchEvent(new PointerEvent('pointerdown',{pointerType:'mouse',bubbles:true}));
check(map.dragging.enabled(),'hybrid mouse drag restored');
check(!root.querySelector('[data-map-status]').hidden===false,'fallback hidden on success');
map.fire('tileerror');check(!root.querySelector('[data-map-status]').hidden,'tile failure fallback');
map.setZoom(17);check(document.querySelectorAll('.regional-map__marker--guide').length===1,'identical locations remain grouped');
document.body.dataset.result='PASS';
} catch(error){document.body.dataset.result='FAIL';document.body.append(error.message);}
</script>'''
fixture=fixture.replace('CSS',css).replace('LEAFLET',leaflet).replace('DATA',json.dumps(data)).replace('SCRIPT',script)
with tempfile.TemporaryDirectory() as temp:
    page=Path(temp)/'map.html';page.write_text(fixture)
    result=subprocess.run([chrome,'--headless','--disable-crash-reporter','--disable-breakpad','--disable-background-networking','--disable-extensions','--no-first-run','--timeout=10000','--virtual-time-budget=2000','--user-data-dir='+str(Path(temp)/'profile'),'--no-sandbox','--disable-dev-shm-usage','--disable-gpu','--no-proxy-server','--dump-dom',page.as_uri()],capture_output=True,text=True,timeout=30)
    if 'data-result="PASS"' not in result.stdout:
        raise SystemExit('Browser regression failed: '+result.stdout[-2000:]+'\n'+result.stderr[-1000:])
    # Missing Leaflet must preserve the HTML fallback and link without throwing.
    page.write_text('<p data-map-status>Fallback</p><a href="/guide/">Guide</a><script>'+script+'</script><script>document.body.dataset.result="PASS"</script>')
    result=subprocess.run([chrome,'--headless','--disable-crash-reporter','--disable-breakpad','--disable-background-networking','--disable-extensions','--no-first-run','--timeout=10000','--virtual-time-budget=2000','--user-data-dir='+str(Path(temp)/'profile'),'--no-sandbox','--disable-dev-shm-usage','--disable-gpu','--dump-dom',page.as_uri()],capture_output=True,text=True,timeout=30)
    assert 'data-result="PASS"' in result.stdout and 'Fallback' in result.stdout and 'href="/guide/"' in result.stdout
print('Regional map browser regressions passed (real Leaflet, offline synthetic tiles; asset failure fallback)')
