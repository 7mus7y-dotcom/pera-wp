# Phone dropdown audit (2026-04-04)

## Commands run
- grep -R "sr_phone_country" wp-content/themes/hello-elementor-child -n
- grep -R "sr_phone_national" wp-content/themes/hello-elementor-child -n
- grep -R "peracrm_phone_dial_code_options" wp-content/themes/hello-elementor-child -n
- grep -R "parts/enquiry-form.php" wp-content/themes/hello-elementor-child -n
- grep -R "get_template_part" wp-content/themes/hello-elementor-child -n | grep enquiry
- grep -R "require_once PERACRM_INC . '/phone-countries.php';" wp-content/plugins/peracrm -n
- grep -R "function peracrm_phone_dial_code_options" wp-content/plugins/peracrm -n

## Findings
1. Theme dropdown markup is centralized in `wp-content/themes/hello-elementor-child/parts/enquiry-form.php` and uses:

```php
$available_phone_countries = function_exists( 'peracrm_phone_dial_code_options' )
  ? (array) peracrm_phone_dial_code_options()
  : array(/* 4 fallback rows */);
```

2. All inspected front-end enquiry templates/routes call that same partial via `get_template_part('parts/enquiry-form', ...)`:
   - `page-rent-with-pera.php`
   - `page-sell-with-pera.php`
   - `single-property.php`
   - `single-bodrum-property.php`
   - `archive/single-property-v2.php`
   - `parts/form-sell-rent.php`

3. CRM templates (`wp-content/plugins/peracrm/inc/views/pages/crm-new.php` and `crm-client.php`) use the same helper-vs-fallback construct.

4. Plugin helper definition/load chain in repo:
   - `wp-content/plugins/peracrm/peracrm.php` unconditionally requires `inc/bootstrap.php`.
   - `wp-content/plugins/peracrm/inc/bootstrap.php` unconditionally requires `inc/phone-countries.php`.
   - `wp-content/plugins/peracrm/inc/phone-countries.php` defines `peracrm_phone_dial_code_options()`.

5. No alternate `parts/enquiry-form.php` file exists elsewhere under `wp-content/themes`.

6. The phone-country picker JS does not inject a 4-item fallback array; it only enhances existing `<select>` options.

## Conclusion
If rendered HTML contains exactly TR/GB/AE/US, then this request executed the fallback branch in `parts/enquiry-form.php`, which can only happen when `peracrm_phone_dial_code_options()` is unavailable during that request. In this repository, that is not caused by an in-repo conditional include in the plugin helper chain.

Therefore the mismatch is outside the inspected code path: either (a) non-CRM front-end requests are not bootstrapping this plugin in the runtime environment, or (b) stale cached HTML predating helper availability is being served.
