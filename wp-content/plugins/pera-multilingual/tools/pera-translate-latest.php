<?php

$limit     = 10;
$languages = array( 'zh', 'ar', 'de' );
$dry_run   = false;

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

$candidates = array();

foreach ( $posts as $post ) {
    $needs_translation = false;
    $statuses = array();

    foreach ( $languages as $language ) {
        $status = $status_api->get( $post->ID, $language );
        $statuses[ $language ] = $status;

        if ( ! $status['complete'] ) {
            $needs_translation = true;
        }
    }

    if ( $needs_translation ) {
        $candidates[] = array(
            'post'     => $post,
            'statuses' => $statuses,
        );
    }

    if ( count( $candidates ) >= $limit ) {
        break;
    }
}

if ( ! $candidates ) {
    echo "No incomplete translations found.\n";
    return;
}

echo "Selected posts:\n\n";

foreach ( $candidates as $candidate ) {
    $post = $candidate['post'];

    echo "#{$post->ID} - {$post->post_title}\n";

    foreach ( $languages as $language ) {
        $status = $candidate['statuses'][ $language ];

        echo "  " . strtoupper( $language ) . ': ';

        if ( $status['complete'] ) {
            echo "COMPLETE\n";
            continue;
        }

        echo "{$status['current']}/{$status['applicable']} current";

        if ( $status['missing'] ) {
            echo ' | missing: ' . implode( ', ', $status['missing'] );
        }

        if ( $status['stale'] ) {
            echo ' | stale: ' . implode( ', ', $status['stale'] );
        }

        echo "\n";
    }

    echo "\n";
}

if ( $dry_run ) {
    echo "DRY RUN ONLY - nothing translated.\n";
    return;
}

/*
 * Translation phase.
 */
foreach ( $candidates as $candidate ) {
    $post = $candidate['post'];

    echo "\n==================================================\n";
    echo "POST {$post->ID}: {$post->post_title}\n";
    echo "==================================================\n";
    $sources = $status_api->applicable_sources( $post->ID, $post->post_type );

    foreach ( $languages as $language ) {
        $status = $status_api->get( $post->ID, $language );

        if ( $status['complete'] ) {
            echo strtoupper( $language ) . ": already complete - skipped\n";
            continue;
        }

        $needed = array_unique(
            array_merge( $status['missing'], $status['stale'] )
        );

        /*
         * Keep expensive/important body translation first.
         */
        $priority = array(
            'post_content',
            'post_title',
            'post_excerpt',
            'meta:seo_title',
            'meta:seo_meta_description',
            'meta:seo_faq_v2',
        );

        usort( $needed, static function ( $a, $b ) use ( $priority ) {
            $pa = array_search( $a, $priority, true );
            $pb = array_search( $b, $priority, true );

            $pa = false === $pa ? 999 : $pa;
            $pb = false === $pb ? 999 : $pb;

            return $pa <=> $pb;
        } );

        echo "\n" . strtoupper( $language ) . ":\n";

        foreach ( $needed as $field ) {
            $source = isset( $sources[ $field ] ) ? $sources[ $field ] : '';

            if ( ! is_string( $source ) || '' === trim( $source ) ) {
                echo "  SKIP {$field} (empty source)\n";
                continue;
            }

            echo "  Translating {$field}... ";

            $result = $translator->translate_and_store(
                'post',
                $post->ID,
                $field,
                $language,
                $source
            );

            /*
             * One retry for transient provider errors.
             */
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
                    $post->ID,
                    $field,
                    $language,
                    $source
                );
            }

            if ( is_wp_error( $result ) ) {
                echo "FAILED [" . $result->get_error_code() . "]\n";
            } else {
                echo "OK\n";
            }
        }
    }
}

wp_cache_flush();

echo "\nFinished.\n";
