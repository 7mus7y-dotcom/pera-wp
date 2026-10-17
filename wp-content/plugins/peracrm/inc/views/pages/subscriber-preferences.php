<?php
if (!defined('ABSPATH')) { exit; }
$stylesheet = peracrm_frontend_get_asset_file('assets/frontend/crm.css');
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><title>Email preferences</title><link rel="stylesheet" href="<?php echo esc_url(add_query_arg('ver', (string) filemtime($stylesheet['path']), $stylesheet['url'])); ?>"></head>
<body class="crm-route"><main id="primary" class="crm-page peracrm-preferences"><h1>Email preferences</h1>
<?php if ($message !== '') : ?><p role="status"><?php echo esc_html($message); ?></p><?php endif; ?>
<?php if ($valid) : ?>
<p>Choose whether to receive marketing email. Opening this page makes no changes.</p>
<form method="post" action="<?php echo esc_url(peracrm_subscribers_preferences_url()); ?>" class="peracrm-subscriber-form">
<input type="hidden" name="token" value="<?php echo esc_attr($token); ?>"><input type="hidden" name="preference_action" value="withdraw">
<button class="btn" type="submit">Unsubscribe from all marketing email</button></form>
<form method="post" action="<?php echo esc_url(peracrm_subscribers_preferences_url()); ?>" class="peracrm-subscriber-form">
<input type="hidden" name="token" value="<?php echo esc_attr($token); ?>"><input type="hidden" name="preference_action" value="preferences">
<fieldset class="peracrm-topic-choices"><legend>Email topics</legend>
<?php $selected_topics = peracrm_subscribers_public_topics($token); foreach (['news' => 'News', 'properties' => 'Property updates'] as $key => $label) : ?>
<label><input type="checkbox" name="topics[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $selected_topics, true)); ?>><span><?php echo esc_html($label); ?></span></label>
<?php endforeach; ?></fieldset>
<p>Clear all choices to remove topic preferences. Saving topics does not subscribe you or undo a global unsubscribe.</p><button class="btn btn--ghost" type="submit">Save topic choices</button></form>
<?php else : ?><p>This link is unavailable. Please use the original preferences link.</p><?php endif; ?>
</main></body></html>
