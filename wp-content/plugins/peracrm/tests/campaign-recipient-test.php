<?php
/** No DB/network/secrets: synthetic recipient token and expansion regressions. */
require __DIR__.'/campaign-snapshots-test.php';
require __DIR__.'/recipient-services-fixture.php';
peracrm_test_recipient_services($saved['payload']['content'], 'campaign_expect');
echo "PASS recipient tokens, rotation, expiry, revocation, origins, escaping and immutable slot expansion\n";
