<?php
require __DIR__.'/campaign-snapshots-test.php';
require __DIR__.'/recipient-persistence-fixture.php';
peracrm_test_persistence_projection($saved['payload']['content'],'campaign_expect',2);
echo "PASS recipient persistence projections and redaction (SQL exercised separately)\n";
