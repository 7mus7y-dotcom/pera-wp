<?php

if (!defined('ABSPATH')) {
    exit;
}

class PeraPortalDiagnosticsService
{
    const RULE_DUPLICATE_UNIT_CODE_ON_FLOOR = 'DUPLICATE_UNIT_CODE_ON_FLOOR';
    const RULE_UNIT_CODE_MISSING_IN_SVG = 'UNIT_CODE_MISSING_IN_SVG';
    const RULE_REQUIRED_FIELDS_MISSING = 'REQUIRED_FIELDS_MISSING';
    const RULE_INVALID_STATUS_VALUE = 'INVALID_STATUS_VALUE';
    const RULE_INVALID_OR_MISSING_CURRENCY = 'INVALID_OR_MISSING_CURRENCY';
    const RULE_FLOOR_MISSING_SVG_UPLOAD = 'FLOOR_MISSING_SVG_UPLOAD';
    const RULE_SVG_ID_WITHOUT_UNIT_RECORD = 'SVG_ID_WITHOUT_UNIT_RECORD';
    const RULE_MISSING_UNIT_PLAN = 'MISSING_UNIT_PLAN';
    const RULE_MISSING_PRICE = 'MISSING_PRICE';

    public static function runForFloor($floor_id)
    {
        $floor_id = absint($floor_id);

        $floor = $floor_id > 0 ? get_post($floor_id) : null;
        if (!($floor instanceof WP_Post) || $floor->post_type !== 'pera_floor') {
            return new WP_Error('pera_portal_diag_invalid_floor', __('Invalid floor selected for diagnostics.', 'pera-portal'));
        }

        $building_id = self::resolveFloorBuildingId($floor_id);
        $building = $building_id > 0 ? get_post($building_id) : null;

        $svg_asset = self::getFloorSvgAsset($floor_id);
        $svg_ids = [];

        if (!empty($svg_asset['svg_markup'])) {
            $svg_ids = PeraPortalDiagnosticsSvgParser::extractIds($svg_asset['svg_markup']);
        }

        $units = self::getFloorUnits($floor_id);

        $issues = [];
        $errors = 0;
        $warnings = 0;
        $infos = 0;

        if (!$svg_asset['has_svg']) {
            self::addIssue($issues, $errors, $warnings, $infos, [
                'rule' => self::RULE_FLOOR_MISSING_SVG_UPLOAD,
                'severity' => 'error',
                'message' => __('Floor has no uploaded SVG.', 'pera-portal'),
                'unit_id' => 0,
                'unit_code' => '',
                'svg_id' => '',
                'recommended_action' => __('Upload floor SVG to restore reliable mapping.', 'pera-portal'),
            ]);
        }

        $unit_code_to_units = [];
        $valid_unit_codes = [];
        $matched_units = 0;

        foreach ($units as $unit) {
            $unit_id = (int) $unit['id'];
            $unit_code = (string) $unit['unit_code'];
            $status = (string) $unit['status'];
            $currency = (string) $unit['currency'];

            if ($unit_code !== '') {
                if (!isset($unit_code_to_units[$unit_code])) {
                    $unit_code_to_units[$unit_code] = [];
                }

                $unit_code_to_units[$unit_code][] = $unit;
                $valid_unit_codes[$unit_code] = $unit_code;
            }

            $required_missing = [];
            if ($unit_code === '') {
                $required_missing[] = 'unit_code';
            }
            if ((int) $unit['floor'] <= 0) {
                $required_missing[] = 'floor';
            }
            if ($status === '') {
                $required_missing[] = 'status';
            }
            if ($currency === '') {
                $required_missing[] = 'currency';
            }

            if (!empty($required_missing)) {
                self::addIssue($issues, $errors, $warnings, $infos, [
                    'rule' => self::RULE_REQUIRED_FIELDS_MISSING,
                    'severity' => 'error',
                    'message' => sprintf(
                        /* translators: %s: comma-separated field names */
                        __('Unit is missing required fields: %s.', 'pera-portal'),
                        implode(', ', $required_missing)
                    ),
                    'unit_id' => $unit_id,
                    'unit_code' => $unit_code,
                    'svg_id' => '',
                    'recommended_action' => __('Complete all required fields before publishing/using this unit.', 'pera-portal'),
                ]);
            }

            if ($status !== '' && !in_array($status, self::allowedStatuses(), true)) {
                self::addIssue($issues, $errors, $warnings, $infos, [
                    'rule' => self::RULE_INVALID_STATUS_VALUE,
                    'severity' => 'error',
                    'message' => sprintf(
                        /* translators: %s: invalid status */
                        __('Invalid status value: %s.', 'pera-portal'),
                        $status
                    ),
                    'unit_id' => $unit_id,
                    'unit_code' => $unit_code,
                    'svg_id' => '',
                    'recommended_action' => __('Set status to one of: available, reserved, sold.', 'pera-portal'),
                ]);
            }

            if ($currency === '' || !in_array($currency, self::allowedCurrencies(), true)) {
                self::addIssue($issues, $errors, $warnings, $infos, [
                    'rule' => self::RULE_INVALID_OR_MISSING_CURRENCY,
                    'severity' => 'error',
                    'message' => $currency === ''
                        ? __('Currency is missing.', 'pera-portal')
                        : sprintf(
                            /* translators: %s: invalid currency */
                            __('Invalid currency value: %s.', 'pera-portal'),
                            $currency
                        ),
                    'unit_id' => $unit_id,
                    'unit_code' => $unit_code,
                    'svg_id' => '',
                    'recommended_action' => __('Set currency to one of: EUR, GBP, USD, TRY (uppercase).', 'pera-portal'),
                ]);
            }

            if ((int) $unit['plan_attachment_id'] <= 0) {
                self::addIssue($issues, $errors, $warnings, $infos, [
                    'rule' => self::RULE_MISSING_UNIT_PLAN,
                    'severity' => 'warning',
                    'message' => __('Unit detail plan is missing.', 'pera-portal'),
                    'unit_id' => $unit_id,
                    'unit_code' => $unit_code,
                    'svg_id' => '',
                    'recommended_action' => __('Upload a unit detail plan attachment.', 'pera-portal'),
                ]);
            }

            if (!is_numeric($unit['price']) || (string) $unit['price'] === '') {
                self::addIssue($issues, $errors, $warnings, $infos, [
                    'rule' => self::RULE_MISSING_PRICE,
                    'severity' => 'warning',
                    'message' => __('Unit price is missing or invalid.', 'pera-portal'),
                    'unit_id' => $unit_id,
                    'unit_code' => $unit_code,
                    'svg_id' => '',
                    'recommended_action' => __('Enter a valid numeric price.', 'pera-portal'),
                ]);
            }

            if ($unit_code !== '' && in_array($unit_code, $svg_ids, true)) {
                $matched_units++;
            }
        }

        foreach ($unit_code_to_units as $unit_code => $duplicate_units) {
            if (count($duplicate_units) <= 1) {
                continue;
            }

            foreach ($duplicate_units as $duplicate_unit) {
                self::addIssue($issues, $errors, $warnings, $infos, [
                    'rule' => self::RULE_DUPLICATE_UNIT_CODE_ON_FLOOR,
                    'severity' => 'error',
                    'message' => sprintf(
                        /* translators: %s: duplicated unit code */
                        __('Duplicate unit_code found on this floor: %s.', 'pera-portal'),
                        $unit_code
                    ),
                    'unit_id' => (int) $duplicate_unit['id'],
                    'unit_code' => $unit_code,
                    'svg_id' => '',
                    'recommended_action' => __('Rename duplicate code or merge duplicate unit records.', 'pera-portal'),
                ]);
            }
        }

        if (!empty($svg_ids)) {
            foreach ($units as $unit) {
                $unit_code = (string) $unit['unit_code'];
                if ($unit_code === '') {
                    continue;
                }

                if (!in_array($unit_code, $svg_ids, true)) {
                    self::addIssue($issues, $errors, $warnings, $infos, [
                        'rule' => self::RULE_UNIT_CODE_MISSING_IN_SVG,
                        'severity' => 'error',
                        'message' => sprintf(
                            /* translators: %s: unit code */
                            __('No matching SVG element id found for unit_code: %s.', 'pera-portal'),
                            $unit_code
                        ),
                        'unit_id' => (int) $unit['id'],
                        'unit_code' => $unit_code,
                        'svg_id' => '',
                        'recommended_action' => __('Fix unit_code or update floor SVG IDs to match.', 'pera-portal'),
                    ]);
                }
            }

            foreach ($svg_ids as $svg_id) {
                if (!isset($valid_unit_codes[$svg_id])) {
                    self::addIssue($issues, $errors, $warnings, $infos, [
                        'rule' => self::RULE_SVG_ID_WITHOUT_UNIT_RECORD,
                        'severity' => 'warning',
                        'message' => sprintf(
                            /* translators: %s: svg id */
                            __('SVG ID has no matching unit record: %s.', 'pera-portal'),
                            $svg_id
                        ),
                        'unit_id' => 0,
                        'unit_code' => '',
                        'svg_id' => $svg_id,
                        'recommended_action' => __('Create a matching unit record or remove unused SVG ID.', 'pera-portal'),
                    ]);
                }
            }
        }

        $issues_by_rule = [];
        foreach ($issues as $issue) {
            $rule = (string) $issue['rule'];
            if (!isset($issues_by_rule[$rule])) {
                $issues_by_rule[$rule] = [];
            }

            $issues_by_rule[$rule][] = $issue;
        }

        return [
            'context' => [
                'floor_id' => $floor_id,
                'floor_title' => sanitize_text_field((string) get_the_title($floor_id)),
                'building_id' => $building_id,
                'building_title' => $building instanceof WP_Post
                    ? sanitize_text_field((string) get_the_title($building_id))
                    : '',
            ],
            'svg' => [
                'has_svg' => (bool) $svg_asset['has_svg'],
                'svg_source' => (string) $svg_asset['svg_source'],
                'svg_attachment_id' => (int) $svg_asset['svg_attachment_id'],
            ],
            'summary' => [
                'total_units' => count($units),
                'total_svg_ids' => count($svg_ids),
                'matched_units' => $matched_units,
                'error_count' => $errors,
                'warning_count' => $warnings,
                'info_count' => $infos,
            ],
            'issues_by_rule' => $issues_by_rule,
            'issues' => $issues,
        ];
    }

