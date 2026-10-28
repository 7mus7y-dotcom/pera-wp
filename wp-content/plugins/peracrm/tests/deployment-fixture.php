<?php
require_once dirname(__DIR__).'/inc/campaigns/deployment.php';
function campaign_deployment_manifest(int $blog=2,string $deployment='deployment-fixture'): array
{
    return ['provider'=>'ses_v2','account_id'=>'123456789012','region'=>'eu-west-1','configuration_set'=>'fixture',
        'configuration_version'=>1,'deployment'=>$deployment,'blog_id'=>$blog,'origin'=>'https://example.org',
        'instance_id'=>'fixture-instance-01','identity_version'=>1,'signing_key_id'=>'k1','encryption_key_id'=>'e1'];
}
function campaign_deployment_material(int $now): array
{
    return ['identity'=>['account_id'=>'123456789012','version'=>1,'access_key'=>str_repeat('A',20),
        'secret_key'=>str_repeat('Z',40),'session_token'=>'synthetic-session-only','not_before'=>$now-60,'expires_at'=>$now+3600],
        'signing'=>['active'=>'k1','keys'=>['k1'=>str_repeat('s',32)],'not_before'=>$now-60,'expires_at'=>$now+86400],
        'encryption'=>['active'=>'e1','keys'=>['e1'=>str_repeat('e',32)],'not_before'=>$now-60,'expires_at'=>$now+86400]];
}
function campaign_deployment_runtime(array $manifest): Closure
{
    return static fn()=>array_intersect_key($manifest,array_flip(['instance_id','origin','blog_id']));
}
