<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_lead_stage_options()
{
    return [
        'new_enquiry' => 'New enquiry',
        'contacted' => 'Contacted',
        'qualified' => 'Qualified',
        'viewing_arranged' => 'Viewing arranged',
        'offer_made' => 'Offer made',
        'negotiation' => 'Negotiation',
    ];
}

function peracrm_deal_stage_options_map()
{
    return [
        'reservation_taken' => 'Reservation taken',
        'contract_signed' => 'Contract signed',
        'payment_in_progress' => 'Payment in progress',
        'completed' => 'Completed',
        'lost' => 'Lost',
    ];
}

function peracrm_closed_reason_options()
{
    return [
        'none' => 'None',
        'lost_price' => 'Lost: Price',
        'lost_finance' => 'Lost: Finance',
        'lost_competitor' => 'Lost: Competitor',
        'junk_lead' => 'Junk lead',
        'duplicate' => 'Duplicate',
    ];
}

function peracrm_map_legacy_lead_stage($legacy_stage)
{
    $legacy_stage = sanitize_key((string) $legacy_stage);

    $mapping = [
        'enquiry' => 'new_enquiry',
        'new_enquiry' => 'new_enquiry',
        'active' => 'qualified',
        'active_search' => 'viewing_arranged',
        'deal_created' => 'offer_made',
        'dormant' => 'qualified',
        'qualified' => 'qualified',
        'contacted' => 'contacted',
        'viewing_arranged' => 'viewing_arranged',
        'offer_made' => 'offer_made',
        'negotiation' => 'negotiation',
    ];

    return $mapping[$legacy_stage] ?? 'new_enquiry';
}

function peracrm_map_legacy_deal_stage($legacy_stage)
{
    $legacy_stage = sanitize_key((string) $legacy_stage);

    $mapping = [
        'qualified' => 'reservation_taken',
        'reserved' => 'reservation_taken',
        'contract_signed' => 'contract_signed',
        'closed_won' => 'completed',
        'closed_lost' => 'lost',
        'reservation_taken' => 'reservation_taken',
        'payment_in_progress' => 'payment_in_progress',
        'completed' => 'completed',
        'lost' => 'lost',
    ];

    return $mapping[$legacy_stage] ?? 'reservation_taken';
}

function peracrm_normalize_closed_reason($reason)
{
    $reason = sanitize_key((string) $reason);
    $allowed = peracrm_closed_reason_options();

    if (isset($allowed[$reason])) {
        return $reason;
    }

    if ($reason === 'junk') {
        return 'junk_lead';
    }

    return 'none';
}
