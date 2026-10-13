"""Classification-v2 evidence contract, mirrored by nearby-amenities-transport.php."""
import datetime as dt
import re
from record_text import database_json_bytes, fail

GROUPS = ('metro', 'tram', 'funicular', 'marmaray', 'metrobus', 'rail', 'bus', 'ferry')
KEYS = set('type railway public_transport highway amenity station subway tram funicular train bus network network:short operator operator:short brand ref route metrobus busway railway:traffic_mode status state proposed planned construction disused abandoned demolished razed removed operational opening_date'.split())
LIFECYCLE = r'(?:proposed|planned|construction|disused|abandoned|demolished|razed|removed)'
ROLES = ('', 'stop', 'stop_entry_only', 'stop_exit_only', 'platform', 'platform_entry_only', 'platform_exit_only')


def tags_of(tags):
    return {k: v for k, v in tags.items() if isinstance(v, str) and
            (k in KEYS or re.fullmatch(LIFECYCLE+r':[a-z_]+', k) or re.fullmatch(r'(?:railway|public_transport|highway):'+LIFECYCLE, k))}


def operational(tags):
    for k, v in tags.items():
        if (re.match('^'+LIFECYCLE+r'(?::|$)', k) or re.fullmatch(r'(?:railway|public_transport|highway):'+LIFECYCLE, k)) and v.strip(' \t\n\r\f\v').lower() not in ('', 'no', 'false', '0'):
            return False
        if k in ('railway', 'public_transport', 'highway', 'station', 'route', 'status', 'state') and re.fullmatch(
                LIFECYCLE+r'|under_construction', v, re.I):
            return False
    if tags.get('operational') == 'no':
        return False
    date = tags.get('opening_date', '')
    if re.fullmatch(r'\d{4}(?:-\d{2}(?:-\d{2})?)?', date):
        date += '-01-01' if len(date) == 4 else '-01' if len(date) == 7 else ''
        if date > dt.datetime.now(dt.timezone.utc).date().isoformat():
            return False
    return True


def system(tags, name):
    pattern = r'(?:[İIıi]ETT[ :_-]*)?(?:metrobüs|metrobus)' if name == 'metrobus' else r'(?:TCDD[ :_-]*)?marmaray'
    return any(re.fullmatch(pattern, tags.get(k, '').strip(' \t\n\r\f\v'), re.I) for k in
               ('network', 'network:short', 'operator', 'operator:short', 'brand'))


def group(tags, routes=()):
    if not operational(tags): return None
    rail = tags.get('railway') in ('station', 'halt', 'tram_stop', 'funicular') or tags.get('public_transport') in ('station', 'platform', 'stop_position')
    bus = tags.get('highway') == 'bus_stop' or tags.get('bus') == 'yes'
    modes = []; metrobus = system(tags, 'metrobus') or tags.get('metrobus') == 'yes'; marmaray = system(tags, 'marmaray')
    for route in routes:
        rt = route['tags']; mode = rt.get('route')
        if route['role'] in ROLES and rt.get('type') == 'route' and operational(rt):
            modes.append(mode)
            metrobus = metrobus or (mode == 'bus' and system(rt, 'metrobus'))
            marmaray = marmaray or (mode == 'train' and system(rt, 'marmaray'))
    if (rail or bus) and metrobus: return 'metrobus'
    if rail and (tags.get('station') == 'funicular' or tags.get('railway') == 'funicular' or tags.get('funicular') == 'yes' or 'funicular' in modes): return 'funicular'
    if rail and (tags.get('railway') == 'tram_stop' or tags.get('tram') == 'yes' or 'tram' in modes): return 'tram'
    if rail and marmaray: return 'marmaray'
    if rail and (tags.get('station') == 'subway' or tags.get('subway') == 'yes' or 'subway' in modes): return 'metro'
    if rail and (tags.get('railway') in ('station', 'halt') or tags.get('train') == 'yes' or 'train' in modes): return 'rail'
    if bus: return 'bus'
    if tags.get('amenity') == 'ferry_terminal': return 'ferry'
    return None


def refs(ref, category):
    prefix = dict(metro='M', tram='T', funicular='F').get(category)
    if not prefix or not isinstance(ref, str): return []
    return sorted({part.strip(' \t\n\r\f\v').upper() for part in re.split('[;,]', ref)
                   if re.fullmatch(prefix+r'[1-9][0-9]?[AB]?', part.strip(' \t\n\r\f\v').upper())})


def lines(evidence, category):
    mode = dict(metro='subway', tram='tram', funicular='funicular').get(category)
    result = set()
    if not mode: return []
    for source in evidence['sources']:
        tags = source['tags']
        if group(tags) == category: result.update(refs(tags.get('ref'), category))
        for route in source['routes']:
            if route['role'] in ROLES and route['tags'].get('type') == 'route' and operational(route['tags']) and route['tags'].get('route') == mode:
                result.update(refs(route['tags'].get('ref'), category))
    return sorted(result)


def validate_transport(place, schema=2):
    category = place.get('group'); evidence = place.get('transport'); identity = place.get('id')
    def reject(reason): fail(identity, 'transport', reason)
    if schema == 1:
        if 'transport' in place or category in ('tram', 'funicular'): reject('Schema-v1 record has v2 transport fields.')
        return
    if category not in GROUPS:
        if evidence is not None: reject('Non-transport record contains transport metadata.')
        return
    if not isinstance(evidence, dict) or set(evidence) != {'sources', 'lines'} or not isinstance(evidence['sources'], list) or not isinstance(evidence['lines'], list) or len(evidence['lines']) > 16 or not 1 <= len(evidence['sources']) <= 16:
        reject('Invalid/oversized transport metadata.')
    for source in evidence['sources']:
        if not isinstance(source, dict) or set(source) != {'id', 'tags', 'routes'} or source['id'] not in [identity]+place.get('aliases', []) or not isinstance(source['tags'], dict) or not isinstance(source['routes'], list) or len(source['routes']) > 32:
            reject('Invalid transport source/membership.')
        for item in [source]+source['routes']:
            tags = item.get('tags') if isinstance(item, dict) else None
            if not isinstance(tags, dict) or len(tags) > 40 or tags_of(tags) != tags:
                reject('Invalid transport evidence tags.')
            try:
                if any(len(v.encode('utf-8')) > 512 for v in tags.values()): reject('Oversized/invalid transport evidence value.')
            except UnicodeEncodeError: reject('Oversized/invalid transport evidence value.')
        for route in source['routes']:
            if set(route) != {'id', 'role', 'tags'} or not isinstance(route['id'], str) or not re.fullmatch(r'relation/[1-9][0-9]{0,18}', route['id']) or not isinstance(route['role'], str) or len(route['role'].encode()) > 32:
                reject('Invalid direct route identity/role.')
        if group(source['tags'], source['routes']) != category: reject('Transport group lacks operational evidence.')
    if database_json_bytes(evidence) > 32768: reject('Invalid/oversized transport metadata.')
    if lines(evidence, category) != evidence['lines']: reject('Line identifiers differ from verified evidence.')
