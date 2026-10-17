<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @internal Always called in the authoritative target-blog context. */
function peracrm_campaigns_schema_lock_name()
{
    global $wpdb;
    return 'peracrm-schema-' . substr(hash('sha256', (defined('DB_NAME') ? DB_NAME : '') . ':' . $wpdb->options), 0, 48);
}

/** Fresh indexed reads: a losing request must not act on cached option snapshots. */
function peracrm_campaigns_schema_option($name, $default = false)
{
    global $wpdb;
    $value = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name));
    return $value === null ? $default : maybe_unserialize($value);
}

/** @internal Nonblocking, connection-owned lock; unlike a row lock it survives DDL. */
function peracrm_campaigns_schema_acquire_slot()
{
    global $wpdb;
    $name = peracrm_campaigns_schema_lock_name();
    // MySQL named locks are recursive. Refuse re-entry on this connection rather
    // than incrementing a lock counter that a nested request could strand.
    $acquired = $wpdb->get_var($wpdb->prepare(
        'SELECT IF(IS_USED_LOCK(%s) = CONNECTION_ID(), 0, GET_LOCK(%s, 0))', $name, $name
    ));
    if ((string) $acquired !== '1') {
        return null;
    }
    $slot = ['name' => $name, 'token' => wp_generate_uuid4()];
    $claimed = $wpdb->query($wpdb->prepare(
        "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
         SELECT %s, %s, 'no' WHERE IS_USED_LOCK(%s) = CONNECTION_ID()
         ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = 'no'",
        'peracrm_campaigns_schema_owner', $slot['token'], $name
    ));
    if ($claimed === false || !peracrm_campaigns_schema_owns_slot($slot)) {
        // This connection acquired the native lock but could not install a
        // token. RELEASE_LOCK itself cannot release a different connection's lock.
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
        return null;
    }
    return $slot;
}

function peracrm_campaigns_schema_owns_slot($slot)
{
    global $wpdb;
    return (string) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s
         AND IS_USED_LOCK(%s) = CONNECTION_ID()",
        'peracrm_campaigns_schema_owner', $slot['token'], $slot['name']
    )) === '1';
}

/** @internal Fence the write itself, not a check followed by update_option(). */
function peracrm_campaigns_schema_publish($slot, $name, $value)
{
    global $wpdb;
    if (!in_array($name, ['peracrm_schema_version', 'peracrm_campaigns_schema_status'], true)) {
        return false;
    }
    $written = $wpdb->query($wpdb->prepare(
        "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
         SELECT %s, %s, 'no' FROM {$wpdb->options} AS owner
         WHERE owner.option_name = %s AND owner.option_value = %s
         AND IS_USED_LOCK(%s) = CONNECTION_ID()
         ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = 'no'",
        $name, maybe_serialize($value), 'peracrm_campaigns_schema_owner', $slot['token'], $slot['name']
    ));
    if ($written === false) {
        return false;
    }
    wp_cache_delete($name, 'options');
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
    return peracrm_campaigns_schema_owns_slot($slot);
}

/** @internal Stale tokens cannot release a later attempt, even on the same connection. */
function peracrm_campaigns_schema_release_slot($slot)
{
    global $wpdb;
    return (string) $wpdb->get_var($wpdb->prepare(
        "SELECT IF(EXISTS(SELECT 1 FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s)
         AND IS_USED_LOCK(%s) = CONNECTION_ID(), RELEASE_LOCK(%s), 0)",
        'peracrm_campaigns_schema_owner', $slot['token'], $slot['name'], $slot['name']
    )) === '1';
}
