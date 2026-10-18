<?php
if (!defined('ABSPATH')) { exit; }

function peracrm_campaign_drafts_require($write = false, $configuration = false)
{
    if (!peracrm_campaigns_staff_can('peracrm_manage_campaigns', $write)
        || ($configuration && !peracrm_with_target_blog(static function () { return user_can(peracrm_get_actor_user_id(), 'manage_options'); }))) {
        throw new RuntimeException('Campaign access is not permitted.');
    }
}

function peracrm_campaign_drafts_url($suffix = '')
{
    return peracrm_with_target_blog(static function () use ($suffix) { return home_url('/crm/campaigns/' . $suffix); });
}

/** Reject oversized input before sanitizing. Plain text only; no authored HTML. Bounds are bytes. */
function peracrm_campaign_draft_text($input, $limit, $multiline = false)
{
    if (!is_string($input) || strlen($input) > $limit) { throw new InvalidArgumentException('A field exceeds its allowed size or has an invalid type.'); }
    return $multiline ? sanitize_textarea_field($input) : sanitize_text_field($input);
}

function peracrm_campaign_draft_document($input)
{
    $keys = ['schema_version','internal_name','subject','preheader','language','sender_id','introduction','main_text','blocks'];
    if (!is_array($input) || array_diff(array_keys($input), $keys) || (isset($input['schema_version']) && $input['schema_version'] !== 1)) {
        throw new InvalidArgumentException('Unsupported campaign document.');
    }
    $out = ['schema_version' => 1];
    foreach (['internal_name'=>160,'subject'=>200,'preheader'=>300,'language'=>35,'sender_id'=>64,'introduction'=>4000,'main_text'=>16000] as $key=>$limit) {
        $out[$key] = peracrm_campaign_draft_text($input[$key] ?? '', $limit, in_array($key, ['introduction','main_text'], true));
    }
    if ($out['language'] !== '' && !preg_match('/^[a-zA-Z]{2,8}(?:-[a-zA-Z0-9]{1,8})*$/D', $out['language'])) { throw new InvalidArgumentException('Use an explicit language tag, for example en or de.'); }
    $out['language'] = strtolower($out['language']);
    if ($out['sender_id'] !== '' && (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $out['sender_id']) || $out['sender_id'] !== ($input['sender_id'] ?? ''))) { throw new InvalidArgumentException('Invalid sender identity key.'); }
    $blocks = $input['blocks'] ?? [];
    if (!is_array($blocks) || array_values($blocks) !== $blocks || count($blocks) > 20) { throw new InvalidArgumentException('Use at most 20 ordered blocks.'); }
    $out['blocks'] = [];
    foreach ($blocks as $block) {
        if (!is_array($block) || !in_array($block['type'] ?? '', ['text','cta'], true)) { throw new InvalidArgumentException('Unsupported block type.'); }
        $allowed = $block['type'] === 'text' ? ['type','heading','text'] : ['type','label','url'];
        if (array_diff(array_keys($block), $allowed)) { throw new InvalidArgumentException('Unsupported block fields.'); }
        if ($block['type'] === 'text') {
            $out['blocks'][] = ['type'=>'text','heading'=>peracrm_campaign_draft_text($block['heading'] ?? '',160), 'text'=>peracrm_campaign_draft_text($block['text'] ?? '',4000,true)];
        } else {
            $url = peracrm_campaign_draft_text($block['url'] ?? '',2048);
            if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) wp_parse_url($url, PHP_URL_SCHEME)), ['http','https'], true) || wp_parse_url($url, PHP_URL_USER) !== null || wp_parse_url($url, PHP_URL_PASS) !== null)) {
                throw new InvalidArgumentException('CTA links must be absolute HTTP or HTTPS URLs without credentials.');
            }
            $out['blocks'][] = ['type'=>'cta','label'=>peracrm_campaign_draft_text($block['label'] ?? '',160), 'url'=>esc_url_raw($url, ['http','https'])];
        }
    }
    if (strlen(wp_json_encode($out)) > 131072) { throw new InvalidArgumentException('Campaign document is too large.'); }
    return $out;
}

