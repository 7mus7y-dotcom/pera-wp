<?php

$languages = array( 'zh', 'ar', 'de' );

$plugin     = Pera_ML_Plugin::instance();
$status_api = $plugin->status();
$translator = $plugin->translator();

$posts = get_posts( array(
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => 100,
    'orderby'        => 'date',
    'order'          => 'DESC',
) );

foreach ( $posts as $post ) {
    $needs_translation = false;

    foreach ( $languages as $language ) {
        if ( ! $status_api->get( $post->ID, $language )['complete'] ) {
            $needs_translation = true;
            break;
        }
    }

    if ( ! $needs_translation ) {
        continue;
    }

    echo "POST {$post->ID}: {$post->post_title}\n";
    $sources = $status_api->applicable_sources( $post->ID, $post->post_type );

    foreach ( $languages as $language ) {
        $status = $status_api->get( $post->ID, $language );

        if ( $status['complete'] ) {
            echo strtoupper( $language ) . ": COMPLETE\n";
            continue;
        }

        $fields = array_unique(
            array_merge( $status['missing'], $status['stale'] )
        );

        foreach ( $fields as $field ) {
            $source = isset( $sources[ $field ] ) ? $sources[ $field ] : '';

            if ( ! is_string( $source ) || '' === trim( $source ) ) {
                continue;
            }

            echo strtoupper( $language ) . " {$field}... ";

            $result = $translator->translate_and_store(
                'post',
                $post->ID,
                $field,
                $language,
                $source
            );

            echo is_wp_error( $result )
                ? "FAILED [" . $result->get_error_code() . "]\n"
                : "OK\n";
        }
    }

    break;
}

wp_cache_flush();
