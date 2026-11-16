#!/usr/bin/env python3
"""Export the theme's existing footer symbols as 2x PNGs for email clients.
Requires system librsvg, Cairo and GObject; no external assets or downloads.
"""
import ctypes as C
import ctypes.util
from pathlib import Path
import xml.etree.ElementTree as ET

plugin = Path(__file__).resolve().parents[1]
sprite = plugin.parents[1] / 'themes/hello-elementor-child/logos-icons/icons.svg'
ns = {'s': 'http://www.w3.org/2000/svg'}
root = ET.parse(sprite).getroot()
rsvg = C.CDLL(ctypes.util.find_library('rsvg-2'))
cairo = C.CDLL(ctypes.util.find_library('cairo'))
gobject = C.CDLL(ctypes.util.find_library('gobject-2.0'))
rsvg.rsvg_handle_new_from_data.argtypes = [C.c_char_p, C.c_size_t, C.c_void_p]
rsvg.rsvg_handle_new_from_data.restype = C.c_void_p
rsvg.rsvg_handle_render_cairo.argtypes = [C.c_void_p, C.c_void_p]
rsvg.rsvg_handle_render_cairo.restype = C.c_int
cairo.cairo_image_surface_create.argtypes = [C.c_int, C.c_int, C.c_int]
cairo.cairo_image_surface_create.restype = C.c_void_p
cairo.cairo_create.argtypes = [C.c_void_p]
cairo.cairo_create.restype = C.c_void_p
cairo.cairo_surface_write_to_png.argtypes = [C.c_void_p, C.c_char_p]
cairo.cairo_surface_write_to_png.restype = C.c_int
cairo.cairo_destroy.argtypes = [C.c_void_p]
cairo.cairo_surface_destroy.argtypes = [C.c_void_p]
gobject.g_object_unref.argtypes = [C.c_void_p]
for name in ['whatsapp', 'instagram', 'youtube', 'facebook', 'linkedin']:
    symbol = root.find(f'.//s:symbol[@id="icon-{name}"]', ns)
    if symbol is None:
        raise RuntimeError(f'Missing theme symbol: {name}')
    paths = ''.join(ET.tostring(child, encoding='unicode') for child in symbol)
    svg = (f'<svg xmlns="http://www.w3.org/2000/svg" width="68" height="68" viewBox="0 0 34 34">'
           f'<rect width="34" height="34" fill="#002d72"/>'
           f'<circle cx="17" cy="17" r="16.5" fill="none" stroke="#fff" stroke-width="1"/>'
           f'<svg x="8" y="8" width="18" height="18" viewBox="{symbol.attrib["viewBox"]}" '
           f'color="#fff" fill="#fff" stroke="#fff">{paths}</svg></svg>').encode()
    handle = rsvg.rsvg_handle_new_from_data(svg, len(svg), None)
    if not handle:
        raise RuntimeError(f'Cannot parse theme symbol: {name}')
    surface = cairo.cairo_image_surface_create(0, 68, 68)
    context = cairo.cairo_create(surface)
    try:
        if not rsvg.rsvg_handle_render_cairo(handle, context):
            raise RuntimeError(f'Cannot render theme symbol: {name}')
        target = plugin / f'logos-icons/email-social-{name}.png'
        if cairo.cairo_surface_write_to_png(surface, str(target).encode()):
            raise RuntimeError(f'Cannot save {target}')
        print(target.name)
    finally:
        cairo.cairo_destroy(context)
        cairo.cairo_surface_destroy(surface)
        gobject.g_object_unref(handle)
