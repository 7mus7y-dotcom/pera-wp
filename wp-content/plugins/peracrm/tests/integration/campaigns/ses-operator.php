<?php
require_once __DIR__.'/prepared-worker-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/operations.php';
require_once WP_PLUGIN_DIR.'/peracrm/tests/deployment-fixture.php';
worker_fixture_target(static function () {
    $admin=(int)get_option('campaign_fixture_administrator');
    $account='234567890123';
    $manifest=array_replace(campaign_deployment_manifest(get_current_blog_id(),'operator-fixture'),['account_id'=>$account]);
    $now=time();
    $material=campaign_deployment_material($now);
    $material['identity']['account_id']=$account;
    $deployment=new PeraCRM_Campaign_Deployment($manifest,$material,static fn()=>time(),campaign_deployment_runtime($manifest));
    $settings=['enabled'=>true,'role_name'=>'PeraCRMSESReadOnly','per_second_cap'=>3,'daily_cap'=>40];
    $calls=[]; $role='WrongRole'; $remaining=28.9;
    $transport=static function ($request) use (&$calls,&$role,&$remaining,$account) {
        $uri=$request->getUri();
        $calls[]=(string)$uri;
        prepared_expect($request->getMethod()==='GET' && $request->getHeaderLine('X-Amz-Security-Token')==='synthetic-session-only'
            && str_contains($request->getHeaderLine('Authorization'),'/eu-west-1/'),'operator requests use temporary regional signing');
        $body=match ($uri->getPath()) {
            '/' => '<GetCallerIdentityResponse xmlns="https://sts.amazonaws.com/doc/2011-06-15/"><GetCallerIdentityResult><Account>'
                .$account.'</Account><Arn>arn:aws:sts::'.$account.':assumed-role/'.$role.'/fixture</Arn></GetCallerIdentityResult></GetCallerIdentityResponse>',
            '/v2/email/account' => json_encode(['SendingEnabled'=>true,'SendQuota'=>['MaxSendRate'=>5.7,'Max24HourSend'=>$remaining,'SentLast24Hours'=>0]]),
            '/v2/email/suppression/addresses' => '{"SuppressedDestinationSummaries":[]}',
            default => throw new RuntimeException('unexpected URL'),
        };
        return ['complete'=>true,'status'=>200,'body'=>$body];
    };
    $operator=new PeraCRM_SES_Operator($deployment,$settings,$transport);
    wp_set_current_user(0);
    prepared_reject(fn()=>$operator->refreshQuota());
    prepared_expect($calls===[],'unauthorized actor cannot call STS');
    wp_set_current_user($admin);
    prepared_reject(fn()=>$operator->suppression(1,false));
    prepared_expect(count($calls)===1 && $operator->suppression(null,false)['state']==='never'
        && !$operator->budgetHealth()['configured'],'wrong role does not read SES or persist state');
    $role=$settings['role_name'];
    $sync=$operator->suppression(1,false);
    prepared_expect($sync['state']==='completed' && !$sync['production_enabled'],'explicit sync completes without activation');
    $quota=$operator->refreshQuota();
    prepared_expect($quota['per_second']===3 && $quota['daily']===28 && !$quota['production_enabled']
        && $operator->budgetHealth()['quota_fresh'],'quota is capped, rounded down, and stored');
    $remaining=0;
    prepared_reject(fn()=>$operator->refreshQuota());
    prepared_expect($operator->budgetHealth()['quota_fresh'],'invalid response does not overwrite prior observation');
    prepared_expect($calls===[
        'https://sts.eu-west-1.amazonaws.com/?Action=GetCallerIdentity&Version=2011-06-15',
        'https://sts.eu-west-1.amazonaws.com/?Action=GetCallerIdentity&Version=2011-06-15',
        'https://email.eu-west-1.amazonaws.com/v2/email/suppression/addresses?PageSize=25',
        'https://sts.eu-west-1.amazonaws.com/?Action=GetCallerIdentity&Version=2011-06-15',
        'https://email.eu-west-1.amazonaws.com/v2/email/account',
        'https://sts.eu-west-1.amazonaws.com/?Action=GetCallerIdentity&Version=2011-06-15',
        'https://email.eu-west-1.amazonaws.com/v2/email/account',
    ] && !peracrm_campaign_queue_dispatch_allowed() && peracrm_sending_budget_service()===null,'only approved reads; dispatch and recovery stay disabled');
});
echo "PASS explicit SES operator runtime\n";
