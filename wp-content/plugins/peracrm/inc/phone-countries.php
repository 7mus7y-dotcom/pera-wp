<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('peracrm_phone_country_dataset')) {
    function peracrm_phone_country_dataset(): array
    {
        static $countries = null;
        if (is_array($countries)) {
            return $countries;
        }

        $countries = [
            ['iso' => 'AF', 'name' => 'Afghanistan', 'dial_code' => '+93'],
            ['iso' => 'AL', 'name' => 'Albania', 'dial_code' => '+355'],
            ['iso' => 'DZ', 'name' => 'Algeria', 'dial_code' => '+213'],
            ['iso' => 'AD', 'name' => 'Andorra', 'dial_code' => '+376'],
            ['iso' => 'AO', 'name' => 'Angola', 'dial_code' => '+244'],
            ['iso' => 'AR', 'name' => 'Argentina', 'dial_code' => '+54'],
            ['iso' => 'AM', 'name' => 'Armenia', 'dial_code' => '+374'],
            ['iso' => 'AU', 'name' => 'Australia', 'dial_code' => '+61'],
            ['iso' => 'AT', 'name' => 'Austria', 'dial_code' => '+43'],
            ['iso' => 'AZ', 'name' => 'Azerbaijan', 'dial_code' => '+994'],
            ['iso' => 'BH', 'name' => 'Bahrain', 'dial_code' => '+973'],
            ['iso' => 'BD', 'name' => 'Bangladesh', 'dial_code' => '+880'],
            ['iso' => 'BY', 'name' => 'Belarus', 'dial_code' => '+375'],
            ['iso' => 'BE', 'name' => 'Belgium', 'dial_code' => '+32'],
            ['iso' => 'BZ', 'name' => 'Belize', 'dial_code' => '+501'],
            ['iso' => 'BJ', 'name' => 'Benin', 'dial_code' => '+229'],
            ['iso' => 'BO', 'name' => 'Bolivia', 'dial_code' => '+591'],
            ['iso' => 'BA', 'name' => 'Bosnia and Herzegovina', 'dial_code' => '+387'],
            ['iso' => 'BW', 'name' => 'Botswana', 'dial_code' => '+267'],
            ['iso' => 'BR', 'name' => 'Brazil', 'dial_code' => '+55'],
            ['iso' => 'BN', 'name' => 'Brunei', 'dial_code' => '+673'],
            ['iso' => 'BG', 'name' => 'Bulgaria', 'dial_code' => '+359'],
            ['iso' => 'BF', 'name' => 'Burkina Faso', 'dial_code' => '+226'],
            ['iso' => 'BI', 'name' => 'Burundi', 'dial_code' => '+257'],
            ['iso' => 'KH', 'name' => 'Cambodia', 'dial_code' => '+855'],
            ['iso' => 'CM', 'name' => 'Cameroon', 'dial_code' => '+237'],
            ['iso' => 'CA', 'name' => 'Canada', 'dial_code' => '+1'],
            ['iso' => 'CV', 'name' => 'Cape Verde', 'dial_code' => '+238'],
            ['iso' => 'CF', 'name' => 'Central African Republic', 'dial_code' => '+236'],
            ['iso' => 'TD', 'name' => 'Chad', 'dial_code' => '+235'],
            ['iso' => 'CL', 'name' => 'Chile', 'dial_code' => '+56'],
            ['iso' => 'CN', 'name' => 'China', 'dial_code' => '+86'],
            ['iso' => 'CO', 'name' => 'Colombia', 'dial_code' => '+57'],
            ['iso' => 'KM', 'name' => 'Comoros', 'dial_code' => '+269'],
            ['iso' => 'CG', 'name' => 'Congo', 'dial_code' => '+242'],
            ['iso' => 'CR', 'name' => 'Costa Rica', 'dial_code' => '+506'],
            ['iso' => 'CI', 'name' => "Côte d’Ivoire", 'dial_code' => '+225'],
            ['iso' => 'HR', 'name' => 'Croatia', 'dial_code' => '+385'],
            ['iso' => 'CU', 'name' => 'Cuba', 'dial_code' => '+53'],
            ['iso' => 'CY', 'name' => 'Cyprus', 'dial_code' => '+357'],
            ['iso' => 'CZ', 'name' => 'Czechia', 'dial_code' => '+420'],
            ['iso' => 'DK', 'name' => 'Denmark', 'dial_code' => '+45'],
            ['iso' => 'DJ', 'name' => 'Djibouti', 'dial_code' => '+253'],
            ['iso' => 'DO', 'name' => 'Dominican Republic', 'dial_code' => '+1'],
            ['iso' => 'EC', 'name' => 'Ecuador', 'dial_code' => '+593'],
            ['iso' => 'EG', 'name' => 'Egypt', 'dial_code' => '+20'],
            ['iso' => 'SV', 'name' => 'El Salvador', 'dial_code' => '+503'],
            ['iso' => 'GQ', 'name' => 'Equatorial Guinea', 'dial_code' => '+240'],
            ['iso' => 'ER', 'name' => 'Eritrea', 'dial_code' => '+291'],
            ['iso' => 'EE', 'name' => 'Estonia', 'dial_code' => '+372'],
            ['iso' => 'ET', 'name' => 'Ethiopia', 'dial_code' => '+251'],
            ['iso' => 'FI', 'name' => 'Finland', 'dial_code' => '+358'],
            ['iso' => 'FR', 'name' => 'France', 'dial_code' => '+33'],
            ['iso' => 'GA', 'name' => 'Gabon', 'dial_code' => '+241'],
            ['iso' => 'GM', 'name' => 'Gambia', 'dial_code' => '+220'],
            ['iso' => 'GE', 'name' => 'Georgia', 'dial_code' => '+995'],
            ['iso' => 'DE', 'name' => 'Germany', 'dial_code' => '+49'],
            ['iso' => 'GH', 'name' => 'Ghana', 'dial_code' => '+233'],
            ['iso' => 'GR', 'name' => 'Greece', 'dial_code' => '+30'],
            ['iso' => 'GT', 'name' => 'Guatemala', 'dial_code' => '+502'],
            ['iso' => 'GN', 'name' => 'Guinea', 'dial_code' => '+224'],
            ['iso' => 'GW', 'name' => 'Guinea-Bissau', 'dial_code' => '+245'],
            ['iso' => 'GY', 'name' => 'Guyana', 'dial_code' => '+592'],
            ['iso' => 'HT', 'name' => 'Haiti', 'dial_code' => '+509'],
            ['iso' => 'HN', 'name' => 'Honduras', 'dial_code' => '+504'],
            ['iso' => 'HK', 'name' => 'Hong Kong', 'dial_code' => '+852'],
            ['iso' => 'HU', 'name' => 'Hungary', 'dial_code' => '+36'],
            ['iso' => 'IS', 'name' => 'Iceland', 'dial_code' => '+354'],
            ['iso' => 'IN', 'name' => 'India', 'dial_code' => '+91'],
            ['iso' => 'ID', 'name' => 'Indonesia', 'dial_code' => '+62'],
            ['iso' => 'IR', 'name' => 'Iran', 'dial_code' => '+98'],
            ['iso' => 'IQ', 'name' => 'Iraq', 'dial_code' => '+964'],
            ['iso' => 'IE', 'name' => 'Ireland', 'dial_code' => '+353'],
            ['iso' => 'IL', 'name' => 'Israel', 'dial_code' => '+972'],
            ['iso' => 'IT', 'name' => 'Italy', 'dial_code' => '+39'],
            ['iso' => 'JM', 'name' => 'Jamaica', 'dial_code' => '+1'],
            ['iso' => 'JP', 'name' => 'Japan', 'dial_code' => '+81'],
            ['iso' => 'JO', 'name' => 'Jordan', 'dial_code' => '+962'],
            ['iso' => 'KZ', 'name' => 'Kazakhstan', 'dial_code' => '+7'],
            ['iso' => 'KE', 'name' => 'Kenya', 'dial_code' => '+254'],
            ['iso' => 'KW', 'name' => 'Kuwait', 'dial_code' => '+965'],
            ['iso' => 'KG', 'name' => 'Kyrgyzstan', 'dial_code' => '+996'],
            ['iso' => 'LA', 'name' => 'Laos', 'dial_code' => '+856'],
            ['iso' => 'LV', 'name' => 'Latvia', 'dial_code' => '+371'],
            ['iso' => 'LB', 'name' => 'Lebanon', 'dial_code' => '+961'],
            ['iso' => 'LS', 'name' => 'Lesotho', 'dial_code' => '+266'],
            ['iso' => 'LR', 'name' => 'Liberia', 'dial_code' => '+231'],
            ['iso' => 'LY', 'name' => 'Libya', 'dial_code' => '+218'],
            ['iso' => 'LI', 'name' => 'Liechtenstein', 'dial_code' => '+423'],
            ['iso' => 'LT', 'name' => 'Lithuania', 'dial_code' => '+370'],
            ['iso' => 'LU', 'name' => 'Luxembourg', 'dial_code' => '+352'],
            ['iso' => 'MO', 'name' => 'Macao', 'dial_code' => '+853'],
            ['iso' => 'MK', 'name' => 'North Macedonia', 'dial_code' => '+389'],
            ['iso' => 'MG', 'name' => 'Madagascar', 'dial_code' => '+261'],
            ['iso' => 'MW', 'name' => 'Malawi', 'dial_code' => '+265'],
            ['iso' => 'MY', 'name' => 'Malaysia', 'dial_code' => '+60'],
            ['iso' => 'MV', 'name' => 'Maldives', 'dial_code' => '+960'],
            ['iso' => 'ML', 'name' => 'Mali', 'dial_code' => '+223'],
            ['iso' => 'MT', 'name' => 'Malta', 'dial_code' => '+356'],
            ['iso' => 'MR', 'name' => 'Mauritania', 'dial_code' => '+222'],
            ['iso' => 'MU', 'name' => 'Mauritius', 'dial_code' => '+230'],
            ['iso' => 'MX', 'name' => 'Mexico', 'dial_code' => '+52'],
            ['iso' => 'MD', 'name' => 'Moldova', 'dial_code' => '+373'],
            ['iso' => 'MC', 'name' => 'Monaco', 'dial_code' => '+377'],
            ['iso' => 'MN', 'name' => 'Mongolia', 'dial_code' => '+976'],
            ['iso' => 'ME', 'name' => 'Montenegro', 'dial_code' => '+382'],
            ['iso' => 'MA', 'name' => 'Morocco', 'dial_code' => '+212'],
            ['iso' => 'MZ', 'name' => 'Mozambique', 'dial_code' => '+258'],
            ['iso' => 'MM', 'name' => 'Myanmar', 'dial_code' => '+95'],
            ['iso' => 'NA', 'name' => 'Namibia', 'dial_code' => '+264'],
            ['iso' => 'NP', 'name' => 'Nepal', 'dial_code' => '+977'],
            ['iso' => 'NL', 'name' => 'Netherlands', 'dial_code' => '+31'],
            ['iso' => 'NZ', 'name' => 'New Zealand', 'dial_code' => '+64'],
            ['iso' => 'NI', 'name' => 'Nicaragua', 'dial_code' => '+505'],
            ['iso' => 'NE', 'name' => 'Niger', 'dial_code' => '+227'],
            ['iso' => 'NG', 'name' => 'Nigeria', 'dial_code' => '+234'],
            ['iso' => 'NO', 'name' => 'Norway', 'dial_code' => '+47'],
            ['iso' => 'OM', 'name' => 'Oman', 'dial_code' => '+968'],
            ['iso' => 'PK', 'name' => 'Pakistan', 'dial_code' => '+92'],
            ['iso' => 'PA', 'name' => 'Panama', 'dial_code' => '+507'],
            ['iso' => 'PY', 'name' => 'Paraguay', 'dial_code' => '+595'],
            ['iso' => 'PE', 'name' => 'Peru', 'dial_code' => '+51'],
            ['iso' => 'PH', 'name' => 'Philippines', 'dial_code' => '+63'],
            ['iso' => 'PL', 'name' => 'Poland', 'dial_code' => '+48'],
            ['iso' => 'PT', 'name' => 'Portugal', 'dial_code' => '+351'],
            ['iso' => 'QA', 'name' => 'Qatar', 'dial_code' => '+974'],
            ['iso' => 'RO', 'name' => 'Romania', 'dial_code' => '+40'],
            ['iso' => 'RU', 'name' => 'Russia', 'dial_code' => '+7'],
            ['iso' => 'RW', 'name' => 'Rwanda', 'dial_code' => '+250'],
            ['iso' => 'SA', 'name' => 'Saudi Arabia', 'dial_code' => '+966'],
            ['iso' => 'SN', 'name' => 'Senegal', 'dial_code' => '+221'],
            ['iso' => 'RS', 'name' => 'Serbia', 'dial_code' => '+381'],
            ['iso' => 'SL', 'name' => 'Sierra Leone', 'dial_code' => '+232'],
            ['iso' => 'SG', 'name' => 'Singapore', 'dial_code' => '+65'],
            ['iso' => 'SK', 'name' => 'Slovakia', 'dial_code' => '+421'],
            ['iso' => 'SI', 'name' => 'Slovenia', 'dial_code' => '+386'],
            ['iso' => 'SO', 'name' => 'Somalia', 'dial_code' => '+252'],
            ['iso' => 'ZA', 'name' => 'South Africa', 'dial_code' => '+27'],
            ['iso' => 'KR', 'name' => 'South Korea', 'dial_code' => '+82'],
            ['iso' => 'ES', 'name' => 'Spain', 'dial_code' => '+34'],
            ['iso' => 'LK', 'name' => 'Sri Lanka', 'dial_code' => '+94'],
            ['iso' => 'SD', 'name' => 'Sudan', 'dial_code' => '+249'],
            ['iso' => 'SR', 'name' => 'Suriname', 'dial_code' => '+597'],
            ['iso' => 'SE', 'name' => 'Sweden', 'dial_code' => '+46'],
            ['iso' => 'CH', 'name' => 'Switzerland', 'dial_code' => '+41'],
            ['iso' => 'SY', 'name' => 'Syria', 'dial_code' => '+963'],
            ['iso' => 'TW', 'name' => 'Taiwan', 'dial_code' => '+886'],
            ['iso' => 'TJ', 'name' => 'Tajikistan', 'dial_code' => '+992'],
            ['iso' => 'TZ', 'name' => 'Tanzania', 'dial_code' => '+255'],
            ['iso' => 'TH', 'name' => 'Thailand', 'dial_code' => '+66'],
            ['iso' => 'TG', 'name' => 'Togo', 'dial_code' => '+228'],
            ['iso' => 'TN', 'name' => 'Tunisia', 'dial_code' => '+216'],
            ['iso' => 'TR', 'name' => 'Turkey', 'dial_code' => '+90'],
            ['iso' => 'TM', 'name' => 'Turkmenistan', 'dial_code' => '+993'],
            ['iso' => 'UG', 'name' => 'Uganda', 'dial_code' => '+256'],
            ['iso' => 'UA', 'name' => 'Ukraine', 'dial_code' => '+380'],
            ['iso' => 'AE', 'name' => 'United Arab Emirates', 'dial_code' => '+971'],
            ['iso' => 'GB', 'name' => 'United Kingdom', 'dial_code' => '+44'],
            ['iso' => 'US', 'name' => 'United States', 'dial_code' => '+1'],
            ['iso' => 'UY', 'name' => 'Uruguay', 'dial_code' => '+598'],
            ['iso' => 'UZ', 'name' => 'Uzbekistan', 'dial_code' => '+998'],
            ['iso' => 'VE', 'name' => 'Venezuela', 'dial_code' => '+58'],
            ['iso' => 'VN', 'name' => 'Vietnam', 'dial_code' => '+84'],
            ['iso' => 'YE', 'name' => 'Yemen', 'dial_code' => '+967'],
            ['iso' => 'ZM', 'name' => 'Zambia', 'dial_code' => '+260'],
            ['iso' => 'ZW', 'name' => 'Zimbabwe', 'dial_code' => '+263'],
        ];

        return $countries;
    }
}

