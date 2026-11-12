<?php
define('ABSPATH',__DIR__);
function esc_html($value) { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_attr($value); }
function home_url($path='') { return 'https://example.test'.$path; }
require dirname(__DIR__).'/inc/campaigns/presentation.php';
function presentation_expect($ok,string $label): void {
    if (!$ok) { throw new RuntimeException('FAIL campaign presentation: '.$label); }
    echo 'PASS campaign presentation: '.$label."\n";
}
$footer=peracrm_campaign_footer_template();
presentation_expect(strlen($footer)<=4000 && preg_match('//u',$footer)===1 && strip_tags($footer)===$footer,'footer is bounded editable plain text');
presentation_expect(str_contains($footer,'Pera Property İnşaat') && str_contains($footer,'34437')
    && str_contains($footer,'info@peraproperty.com') && str_contains($footer,'unsubscribe'),'company footer includes identity, address, contact and opt-out wording');
foreach (['neutral','success','error'] as $state) {
    ob_start(); peracrm_campaign_public_page_start('<script>untrusted</script>','Email choices',$state);
    echo '<p>Fixture message.</p>'; peracrm_campaign_public_page_end(); $html=ob_get_clean();
    presentation_expect(str_contains($html,'Pera Property home') && str_contains($html,'Privacy policy'),'company branding and navigation '.$state);
    presentation_expect(!str_contains($html,'<script') && !str_contains($html,'<link') && !str_contains($html,'<img')
        && !str_contains($html,' style=') && str_contains($html,'&lt;script&gt;'),'escaped text and no asset/script requests '.$state);
    preg_match('~<style>(.*?)</style>~s',$html,$match);
    $hash=base64_encode(hash('sha256',$match[1]??'',true));
    $csp=peracrm_campaign_public_page_csp();
    presentation_expect(!empty($match[1]) && str_contains($csp,"style-src 'sha256-{$hash}'")
        && str_contains($csp,"default-src 'none'") && !str_contains($csp,'unsafe-inline'),'exact inline stylesheet CSP hash '.$state);
    presentation_expect(str_contains($html,'name="referrer" content="no-referrer"')
        && str_contains($csp,"form-action 'self'") && str_contains($csp,"frame-ancestors 'none'"),'private token-page boundaries '.$state);
}
