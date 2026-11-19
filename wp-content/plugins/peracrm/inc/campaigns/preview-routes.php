<?php
if (!defined('ABSPATH')) { exit; }

function peracrm_campaign_preview_csp()
{
    return "default-src 'none'; script-src 'none'; style-src 'unsafe-inline'; img-src https: http:; form-action 'none'; base-uri 'none'; object-src 'none'; frame-src 'none'; connect-src 'none'";
}

/** Authenticated POST only, including reads: no draft content in URLs or shared caches. */
function peracrm_campaign_preview_route()
{
    peracrm_subscribers_private_headers();
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: default-src \'none\'; frame-ancestors \'self\'; form-action \'none\'');
    if (!peracrm_campaigns_staff_can('peracrm_manage_campaigns',true)) { wp_send_json_error(['message'=>'Campaign preview access is not permitted.'],403); }
    if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') { wp_send_json_error(['message'=>'POST required.'],405); }
    if (!check_ajax_referer('peracrm_campaign_preview','nonce',false)) { wp_send_json_error(['message'=>'Invalid preview request.'],403); }
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>200000) { wp_send_json_error(['message'=>'Preview request is too large.'],413); }
    try {
        $operation=isset($_POST['operation']) && is_string($_POST['operation']) ? $_POST['operation'] : '';
        if ($operation==='search') {
            $result=peracrm_campaign_content_search(sanitize_key($_POST['kind'] ?? ''),wp_unslash($_POST['term'] ?? ''),max(0,(int)($_POST['after'] ?? 0)),strtolower(sanitize_text_field(wp_unslash($_POST['language'] ?? ''))));
        } elseif ($operation==='render') {
            $input=json_decode(wp_unslash($_POST['document_json'] ?? ''),true);
            $result=peracrm_campaign_render(peracrm_campaign_render_model($input));
            // The frame has an empty sandbox, plus this CSP; no executable/navigation elements are rendered.
            $meta='<meta http-equiv="Content-Security-Policy" content="'.esc_attr(peracrm_campaign_preview_csp()).'"><meta name="referrer" content="no-referrer">';
            $result['html']=str_replace('<head>','<head>'.$meta,$result['html']);
        } elseif (in_array($operation,['test-submit','test-status'],true)) {
            require_once __DIR__.'/draft-test-queue.php';
            peracrm_campaign_draft_test_authorize();
            $id=(int)($_POST['id'] ?? 0); $request_id=(string)($_POST['request_id'] ?? '');
            if ($id<1) { throw new InvalidArgumentException(); }
            if ($operation==='test-status') { $result=peracrm_campaign_draft_test_queue_status($id,$request_id); }
            else {
                $document=json_decode(wp_unslash($_POST['document_json'] ?? ''),true);
                if (!is_array($document)) { throw new InvalidArgumentException(); }
                $result=peracrm_campaign_draft_test_queue_submit($id,(int)($_POST['revision'] ?? 0),wp_unslash($_POST['recipient'] ?? ''),$request_id,$document);
            }
        } elseif ($operation==='audience') {
            $rules=json_decode(wp_unslash($_POST['rules_json'] ?? ''),true);
            $result=peracrm_campaign_audience_preview((int)($_POST['id'] ?? 0),(int)($_POST['revision'] ?? 0),$rules,wp_unslash($_POST['cursor'] ?? ''));
        } else { throw new InvalidArgumentException('Unsupported preview operation.'); }
        wp_send_json_success($result);
    } catch (UnexpectedValueException $error) { wp_send_json_error(['message'=>$error->getMessage()],409); }
    catch (InvalidArgumentException $error) { wp_send_json_error(['message'=>'Invalid preview input. Save valid fields or restart preview.'],422); }
    catch (Throwable $error) {
        $messages=[
            'draft_test_forbidden'=>'Administrator access is required for test emails.',
            'draft_test_revision'=>'The saved revision changed. Reload the campaign before sending a test.',
            'draft_test_unsaved'=>'Save the current draft before sending a test.',
            'draft_test_recipient_not_configured'=>'This test inbox is not configured. Ask an administrator to update the permitted test inboxes.',
            'draft_test_recipient_blocked'=>'This inbox has a bounce, complaint or administrative delivery block.',
            'draft_test_runner_offline'=>'The test sender is offline. Complete the one-time server setup or check its scheduled task.',
        ];
        wp_send_json_error(['message'=>$messages[$error->getMessage()] ?? 'Preview unavailable. Check access and storage readiness.'],503);
    }
}
add_action('wp_ajax_peracrm_campaign_preview','peracrm_campaign_preview_route');
add_action('wp_ajax_nopriv_peracrm_campaign_preview',static function () { peracrm_subscribers_private_headers(); wp_send_json_error(['message'=>'Campaign preview access is not permitted.'],403); });