function peracrm_campaign_configuration_document($input)
{
    if (!is_array($input) || array_diff(array_keys($input), ['senders','footer','footer_approved'])) { throw new InvalidArgumentException('Unsupported configuration.'); }
    $senders = $input['senders'] ?? [];
    if (!is_array($senders) || array_values($senders) !== $senders || count($senders) > 10) { throw new InvalidArgumentException('Use at most 10 sender identities.'); }
    $out = ['senders'=>[], 'footer'=>peracrm_campaign_draft_text($input['footer'] ?? '',4000,true), 'footer_approved'=>!empty($input['footer_approved'])];
    foreach ($senders as $sender) {
        if (!is_array($sender) || array_diff(array_keys($sender), ['id','name','email','reply_to','approved'])) { throw new InvalidArgumentException('Unsupported sender fields.'); }
        $id = peracrm_campaign_draft_text($sender['id'] ?? '',64);
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $id) || $id !== ($sender['id'] ?? '') || isset($out['senders'][$id])) { throw new InvalidArgumentException('Sender keys must be unique, stable lowercase identifiers.'); }
        $name = peracrm_campaign_draft_text($sender['name'] ?? '',160);
        $email = peracrm_campaign_draft_text($sender['email'] ?? '',254);
        $reply = peracrm_campaign_draft_text($sender['reply_to'] ?? '',254);
        if ($email !== '') { peracrm_campaigns_normalize_email($email); }
        if ($reply !== '') { peracrm_campaigns_normalize_email($reply); }
        $approved = !empty($sender['approved']);
        if ($approved && ($name === '' || $email === '' || $reply === '')) { throw new InvalidArgumentException('An approved sender requires name, email and reply-to.'); }
        $out['senders'][$id] = ['id'=>$id,'name'=>$name,'email'=>$email,'reply_to'=>$reply,'approved'=>$approved];
    }
    $out['senders'] = array_values($out['senders']);
    if ($out['footer_approved'] && $out['footer'] === '') { throw new InvalidArgumentException('Approved footer text is required.'); }
    return $out;
}

/** Every public read authorizes before entering target storage. No raw repository read API. */
function peracrm_campaign_configuration($administration = false)
{
    peracrm_campaign_drafts_require(false, $administration);
    return peracrm_with_target_blog(static function () use ($administration) {
        global $wpdb;
        $row = $wpdb->get_row('SELECT * FROM ' . peracrm_table('peracrm_campaign_configuration') . ' WHERE id=1', ARRAY_A);
        peracrm_campaigns_db_require(true);
        $result = $row ? ['revision'=>(int)$row['revision'],'document'=>json_decode($row['document_json'],true),'approved_by'=>(int)$row['approved_by']] : ['revision'=>0,'document'=>['senders'=>[],'footer'=>'','footer_approved'=>false],'approved_by'=>0];
        if (!$administration) {
            $result['document']['senders'] = array_values(array_filter($result['document']['senders'], static function ($sender) { return !empty($sender['approved']); }));
            if (empty($result['document']['footer_approved'])) { $result['document']['footer'] = ''; }
        }
        return $result;
    });
}

function peracrm_campaign_draft_missing($doc, $configuration)
{
    $missing = [];
    foreach (['internal_name'=>'Internal name','subject'=>'Subject','language'=>'Explicit language'] as $key=>$label) { if ($doc[$key] === '') { $missing[] = $label; } }
    $sender = array_filter($configuration['senders'], static function ($sender) use ($doc) { return $sender['id'] === $doc['sender_id'] && $sender['approved']; });
    if (!$sender) { $missing[] = 'Approved sender and reply-to'; }
    if (!$configuration['footer_approved'] || $configuration['footer'] === '') { $missing[] = 'Approved mandatory footer'; }
    if ($doc['introduction'] === '' && $doc['main_text'] === '' && !$doc['blocks']) { $missing[] = 'Content'; }
    foreach ($doc['blocks'] as $index=>$block) {
        if (($block['type'] === 'text' && $block['text'] === '') || ($block['type'] === 'cta' && ($block['label'] === '' || $block['url'] === ''))) { $missing[] = 'Complete block ' . ($index + 1); }
    }
    return $missing;
}

function peracrm_campaign_draft_get($id)
{
    peracrm_campaign_drafts_require();
    return peracrm_with_target_blog(static function () use ($id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . peracrm_table('peracrm_campaign_drafts') . ' WHERE id=%d', $id), ARRAY_A);
        peracrm_campaigns_db_require(true);
        if ($row) { $row['document'] = json_decode($row['document_json'], true); }
        return $row;
    });
}

function peracrm_campaign_draft_list($page = 1)
{
    peracrm_campaign_drafts_require();
    return peracrm_with_target_blog(static function () use ($page) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT id,internal_name,subject,language,status,updated_at FROM ' . peracrm_table('peracrm_campaign_drafts') . ' ORDER BY updated_at DESC,id DESC LIMIT 26 OFFSET %d', (min(10000,max(1,(int)$page))-1)*25), ARRAY_A);
        peracrm_campaigns_db_require(true);
        return $rows;
    });
}

function peracrm_campaign_draft_history($id, $page = 1)
{
    peracrm_campaign_drafts_require();
    return peracrm_with_target_blog(static function () use ($id,$page) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT revision,document_json,actor_user_id,created_at FROM ' . peracrm_table('peracrm_campaign_draft_revisions') . ' WHERE campaign_id=%d ORDER BY revision DESC LIMIT 25 OFFSET %d', $id,(min(10000,max(1,(int)$page))-1)*25), ARRAY_A);
        peracrm_campaigns_db_require(true);
        return $rows;
    });
}

