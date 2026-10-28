<?php
/** Rendered inside the authorized client page, on the CRM target blog. */
if (!defined('ABSPATH')) { exit; }
if (!peracrm_instruction_can_access($client_id)) { return; }
$rows = peracrm_instructions_list($client_id);
$type = $client_type_value === 'seller' ? 'sale' : ($client_type_value === 'landlord' ? 'rental' : '');
if ($type === '' && !$rows) { return; }
$catalog = peracrm_instruction_fields();
$assets = [];
foreach ($rows as $row) { $assets[(int) $row['property_id']] = $row; }
$render_fields = static function ($group, $fields, $values, $prefix) {
    foreach ($fields as $key => $field) {
        $value = (string) ($values[$key] ?? '');
        $id = $prefix . '-' . $key;
        ?>
        <div class="crm-form-field">
          <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($field[0]); ?></label>
          <?php if (is_array($field[1])) : ?>
            <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($group . '[' . $key . ']'); ?>">
              <option value=""><?php esc_html_e('Not set', 'peracrm'); ?></option>
              <?php foreach ($field[1] as $option) : ?><option value="<?php echo esc_attr($option); ?>" <?php selected($value, $option); ?>><?php echo esc_html(ucwords(str_replace('_', ' ', $option))); ?></option><?php endforeach; ?>
            </select>
          <?php elseif ($field[1] === 'textarea') : ?>
            <textarea id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($group . '[' . $key . ']'); ?>" rows="2" maxlength="5000"><?php echo esc_textarea($value); ?></textarea>
          <?php else : ?>
            <input id="<?php echo esc_attr($id); ?>" type="<?php echo esc_attr($field[1]); ?>" name="<?php echo esc_attr($group . '[' . $key . ']'); ?>" value="<?php echo esc_attr($value); ?>" <?php if ($field[1] === 'number') : ?>min="0" step="any"<?php endif; ?> />
          <?php endif; ?>
        </div>
        <?php
    }
};
$advisors = array_filter(peracrm_get_staff_users(), static function ($user) use ($client_id) {
    return peracrm_user_can_access_client((int) $user->ID, $client_id);
});
$render_form = static function ($row, $form_type) use ($client_id, $catalog, $assets, $render_fields, $advisors) {
    $id = (int) ($row['id'] ?? 0);
    $prefix = 'instruction-' . $form_type . '-' . $id;
    $property = peracrm_json_decode($row['property_details'] ?? '');
    $details = peracrm_json_decode($row['details'] ?? '');
    $basic_property = array_intersect_key($catalog['property'], array_flip(['name', 'district', 'neighbourhood', 'address', 'category', 'bedrooms', 'gross_area', 'net_area', 'occupancy']));
    $basic_common = array_intersect_key($catalog['common'], array_flip(['asking_price', 'currency']));
    $basic_common['asking_price'][0] = $form_type === 'rental' ? __('Monthly asking rent', 'peracrm') : __('Asking price', 'peracrm');
    ?>
    <form method="post" action="<?php echo esc_url(home_url('/wp-admin/admin-post.php')); ?>" class="crm-form-stack">
      <?php wp_nonce_field('peracrm_property_instruction_' . $client_id); ?>
      <input type="hidden" name="action" value="peracrm_property_instruction" />
      <input type="hidden" name="client_id" value="<?php echo esc_attr($client_id); ?>" />
      <input type="hidden" name="instruction_id" value="<?php echo esc_attr($id); ?>" />
      <input type="hidden" name="instruction_type" value="<?php echo esc_attr($form_type); ?>" />
      <input type="hidden" name="property_revision" value="<?php echo esc_attr($row['property_revision'] ?? ''); ?>" />
      <input type="hidden" name="revision" value="<?php echo esc_attr($row['revision'] ?? 0); ?>" />
      <h4><?php esc_html_e('1. Basic details', 'peracrm'); ?></h4>
      <p class="crm-section__description"><?php echo esc_html(ucfirst($form_type)); ?> · <?php esc_html_e('Enter a name or identifier. Other details can be added later.', 'peracrm'); ?></p>
      <?php if (!$id && $assets) : ?>
        <label><?php esc_html_e('Reuse a property belonging to this client', 'peracrm'); ?>
          <select name="property_id"><option value="0"><?php esc_html_e('Create new property', 'peracrm'); ?></option>
            <?php foreach ($assets as $asset_id => $asset) : ?><option value="<?php echo esc_attr($asset_id); ?>"><?php echo esc_html(($asset['identifier'] ?: (peracrm_json_decode($asset['property_details'])['name'] ?? '')) . ' (#' . $asset_id . ')'); ?></option><?php endforeach; ?>
          </select>
        </label>
        <p class="crm-section__description"><?php esc_html_e('When reusing a property, its saved physical details are kept. Edit an existing instruction to update the shared property details.', 'peracrm'); ?></p>
      <?php endif; ?>
      <div class="crm-form-row-2">
        <label><?php esc_html_e('Property identifier / apartment number', 'peracrm'); ?><input name="identifier" maxlength="191" value="<?php echo esc_attr($row['identifier'] ?? ''); ?>" /></label>
        <?php $render_fields('property', $basic_property, $property, $prefix); ?>
        <?php $render_fields('details', $basic_common, $details, $prefix); ?>
        <label><?php esc_html_e('Instruction status', 'peracrm'); ?><select name="status"><?php foreach (peracrm_instruction_statuses($form_type) as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($row['status'] ?? 'draft', $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
        <label><?php esc_html_e('Sahibinden listing URL', 'peracrm'); ?><input type="url" name="sahibinden_url" value="<?php echo esc_attr($row['sahibinden_url'] ?? ''); ?>" /></label>
        <label><?php esc_html_e('Pera Property listing URL', 'peracrm'); ?><input type="url" name="pera_url" value="<?php echo esc_attr($row['pera_url'] ?? ''); ?>" /></label>
      </div>
      <details>
        <summary><?php esc_html_e('2. Additional details', 'peracrm'); ?></summary>
        <p class="crm-section__description"><?php esc_html_e('Physical details are shared with other instructions for this property. Prices and workflow details belong to this instruction.', 'peracrm'); ?></p>
        <div class="crm-form-row-2">
          <?php $render_fields('property', array_diff_key($catalog['property'], $basic_property), $property, $prefix); ?>
          <?php $render_fields('details', array_diff_key($catalog['common'], $basic_common) + $catalog[$form_type], $details, $prefix); ?>
          <label><?php esc_html_e('Assigned advisor', 'peracrm'); ?><select name="advisor_user_id">
            <option value="0"><?php esc_html_e('Use client advisor', 'peracrm'); ?></option>
            <?php foreach ($advisors as $advisor) : ?><option value="<?php echo esc_attr($advisor->ID); ?>" <?php selected((int) ($row['advisor_user_id'] ?? 0), (int) $advisor->ID); ?>><?php echo esc_html($advisor->display_name); ?></option><?php endforeach; ?>
          </select></label>
          <label><?php esc_html_e('Linked public property ID (optional)', 'peracrm'); ?><input type="number" name="public_property_id" min="1" value="<?php echo esc_attr($row['public_property_id'] ?? ''); ?>" /></label>
          <label><?php esc_html_e('Property-specific advisor notes (internal)', 'peracrm'); ?><textarea name="notes" rows="3" maxlength="5000"><?php echo esc_textarea($row['notes'] ?? ''); ?></textarea></label>
        </div>
      </details>
      <label><?php esc_html_e('Reason for backward transition, cancellation, withdrawal, archival or reopening', 'peracrm'); ?><textarea name="transition_reason" rows="2" maxlength="5000"></textarea></label>
      <button class="btn btn--solid" type="submit"><?php esc_html_e('Save property instruction', 'peracrm'); ?></button>
    </form>
    <?php
};
?>
<article id="crm-property-instructions" class="crm-section crm-section--flush">
  <header class="crm-section__header"><div>
    <h3 class="crm-section__title"><?php echo esc_html($type === 'sale' ? __('Properties for sale', 'peracrm') : ($type === 'rental' ? __('Properties for rent', 'peracrm') : __('Property instruction history', 'peracrm'))); ?></h3>
    <p class="crm-section__description"><?php esc_html_e('Private property instructions. Saving does not create or publish a website listing. Historical instructions remain available after client-type changes.', 'peracrm'); ?></p>
  </div></header>
  <div class="crm-section__body">
    <?php if ($type !== '') : ?>
      <details class="crm-instruction-editor"><summary class="btn btn--solid"><?php esc_html_e('+ Add property', 'peracrm'); ?></summary><?php $render_form([], $type); ?></details>
    <?php endif; ?>
    <?php foreach ($rows as $row) :
        $property = peracrm_json_decode($row['property_details']);
        $details = peracrm_json_decode($row['details']);
        $advisor = get_userdata((int) $row['advisor_user_id']);
        $reference = ($property['name'] ?? '') ?: $row['identifier'];
        $summary = [
            'Reference' => $row['identifier'], 'Location' => implode(' / ', array_filter([$property['district'] ?? '', $property['neighbourhood'] ?? ''])),
            'Configuration' => $property['bedrooms'] ?? '', 'Gross area (m²)' => $property['gross_area'] ?? '', 'Net area (m²)' => $property['net_area'] ?? '',
            $row['instruction_type'] === 'rental' ? 'Monthly asking rent' : 'Asking price' => trim(($details['asking_price'] ?? '') . ' ' . ($details['currency'] ?? '')),
            'Occupancy' => $property['occupancy'] ?? '', 'Assigned advisor' => $advisor ? $advisor->display_name : 'Unassigned',
        ];
        if ($row['instruction_type'] === 'rental') {
            foreach (['tenant', 'contract_start', 'contract_expiry', 'tenancy_anniversary', 'contracted_rent', 'deposit', 'deposit_status', 'rent_review', 'next_action'] as $key) {
                $summary[$catalog['rental'][$key][0] ?? ($catalog['common'][$key][0] ?? $key)] = $details[$key] ?? '';
            }
        }
    ?>
      <section class="crm-section crm-instruction-card" aria-labelledby="instruction-title-<?php echo esc_attr($row['id']); ?>">
        <header class="crm-section__header"><div><h4 id="instruction-title-<?php echo esc_attr($row['id']); ?>"><?php echo esc_html($reference); ?></h4>
          <span class="crm-chip crm-chip--status"><?php echo esc_html(ucfirst($row['instruction_type']) . ' · ' . peracrm_instruction_statuses($row['instruction_type'])[$row['status']]); ?></span>
          <?php if ($type !== $row['instruction_type']) : ?><span class="crm-chip crm-chip--neutral"><?php esc_html_e('Historical / other instruction type', 'peracrm'); ?></span><?php endif; ?>
        </div></header>
        <div class="crm-section__body">
          <dl class="crm-form-row-2"><?php foreach ($summary as $label => $value) : if ($value === '') { continue; } ?><div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html($value); ?></dd></div><?php endforeach; ?></dl>
          <div class="crm-instruction-actions">
            <?php foreach (['sahibinden_url' => 'View Sahibinden', 'pera_url' => 'View Pera website'] as $key => $label) : if (empty($row[$key])) { continue; } ?>
              <a class="btn btn--ghost" href="<?php echo esc_url($row[$key], ['http', 'https']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
            <?php if ($row['public_property_id'] && get_post_status((int) $row['public_property_id']) === 'publish') : ?><a class="btn btn--ghost" href="<?php echo esc_url(get_permalink((int) $row['public_property_id'])); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View linked website property', 'peracrm'); ?></a><?php endif; ?>
          </div>
          <details class="crm-instruction-editor"><summary class="btn btn--ghost"><?php esc_html_e('Edit', 'peracrm'); ?></summary><?php $render_form($row, $row['instruction_type']); ?></details>
          <details class="crm-instruction-editor"><summary class="btn btn--ghost"><?php esc_html_e('Create reminder', 'peracrm'); ?></summary>
            <form method="post" action="<?php echo esc_url(home_url('/wp-admin/admin-post.php')); ?>" class="crm-form-stack">
              <?php wp_nonce_field('peracrm_add_reminder', 'peracrm_add_reminder_nonce'); ?>
              <input type="hidden" name="action" value="peracrm_add_reminder" />
              <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr($client_id); ?>" />
              <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url(home_url('/crm/client/' . $client_id . '/')); ?>" />
              <label><?php esc_html_e('Due date & time', 'peracrm'); ?><input type="datetime-local" name="peracrm_due_at" required /></label>
              <label><?php esc_html_e('Reminder note', 'peracrm'); ?><textarea name="peracrm_reminder_note" maxlength="5000" rows="3"><?php echo esc_textarea('[Property ' . $reference . ' / instruction #' . $row['id'] . '] ' . ($details['next_action'] ?? '')); ?></textarea></label>
              <button class="btn btn--solid" type="submit"><?php esc_html_e('Add reminder', 'peracrm'); ?></button>
            </form>
          </details>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
</article>
