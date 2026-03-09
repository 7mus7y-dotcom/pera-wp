<?php

if (!defined('ABSPATH')) {
    exit;
}

class PeraPortalDiagnosticsSvgParser
{
    public static function extractIds($svg_markup)
    {
        $svg_markup = is_string($svg_markup) ? $svg_markup : '';
        if ($svg_markup === '') {
            return [];
        }

        if (!class_exists('DOMDocument')) {
            return [];
        }

        $previous_state = libxml_use_internal_errors(true);

        $document = new DOMDocument();
        $loaded = $document->loadXML($svg_markup, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        if (!$loaded) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous_state);
            return [];
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//*[@id]');

        $ids = [];

        if ($nodes instanceof DOMNodeList) {
            foreach ($nodes as $node) {
                if (!($node instanceof DOMElement)) {
                    continue;
                }

                $id = sanitize_text_field((string) $node->getAttribute('id'));
                if ($id === '') {
                    continue;
                }

                $ids[$id] = $id;
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous_state);

        return array_values($ids);
    }
}
