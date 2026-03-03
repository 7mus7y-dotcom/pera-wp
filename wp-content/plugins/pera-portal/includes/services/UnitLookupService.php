<?php

if (!defined('ABSPATH')) {
    exit;
}

class PeraPortalUnitLookupService
{
    public static function getUnitById($unit_id)
    {
        return [
            'id' => absint($unit_id),
            'label' => '',
        ];
    }
}