/** Transaction rollback includes current draft and its immutable history row. */
function peracrm_campaign_draft_transaction(callable $callback, $configuration = false)
{
    try {
        peracrm_campaign_drafts_require(true, $configuration);
        return peracrm_with_target_blog(static function () use ($callback) {
            global $wpdb;
            $errors = $wpdb->suppress_errors(true);
            try {
                peracrm_campaigns_db_require($wpdb->query('START TRANSACTION'));
                $result = $callback();
                peracrm_campaigns_db_require($wpdb->query('COMMIT'));
                return $result;
            } catch (Throwable $error) { $wpdb->query('ROLLBACK'); throw $error; }
            finally { $wpdb->suppress_errors($errors); }
        });
    } catch (InvalidArgumentException $error) { return new WP_Error('campaign_invalid', $error->getMessage()); }
    catch (UnexpectedValueException $error) { return new WP_Error('campaign_conflict', 'A newer revision exists. Your input is retained. Open the saved version in another tab and reconcile before saving.'); }
    catch (Throwable $error) { return new WP_Error('campaign_unavailable', 'Change was not accepted. Check access and storage readiness. Your input is retained.'); }
}

function peracrm_campaign_draft_save($id, $expected_revision, $input)
{
    return peracrm_campaign_draft_transaction(static function () use ($id,$expected_revision,$input) {
        global $wpdb;
        $doc = peracrm_campaign_draft_document($input);
        if (!is_int($id) || $id < 0 || !is_int($expected_revision) || $expected_revision < 0 || (!$id && $expected_revision !== 0)) { throw new InvalidArgumentException('Invalid campaign revision.'); }
        $table = peracrm_table('peracrm_campaign_drafts');
        if ($id) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT revision FROM {$table} WHERE id=%d FOR UPDATE",$id),ARRAY_A);
            peracrm_campaigns_db_require(true);
            if (!$row || (int)$row['revision'] !== $expected_revision) { throw new UnexpectedValueException(); }
        }
        $revision = $expected_revision + 1;
        $now = gmdate('Y-m-d H:i:s'); $actor = peracrm_get_actor_user_id();
        $data = ['internal_name'=>$doc['internal_name'],'subject'=>$doc['subject'],'language'=>$doc['language'],'status'=>'draft','document_json'=>wp_json_encode($doc),'revision'=>$revision,'updated_by'=>$actor,'updated_at'=>$now];
        if ($id) {
            $updated = $wpdb->update($table,$data,['id'=>$id,'revision'=>$expected_revision]);
            peracrm_campaigns_db_require($updated);
            if ($updated !== 1) { throw new UnexpectedValueException(); }
        } else {
            peracrm_campaigns_db_require($wpdb->insert($table,$data+['created_by'=>$actor,'created_at'=>$now]));
            $id = (int)$wpdb->insert_id;
        }
        // No UPDATE/DELETE path for revision rows, including duplication.
        peracrm_campaigns_db_require($wpdb->insert(peracrm_table('peracrm_campaign_draft_revisions'),['campaign_id'=>$id,'revision'=>$revision,'document_json'=>$data['document_json'],'actor_user_id'=>$actor,'created_at'=>$now]));
        return ['id'=>$id,'revision'=>$revision,'document'=>$doc];
    });
}

function peracrm_campaign_draft_duplicate($id)
{
    try {
        peracrm_campaign_drafts_require(true);
        $source = peracrm_campaign_draft_get($id);
        if (!$source) { throw new RuntimeException(); }
        $doc = $source['document'];
        $name = substr($doc['internal_name'],0,150);
        while ($name !== '' && !preg_match('//u',$name)) { $name = substr($name,0,-1); }
        $doc['internal_name'] = $name . ' (copy)';
        return peracrm_campaign_draft_save(0,0,$doc);
    } catch (Throwable $error) { return new WP_Error('campaign_unavailable','Campaign could not be duplicated.'); }
}

function peracrm_campaign_configuration_save($expected_revision, $input)
{
    return peracrm_campaign_draft_transaction(static function () use ($expected_revision,$input) {
        global $wpdb;
        $doc = peracrm_campaign_configuration_document($input);
        if (!is_int($expected_revision) || $expected_revision < 0) { throw new InvalidArgumentException('Invalid configuration revision.'); }
        $table = peracrm_table('peracrm_campaign_configuration');
        peracrm_campaigns_db_require($wpdb->query("INSERT IGNORE INTO {$table} (id,revision,document_json,approved_by,updated_at) VALUES (1,0,'{}',0,'1970-01-01 00:00:00')"));
        $revision = $wpdb->get_var("SELECT revision FROM {$table} WHERE id=1 FOR UPDATE");
        peracrm_campaigns_db_require(true);
        if ((int)$revision !== $expected_revision) { throw new UnexpectedValueException(); }
        peracrm_campaigns_db_require($wpdb->update($table,['revision'=>$expected_revision+1,'document_json'=>wp_json_encode($doc),'approved_by'=>peracrm_get_actor_user_id(),'updated_at'=>gmdate('Y-m-d H:i:s')],['id'=>1,'revision'=>$expected_revision]));
        return ['revision'=>$expected_revision+1,'document'=>$doc];
    }, true);
}
