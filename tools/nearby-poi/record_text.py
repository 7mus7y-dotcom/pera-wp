"""OSM name/alias storage contract, mirrored by pera_nearby_place_text_error()."""
import json
import re
import unicodedata

OSM_ID = re.compile(r'(node|way|relation)/[1-9][0-9]{0,18}')


def valid_name_key(key):
    segments = key.split(':')
    return segments[0] == 'name' and all(segment and all(
        char in '_-' or unicodedata.category(char)[0] in 'LMN' for char in segment)
        for segment in segments[1:])


def fail(identity, key, reason):
    # Values are never logged; JSON escaping prevents key/ID log injection.
    identity = identity if isinstance(identity, str) else '<unknown>'
    key = key if isinstance(key, str) else '<non-string>'
    raise ValueError('OSM '+json.dumps(identity[:80], ensure_ascii=True)+' key '+
                     json.dumps(key[:80], ensure_ascii=True)+': '+reason)


def database_json_bytes(value):
    # Match PHP JSON_UNESCAPED_UNICODE, including slash and line-separator escaping.
    encoded = json.dumps(value, ensure_ascii=False, separators=(',', ':'))
    return len(encoded.replace('/', r'\/').replace('\u2028', r'\u2028').replace('\u2029', r'\u2029').encode('utf-8'))


def validate_text(place):
    identity = place.get('id')
    names = place.get('names')
    if not isinstance(names, dict):
        fail(identity, '<names>', 'names must be an object')
    if not isinstance(names.get('name'), str) or names['name'] == '':
        fail(identity, 'name', 'primary name must be a nonempty string')
    for key, value in names.items():
        if not isinstance(key, str):
            fail(identity, key, 'name key must be a string')
        try:
            key_size = len(key.encode('utf-8'))
        except UnicodeEncodeError:
            fail(identity, key, 'name key must be valid UTF-8')
        if key_size > 255:
            fail(identity, key, 'name key exceeds 255 bytes')
        if not valid_name_key(key):
            fail(identity, key, 'invalid name key; use name or nonempty Unicode letter/mark/number/_/- name: segments')
        if not isinstance(value, str):
            fail(identity, key, 'name value must be a string')
        try:
            size = len(value.encode('utf-8'))
        except UnicodeEncodeError:
            fail(identity, key, 'name value must be valid UTF-8')
        if size > 4096:
            fail(identity, key, f'name value exceeds 4096 UTF-8 bytes ({size} bytes)')
    if database_json_bytes(names) > 65535:
        fail(identity, '<names>', 'encoded names exceed MariaDB TEXT capacity (65535 bytes)')
    aliases = place.get('aliases', [])
    if not isinstance(aliases, list):
        fail(identity, 'aliases', 'aliases must be a list')
    for alias in aliases:
        if not isinstance(alias, str) or not OSM_ID.fullmatch(alias):
            fail(identity, 'aliases', 'invalid alias OSM identity')
    if database_json_bytes(aliases) > 65535:
        fail(identity, 'aliases', 'encoded aliases exceed MariaDB TEXT capacity (65535 bytes)')
