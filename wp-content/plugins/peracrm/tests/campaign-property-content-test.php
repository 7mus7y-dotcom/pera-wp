<?php
/** Regression: campaign property summaries consume production ACF keys, never obsolete prices. */
require __DIR__.'/campaign-previews-test.php';
function get_post_meta($id,$key,$single=true) { return $GLOBALS['property_meta'][$key] ?? ''; }
$GLOBALS['property_meta']=[
    'v2_units'=>4,
    'v2_units_0_v2_bedrooms'=>'2',
    'v2_units_0_v2_gross_size_min'=>'51',
    'v2_units_0_v2_gross_size_max'=>'54',
    'v2_units_0_v2_price_usd_min'=>'360000',
    'v2_units_0_v2_price_usd_max'=>'400000',
    'v2_units_1_v2_bedrooms'=>'0',
    'v2_units_1_v2_price_usd_min'=>'250000',
    'v2_units_2_v2_beds'=>'99', // obsolete field must never supply the displayed bedroom count
    'v2_units_3_v2_bedrooms'=>'3',
    'price'=>'1','price_usd'=>'2',
];
$units=peracrm_campaign_property_units(42);
campaign_expect($units['summary']==="2 beds · 51–54 m² · USD 360,000–400,000\nStudio · USD 250,000\n3 beds",'real ACF bedrooms, studio, USD range, partial units and blank-row omission');
campaign_expect(count($units['units'])===3 && $units['units'][0]['v2_beds']===2.0,'normalized model stays compatible with existing capture shape');
$GLOBALS['property_meta']=['v2_units'=>2,'price_usd'=>900000];
campaign_expect(peracrm_campaign_property_units(42)['summary']==='Unit details available on the property page.','no invented price or repeated unavailable rows when all canonical rows are blank');
echo "PASS campaign property content regressions\n";