    public static function allowedStatuses()
    {
        return ['available', 'reserved', 'sold'];
    }

    public static function allowedCurrencies()
    {
        return ['EUR', 'GBP', 'USD', 'TRY'];
    }

    private static function getFloorUnits($floor_id)
    {
        $query = new WP_Query([
            'post_type' => 'pera_unit',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => 'floor',
                    'value' => (string) absint($floor_id),
                    'compare' => '=',
                ],
            ],
        ]);

        $rows = [];

        foreach ($query->posts as $unit_post) {
            $unit_id = (int) $unit_post->ID;
            $plan = self::getField('unit_detail_plan', $unit_id);

            $plan_attachment_id = 0;
            if (is_array($plan) && !empty($plan['ID'])) {
                $plan_attachment_id = absint($plan['ID']);
            } elseif (is_numeric($plan)) {
                $plan_attachment_id = absint($plan);
            }

            $rows[] = [
                'id' => $unit_id,
                'unit_code' => sanitize_text_field((string) self::getField('unit_code', $unit_id)),
                'floor' => absint(self::getField('floor', $unit_id)),
                'status' => sanitize_key((string) self::getField('status', $unit_id)),
                'currency' => sanitize_text_field((string) self::getField('currency', $unit_id)),
                'price' => self::getField('price', $unit_id),
                'plan_attachment_id' => $plan_attachment_id,
            ];
        }

        return $rows;
    }

    private static function resolveFloorBuildingId($floor_id)
    {
        $building = self::getField('building', $floor_id);

        if (is_array($building) && isset($building['ID'])) {
            return absint($building['ID']);
        }

        if (is_object($building) && isset($building->ID)) {
            return absint($building->ID);
        }

        return absint($building);
    }

    private static function getFloorSvgAsset($floor_id)
    {
        $file = self::getField('floor_svg', $floor_id);

        $attachment_id = 0;
        $path = '';
        $svg_markup = '';

        if (is_array($file) && !empty($file['ID'])) {
            $attachment_id = absint($file['ID']);
        } elseif (is_numeric($file)) {
            $attachment_id = absint($file);
        }

        if ($attachment_id > 0) {
            $path = (string) get_attached_file($attachment_id);
            if ($path !== '' && is_readable($path)) {
                $svg_markup = (string) file_get_contents($path);
            }
        }

        return [
            'has_svg' => $attachment_id > 0 && $svg_markup !== '',
            'svg_source' => $attachment_id > 0 ? 'upload' : 'missing',
            'svg_attachment_id' => $attachment_id,
            'svg_markup' => $svg_markup,
        ];
    }

    private static function getField($field_name, $post_id)
    {
        if (function_exists('get_field')) {
            return get_field($field_name, $post_id);
        }

        return get_post_meta($post_id, $field_name, true);
    }

    private static function addIssue(array &$issues, &$errors, &$warnings, &$infos, array $issue)
    {
        $issues[] = [
            'rule' => isset($issue['rule']) ? (string) $issue['rule'] : '',
            'severity' => isset($issue['severity']) ? (string) $issue['severity'] : 'info',
            'message' => isset($issue['message']) ? (string) $issue['message'] : '',
            'unit_id' => isset($issue['unit_id']) ? absint($issue['unit_id']) : 0,
            'unit_code' => isset($issue['unit_code']) ? sanitize_text_field((string) $issue['unit_code']) : '',
            'svg_id' => isset($issue['svg_id']) ? sanitize_text_field((string) $issue['svg_id']) : '',
            'recommended_action' => isset($issue['recommended_action']) ? (string) $issue['recommended_action'] : '',
        ];

        if ($issue['severity'] === 'error') {
            $errors++;
            return;
        }

        if ($issue['severity'] === 'warning') {
            $warnings++;
            return;
        }

        $infos++;
    }
}
