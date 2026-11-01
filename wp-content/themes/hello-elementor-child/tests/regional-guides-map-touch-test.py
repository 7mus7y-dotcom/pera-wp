"""Real Chromium touch input, offline Leaflet; run with Python Playwright installed."""
import json
import shutil
from pathlib import Path
from playwright.sync_api import sync_playwright

root = Path(__file__).resolve().parents[1]
config = dict(guides=[dict(lat=41.08, lng=28.975, title='Guide', url='https://example.test/guide/')], landmarks=[], read='Read guide')
html = '<meta name="viewport" content="width=device-width,initial-scale=1"><style>' + (root/'vendor/leaflet/leaflet.css').read_text() + (root/'css/main.css').read_text() + '</style><div style="height:250px"></div><section class="regional-map" data-regional-map><div data-map-canvas class="regional-map__canvas"></div><p data-map-status>Fallback</p></section><div style="height:2000px"></div><details data-regional-map-picker><summary>Picker</summary><div data-map-canvas style="height:240px"></div></details><input id="pera-guide-map-lat"><input id="pera-guide-map-lng">'
html += '<script>' + (root/'vendor/leaflet/leaflet.js').read_text() + '</script><script>document.querySelector("section").dataset.mapConfig=' + json.dumps(json.dumps(config)) + ';window.maps=[];const original=L.map;L.map=(...args)=>{const m=original(...args);maps.push(m);return m;};const Grid=L.GridLayer.extend({createTile(){return document.createElement("div");}});L.tileLayer=()=>new Grid();</script><script>' + (root/'js/regional-guides-map.js').read_text() + '</script>'
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=shutil.which('chromium') or shutil.which('google-chrome'), args=['--no-sandbox'])
    page = browser.new_page(viewport=dict(width=390, height=844), is_mobile=True, has_touch=True)
    page.route('**/*', lambda route: route.abort())
    page.set_content(html)
    cdp = page.context.new_cdp_session(page)
    def touch(kind, points):
        cdp.send('Input.dispatchTouchEvent', dict(type=kind, touchPoints=[dict(x=x, y=y, id=i) for i, (x,y) in enumerate(points)]))
        page.wait_for_timeout(40)
    def gesture(start, end):
        touch('touchStart', start)
        for step in range(1, 9):
            touch('touchMove', [(x+(end[i][0]-x)*step/8, y+(end[i][1]-y)*step/8) for i,(x,y) in enumerate(start)])
        touch('touchEnd', [])
        page.wait_for_timeout(350)
    def center(): return page.evaluate('maps[0].getCenter()')
    assert page.evaluate('maps[0].touchZoom.enabled()')
    assert page.locator('.regional-map__toggle').count() == 0
    before = center()
    gesture([(180,480)], [(180,330)])
    assert page.evaluate('scrollY') > 50, 'one finger scrolls the webpage'
    assert center() == before, 'one finger cannot move the map'
    page.evaluate('scrollTo(0,0)')
    page.wait_for_timeout(200)
    before = center()
    gesture([(110,400),(230,400)], [(140,440),(260,440)])
    assert center() != before, 'equal-distance two-finger movement pans immediately'
    assert page.evaluate('scrollY') == 0, 'two-finger panning does not scroll page'
    zoom = page.evaluate('maps[0].getZoom()')
    gesture([(145,410),(205,410)], [(80,410),(270,410)])
    assert page.evaluate('maps[0].getZoom()') > zoom, 'pinch zoom works immediately'
    # A new one-finger gesture after a pinch must return to page scrolling.
    before = center()
    gesture([(180,480)], [(180,330)])
    assert center() == before
    assert page.evaluate('scrollY') > 50
    page.evaluate('scrollTo(0,0); maps[0].setView([41.08,28.975],12,{animate:false})')
    page.locator('.regional-map__marker--guide').tap()
    link = page.locator('.regional-map__popup a')
    assert link.get_attribute('href') == 'https://example.test/guide/'
    page.evaluate('window.followed=false;document.querySelector(".regional-map__popup a").addEventListener("click",e=>{e.preventDefault();window.followed=true})')
    link.tap()
    assert page.evaluate('followed'), 'popup links receive taps'
    # Picker is independent: default Drag and TouchZoom, click coordinates, input sync.
    page.evaluate('document.querySelector("details").open=true')
    page.wait_for_timeout(100)
    assert page.evaluate('maps[1].dragging.enabled() && maps[1].touchZoom.enabled()')
    page.evaluate('maps[1].fire("click",{latlng:L.latLng(41.1,29.2)})')
    assert page.locator('#pera-guide-map-lat').input_value() == '41.100000'
    assert page.locator('#pera-guide-map-lng').input_value() == '29.200000'
    # Desktop mouse drag still pans after touch use on the same map.
    page.evaluate('scrollTo(0,0)')
    before = center()
    page.mouse.move(160,400); page.mouse.down(); page.mouse.move(210,440,steps=8); page.mouse.up()
    page.wait_for_timeout(400)
    assert center() != before, 'hybrid mouse dragging remains available'
    browser.close()
print('Real touch scroll, pan, pinch, taps, hybrid mouse and admin picker passed')
