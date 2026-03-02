<?php

if (!defined('ABSPATH')) {
    exit;
}

class PeraPortalSvgPlanService
{
    public static function getFloorPlanPath($floor_id)
    {
        $floor_id = absint($floor_id);

        if ($floor_id <= 0) {
            return '';
        }

        return '';
    }
}
