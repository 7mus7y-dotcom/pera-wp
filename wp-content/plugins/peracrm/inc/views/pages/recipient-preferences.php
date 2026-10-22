<?php
if (!defined('ABSPATH')) { exit; }
$stylesheet=peracrm_frontend_get_asset_file('assets/frontend/crm.css');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><title>Email preferences</title><link rel="stylesheet" href="<?php echo esc_url($stylesheet['url']); ?>"></head>
<body class="crm-route"><main id="primary" class="crm-page peracrm-preferences"><h1>Email preferences</h1>
<?php if ($message!=='') : ?><p role="status"><?php echo esc_html($message); ?></p><?php endif; ?>
<?php if ($data!==null) : ?>
<p>Opening this page makes no changes. Saving preferences never subscribes you or removes a global unsubscribe.</p>
<form method="post" action="<?php echo esc_url($url); ?>" class="peracrm-subscriber-form">
<input type="hidden" name="token" value="<?php echo esc_attr($token); ?>"><input type="hidden" name="proof" value="<?php echo esc_attr($proof); ?>">
<?php if ($withdrawalURL!=='') : ?><input type="hidden" name="withdrawal_token" value="<?php echo esc_attr($withdrawal); ?>"><?php endif; ?>
<label>Preferred language tag <input name="language" maxlength="35" value="<?php echo esc_attr($data['language']); ?>" placeholder="en"></label>
<p>Use an explicit language tag such as en or tr. Leave blank to remove the language preference.</p>
<fieldset class="peracrm-topic-choices"><legend>Email topics</legend>
<?php foreach (['news'=>'News','properties'=>'Property updates'] as $key=>$label) : ?>
<label><input type="checkbox" name="topics[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key,$data['topics'],true)); ?>><span><?php echo esc_html($label); ?></span></label>
<?php endforeach; ?></fieldset><p>Clear both choices to remove topic preferences.</p>
<button class="btn" type="submit">Save preferences</button></form>
<?php endif; ?>
<?php if ($withdrawalURL!=='') : ?><p><a href="<?php echo esc_url($withdrawalURL); ?>" rel="noreferrer">Unsubscribe from all marketing email</a></p>
<?php elseif ($data===null && $status!==503) : ?><p>Preference access may have expired. Use the separate unsubscribe link in your original email to withdraw marketing permission.</p><?php endif; ?>
</main></body></html>
