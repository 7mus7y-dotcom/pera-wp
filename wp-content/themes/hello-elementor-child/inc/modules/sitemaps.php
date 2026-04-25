<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_sitemaps_is_noindex_flag' ) ) {
	/**
	 * Detect common robots noindex flag values.
	 *
	 * @param mixed $value Meta value.
	 */
	function pera_sitemaps_is_noindex_flag( $value ): bool {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( pera_sitemaps_is_noindex_flag( $item ) ) {
					return true;
				}
			}

			return false;
		}

		if ( is_object( $value ) ) {
			return false;
		}

		$normalized = strtolower( trim( (string) $value ) );

		if ( '' === $normalized ) {
			return false;
		}

		return in_array( $normalized, array( '1', 'true', 'yes', 'noindex' ), true );
	}
}

if ( ! function_exists( 'pera_sitemaps_is_post_indexable' ) ) {
	/**
	 * Check if a post is indexable according to common SEO noindex meta keys.
	 */
	function pera_sitemaps_is_post_indexable( int $post_id ): bool {
		$noindex_keys = array(
			'_yoast_wpseo_meta-robots-noindex',
			'rank_math_robots',
			'_aioseo_robots_noindex',
			'_seopress_robots_index',
		);

		foreach ( $noindex_keys as $meta_key ) {
			$meta = get_post_meta( $post_id, $meta_key, true );
			if ( pera_sitemaps_is_noindex_flag( $meta ) ) {
				return false;
			}
		}

		return true;
	}
}

if ( ! function_exists( 'pera_sitemaps_is_term_indexable' ) ) {
	/**
	 * Check if a term is indexable according to common SEO noindex meta keys.
	 */
	function pera_sitemaps_is_term_indexable( int $term_id ): bool {
		$noindex_keys = array(
			'_yoast_wpseo_noindex',
			'rank_math_robots',
			'_aioseo_noindex',
			'_seopress_robots_index',
		);

		foreach ( $noindex_keys as $meta_key ) {
			$meta = get_term_meta( $term_id, $meta_key, true );
			if ( pera_sitemaps_is_noindex_flag( $meta ) ) {
				return false;
			}
		}

		return true;
	}
}

add_filter(
	'wp_sitemaps_post_types',
	static function ( array $post_types ): array {
		$allowed = array( 'post', 'page', 'property' );

		return array_intersect_key( $post_types, array_flip( $allowed ) );
	}
);

add_filter(
	'wp_sitemaps_taxonomies',
	static function ( array $taxonomies ): array {
		$allowed = array( 'district', 'region', 'property_type' );

		return array_intersect_key( $taxonomies, array_flip( $allowed ) );
	}
);

add_filter(
	'wp_sitemaps_users_pre_url_list',
	static function ( $url_list ) {
		return array();
	}
);

add_filter(
	'wp_sitemaps_posts_entry',
	static function ( $entry, WP_Post $post ) {
		if ( ! pera_sitemaps_is_post_indexable( (int) $post->ID ) ) {
			return false;
		}

		return $entry;
	},
	10,
	2
);

add_filter(
	'wp_sitemaps_taxonomies_entry',
	static function ( $entry, WP_Term $term ) {
		if ( $term->count < 1 ) {
			return false;
		}

		if ( ! pera_sitemaps_is_term_indexable( (int) $term->term_id ) ) {
			return false;
		}

		return $entry;
	},
	10,
	2
);

add_filter(
	'wp_sitemaps_taxonomies_query_args',
	static function ( array $args ): array {
		$args['hide_empty'] = true;

		return $args;
	}
);
