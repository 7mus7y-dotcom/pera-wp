<?php

$languages = array( 'zh', 'ar', 'de' );

$plugin     = Pera_ML_Plugin::instance();
$status_api = $plugin->status();

$properties = get_posts( array(
    'post_type'              => 'property',
    'post_status'            => 'publish',
    'posts_per_page'         => -1,
    'orderby'                => 'date',
    'order'                  => 'DESC',
    'no_found_rows'          => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => false,
) );

$stats = array();

foreach ( $languages as $language ) {
    $stats[ $language ] = array(
        'complete'   => 0,
        'incomplete' => 0,
        'missing'    => 0,
        'stale'      => 0,
    );
}

$complete_all   = 0;
$incomplete_any = 0;

foreach ( $properties as $property ) {
    $all_complete = true;

    foreach ( $languages as $language ) {
        $status = $status_api->get( $property->ID, $language, 'property' );

        if ( $status['complete'] ) {
            $stats[ $language ]['complete']++;
        } else {
            $stats[ $language ]['incomplete']++;
            $all_complete = false;

            if ( ! empty( $status['missing'] ) ) {
                $stats[ $language ]['missing']++;
            }

            if ( ! empty( $status['stale'] ) ) {
                $stats[ $language ]['stale']++;
            }
        }
    }

    if ( $all_complete ) {
        $complete_all++;
    } else {
        $incomplete_any++;
    }
}

echo "\nPERA MULTILINGUAL PROPERTY TRANSLATION STATUS\n";
echo "=============================================\n";
echo "Published properties: " . count( $properties ) . "\n\n";

foreach ( $languages as $language ) {
    echo strtoupper( $language ) . "\n";
    echo "  Complete:   " . $stats[ $language ]['complete'] . "\n";
    echo "  Incomplete: " . $stats[ $language ]['incomplete'] . "\n";
    echo "  With missing fields: " . $stats[ $language ]['missing'] . "\n";
    echo "  With stale fields:   " . $stats[ $language ]['stale'] . "\n\n";
}

echo "ALL LANGUAGES\n";
echo "  Fully translated:            {$complete_all}\n";
echo "  Still requiring translation: {$incomplete_any}\n";
echo "=============================================\n";
