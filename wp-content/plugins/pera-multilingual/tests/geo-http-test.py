"""Inspect final HTTP headers from the real theme + plugin on PHP's HTTP SAPI."""
from pathlib import Path
import socket
import subprocess
import tempfile
import time
import urllib.request
import urllib.error

fixture = Path(__file__).parent / 'fixtures/geo-http.php'
with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0))
    port = sock.getsockname()[1]

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None

opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect())
count = 0
with tempfile.TemporaryFile() as log:
    process = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', str(fixture.resolve())], stdout=log, stderr=log)
    try:
        for attempt in range(50):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.1):
                    break
            except OSError:
                time.sleep(.1)
        def fetch(path):
            global count
            try:
                response = opener.open(f'http://127.0.0.1:{port}{path}', timeout=5)
            except urllib.error.HTTPError as error:
                response = error
            with response:
                body = response.read().decode()
                assert response.code in (200, 302), (path, response.code, body)
                assert 'Fatal error' not in body and 'Warning' not in body, (path, body)
                count += 1
                return response.code, response.headers
        routes = {'/': 300, '/property/': 300, '/property/example/': 600, '/district/istanbul/': 900, '/blog/example/': 1800, '/about/': None, '/search/': None}
        expected = 'private, no-store, no-cache, must-revalidate, max-age=0'
        for route, ttl in routes.items():
            for extra in ('', '&audience=bot', '&country=XX', '&late=1', '&filtered=1', '&loggedin=1'):
                status, headers = fetch(route + '?state=on' + extra)
                assert status == 200, (route, extra, status)
                assert headers.get_all('Cache-Control') == [expected], (route, extra, dict(headers))
                assert headers['Pragma'] == 'no-cache' and '1984' in headers['Expires'], (route, dict(headers))
            for state in ('state=off', 'state=on&ready=no'):
                status, headers = fetch(route + '?' + state)
                if ttl:
                    assert headers['Cache-Control'] == f'public, max-age={ttl}, must-revalidate', (route, dict(headers))
                    assert headers['Expires'] and not headers.get('Pragma'), (route, dict(headers))
                else:
                    assert headers.get('Cache-Control') is None, (route, dict(headers))
            for extra in ('filtered=1', 'loggedin=1'):
                status, headers = fetch(route + '?state=off&' + extra)
                assert headers['Cache-Control'] == expected and headers['Pragma'] == 'no-cache', (route, dict(headers))
        status, headers = fetch('/property/example/?state=on&country=CN&beds=2')
        assert status == 302 and '/zh/property/example/' in headers['Location'] and 'beds=2' in headers['Location'], dict(headers)
        assert headers['Cache-Control'] == expected and '1984' in headers['Expires'], dict(headers)
        status, headers = fetch('/zh/property/example/?state=on&country=CN')
        assert status == 200 and headers['Cache-Control'] == expected, dict(headers)
        status, headers = fetch('/robots.txt?state=on')
        assert status == 200 and headers.get('Cache-Control') is None, dict(headers)
        print(f'Final HTTP header integration: {count} responses passed (actual theme callbacks, enabled/disabled/cache-not-ready, 302 and matching-language 200)')
    finally:
        process.terminate()
        process.wait(timeout=5)
