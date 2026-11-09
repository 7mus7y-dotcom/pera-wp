<?php
/** Additive schema, installed and verified under the canonical schema lock. */
if (!defined('ABSPATH')) { exit; }
function peracrm_appointments_schema_definitions(): array
{
    return [
        'peracrm_appointments' => "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(32) NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            start_at DATETIME NOT NULL,
            end_at DATETIME NOT NULL,
            timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Istanbul',
            advisor_id BIGINT UNSIGNED NOT NULL,
            location_type VARCHAR(24) NOT NULL DEFAULT 'other',
            location_name VARCHAR(255) NOT NULL DEFAULT '',
            address VARCHAR(1000) NOT NULL DEFAULT '',
            map_url VARCHAR(2000) NOT NULL DEFAULT '',
            meeting_url VARCHAR(2000) NOT NULL DEFAULT '',
            hotel_name VARCHAR(255) NOT NULL DEFAULT '',
            attendees BIGINT UNSIGNED NOT NULL DEFAULT 2,
            internal_notes LONGTEXT NOT NULL,
            outcome_notes LONGTEXT NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'confirmed',
            created_by BIGINT UNSIGNED NOT NULL,
            updated_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            version BIGINT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY calendar_range (start_at, end_at),
            KEY client_history (client_id, start_at),
            KEY advisor_schedule (advisor_id, start_at),
            KEY status_range (status, start_at)",
        'peracrm_appointment_participants' => "appointment_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            is_primary TINYINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (appointment_id, user_id),
            KEY user_schedule (user_id, appointment_id)",
        'peracrm_appointment_properties' => "appointment_id BIGINT UNSIGNED NOT NULL,
            property_id BIGINT UNSIGNED NOT NULL,
            position BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY  (appointment_id, property_id),
            UNIQUE KEY tour_order (appointment_id, position),
            KEY property_id (property_id)",
        // A request receipt covers updates and stage-only reuse as well as creates.
        'peracrm_appointment_requests' => "actor_id BIGINT UNSIGNED NOT NULL,
            request_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            payload_hash VARCHAR(64) NOT NULL,
            appointment_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (actor_id, request_key),
            KEY appointment_id (appointment_id)"
    ];
}
/** Verify operational indexes, not just the version marker and primary keys. */
function peracrm_appointments_assert_indexes(): void
{
    global $wpdb;
    $required=[
        'peracrm_appointments'=>['calendar_range'=>['start_at','end_at'],'client_history'=>['client_id','start_at'],'advisor_schedule'=>['advisor_id','start_at'],'status_range'=>['status','start_at']],
        'peracrm_appointment_participants'=>['user_schedule'=>['user_id','appointment_id']],
        'peracrm_appointment_properties'=>['tour_order'=>['appointment_id','position']],
        'peracrm_appointment_requests'=>['appointment_id'=>['appointment_id']],
    ];
    foreach($required as $name=>$indexes){
        $rows=(array)$wpdb->get_results('SHOW INDEX FROM '.peracrm_table($name),ARRAY_A);
        foreach($indexes as $index=>$columns){
            $parts=array_values(array_filter($rows,static function($r)use($index){return $r['Key_name']===$index;}));
            usort($parts,static function($a,$b){return $a['Seq_in_index']<=>$b['Seq_in_index'];});
            if(array_column($parts,'Column_name')!==$columns || array_filter(array_column($parts,'Sub_part'))){throw new RuntimeException('Appointment query indexes are unavailable.');}
        }
    }
}
