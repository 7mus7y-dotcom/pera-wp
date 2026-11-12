<?php
if (!defined('ABSPATH')) { exit; }

/** Editable starting copy only. Applying it never saves or grants approval. */
function peracrm_campaign_footer_template(): string
{
    return "Pera Property İnşaat Sanayi ve Ticaret Ltd. Şti.\n"
        ."Ömer Avni, Balçık Sk. No:6, 34437 Beyoğlu/İstanbul, Türkiye\n"
        ."www.peraproperty.com · info@peraproperty.com · +90 545 205 43 56\n\n"
        ."You are receiving this email because you subscribed to Pera Property updates. "
        ."You can unsubscribe from marketing email at any time using the link below.";
}

/** Token-bearing pages are self-contained: no theme hooks, analytics or asset requests. */
function peracrm_campaign_public_page_css(): string
{
    static $css;
    if ($css === null) {
        $path=dirname(__DIR__,2).'/assets/frontend/email-preferences.css';
        $css=is_readable($path)?(string)file_get_contents($path):'';
    }
    return $css;
}
function peracrm_campaign_public_page_csp(): string
{
    $hash=base64_encode(hash('sha256',peracrm_campaign_public_page_css(),true));
    return "default-src 'none'; style-src 'sha256-{$hash}'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'";
}
function peracrm_campaign_public_page_start(string $title,string $eyebrow='Email preferences',string $state='neutral'): void
{
    $path=dirname(__DIR__,2).'/logos-icons/pera-logo.svg';
    $logo=is_readable($path)?(string)file_get_contents($path):'<span>Pera Property</span>';
    $logo=preg_replace('/<\?xml[^>]*\?>|\sstyle="[^"]*"/','',$logo);
    $logo=preg_replace('/<svg\b/','<svg class="email-brand__logo" aria-hidden="true" focusable="false"',$logo,1);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><title>'.esc_html($title).' | Pera Property</title><style>'.peracrm_campaign_public_page_css().'</style></head>';
    echo '<body class="pera-email-page"><a class="email-skip" href="#primary">Skip to content</a><header class="email-header"><div class="email-container email-header__inner"><a class="email-brand" href="'.esc_url(home_url('/')).'" rel="noreferrer" aria-label="Pera Property home">'.$logo.'<span>ISTANBUL · SINCE 2016</span></a><span class="email-header__label">Email preferences</span></div></header>';
    echo '<main id="primary" class="email-main email-container"><section class="email-card email-card--'.esc_attr(in_array($state,['success','error'],true)?$state:'neutral').'" aria-labelledby="email-title"><p class="email-eyebrow">'.esc_html($eyebrow).'</p>';
    if ($state==='success') { echo '<span class="email-result-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>'; }
    echo '<h1 id="email-title">'.esc_html($title).'</h1><div class="email-card__body">';
}
function peracrm_campaign_public_page_end(): void
{
    echo '</div><div class="email-help"><strong>Need a hand?</strong><p>Our Istanbul team can help with your email preferences. <a href="mailto:info@peraproperty.com" rel="noreferrer">info@peraproperty.com</a></p></div></section>';
    echo '<p class="email-return"><a href="'.esc_url(home_url('/')).'" rel="noreferrer">Return to Pera Property <span aria-hidden="true">→</span></a></p></main>';
    echo '<footer class="email-footer email-container"><div><strong>Pera Property</strong><p>Ömer Avni, Balçık Sk. No:6<br>34437 Beyoğlu/İstanbul, Türkiye</p></div><nav aria-label="Company information"><a href="'.esc_url(home_url('/privacy-policy/')).'" rel="noreferrer">Privacy policy</a><a href="'.esc_url(home_url('/contact-us/')).'" rel="noreferrer">Contact our team</a></nav></footer></body></html>';
}
