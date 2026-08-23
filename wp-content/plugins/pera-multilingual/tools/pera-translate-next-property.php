<?php

$languages = array( 'zh', 'ar', 'de' );

$plugin     = Pera_ML_Plugin::instance();
$status_api = $plugin->status();
$translator = $plugin->translator();

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

foreach ( $properties as $property ) {
    $needs_translation = false;

    foreach ( $languages as $language ) {
        $status = $status_api->get( $property->ID, $language, 'property' );

        if ( ! $status['complete'] ) {
            $needs_translation = true;
            break;
        }
    }

    if ( ! $needs_translation ) {
        continue;
    }

    echo "PROPERTY {$property->ID}: {$property->post_title}\n";

    $sources = $status_api->applicable_sources( $property->ID, 'property' );

    foreach ( $languages as $language ) {
        $status = $status_api->get( $property->ID, $language, 'property' );

        if ( $status['complete'] ) {
            echo strtoupper( $language ) . ": COMPLETE\n";
            continue;
        }

        $fields = array_values( array_unique(
            array_merge( $status['missing'], $status['stale'] )
        ) );

        if ( false !== ( $position = array_search( 'post_content', $fields, true ) ) ) {
            unset( $fields[ $position ] );
            array_unshift( $fields, 'post_content' );
        }

        foreach ( $fields as $field ) {
            if ( ! array_key_exists( $field, $sources ) ) {
                echo strtoupper( $language ) . " {$field}... SKIP [source unavailable]\n";
                continue;
            }

            $source = $sources[ $field ];

            if ( ! is_string( $source ) || '' === trim( $source ) ) {
                echo strtoupper( $language ) . " {$field}... SKIP [empty source]\n";
                continue;
            }

            echo strtoupper( $language ) . " {$field}... ";

            $result = $translator->translate_and_store(
                'post',
                $property->ID,
                $field,
                $language,
                $source
            );

            if (
                is_wp_error( $result ) &&
                in_array(
                    $result->get_error_code(),
                    array(
                        'pera_ml_rate_limited',
                        'pera_ml_provider_transient',
                        'http_request_failed',
                    ),
                    true
                )
            ) {
                echo "transient failure [" . $result->get_error_code() . "] - retrying... ";
                sleep( 5 );

                $result = $translator->translate_and_store(
                    'post',
                    $property->ID,
                    $field,
                    $language,
                    $source
                );
            }

            echo is_wp_error( $result )
                ? "FAILED [" . $result->get_error_code() . "]\n"
                : "OK\n";
        }
    }

    wp_cache_flush();
    return;
}

echo "ALL PUBLISHED PROPERTIES COMPLETE\n";
wp_cache_flush();