if (!function_exists('peracrm_phone_country_dial_codes')) {
    function peracrm_phone_country_dial_codes(): array
    {
        static $codes = null;
        if (is_array($codes)) {
            return $codes;
        }

        $codes = [];
        foreach (peracrm_phone_country_dataset() as $country) {
            $dial = isset($country['dial_code']) ? sanitize_text_field((string) $country['dial_code']) : '';
            if ($dial === '') {
                continue;
            }
            $codes[$dial] = $dial;
        }

        $codes = array_values($codes);
        usort(
            $codes,
            static function (string $left, string $right): int {
                return strlen($right) <=> strlen($left);
            }
        );

        return $codes;
    }
}


if (!function_exists('peracrm_phone_dial_code_options')) {
    function peracrm_phone_dial_code_options(): array
    {
        static $options = null;
        if (is_array($options)) {
            return $options;
        }

        $grouped = [];
        foreach (peracrm_phone_country_dataset() as $row) {
            if (!is_array($row)) {
                continue;
            }

            $dial = isset($row['dial_code']) ? sanitize_text_field((string) $row['dial_code']) : '';
            $name = isset($row['name']) ? sanitize_text_field((string) $row['name']) : '';
            $iso = isset($row['iso']) ? strtoupper(sanitize_key((string) $row['iso'])) : '';
            if ($dial === '') {
                continue;
            }

            if (!isset($grouped[$dial])) {
                $grouped[$dial] = [
                    'dial_code' => $dial,
                    'iso' => $iso,
                    'names' => [],
                    'search' => [],
                ];
            }

            if ($name !== '') {
                $grouped[$dial]['names'][$name] = $name;
                $grouped[$dial]['search'][$name] = $name;
            }

            if ($iso !== '') {
                $grouped[$dial]['search'][$iso] = $iso;
            }
        }

        $options = [];
        foreach ($grouped as $dial => $row) {
            $names = array_values($row['names']);
            sort($names, SORT_NATURAL | SORT_FLAG_CASE);

            $label = $dial;
            if (!empty($names)) {
                $primary = $names[0];
                $label = $primary . ' ' . $dial;
                if (count($names) > 1) {
                    $label .= sprintf(' (+%d countries)', count($names) - 1);
                }
            }

            $options[] = [
                'dial_code' => $dial,
                'iso' => (string) $row['iso'],
                'label' => $label,
                'search_tokens' => implode(' ', array_values($row['search'])),
            ];
        }

        usort(
            $options,
            static function (array $left, array $right): int {
                return strcmp((string) ($left['dial_code'] ?? ''), (string) ($right['dial_code'] ?? ''));
            }
        );

        return $options;
    }
}
