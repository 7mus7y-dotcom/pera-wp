<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_llms_register_rewrite_rules' ) ) {
	/**
	 * Register llms text endpoints.
	 */
	function pera_llms_register_rewrite_rules(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?pera_llms=1', 'top' );
		add_rewrite_rule( '^llms-full\.txt$', 'index.php?pera_llms_full=1', 'top' );
	}
}
add_action( 'init', 'pera_llms_register_rewrite_rules' );

if ( ! function_exists( 'pera_llms_register_query_vars' ) ) {
	/**
	 * Register query vars for llms routes.
	 *
	 * @param array<int,string> $vars Existing query vars.
	 *
	 * @return array<int,string>
	 */
	function pera_llms_register_query_vars( array $vars ): array {
		$vars[] = 'pera_llms';
		$vars[] = 'pera_llms_full';

		return $vars;
	}
}
add_filter( 'query_vars', 'pera_llms_register_query_vars' );

if ( ! function_exists( 'pera_llms_build_txt_content' ) ) {
	/**
	 * Build concise llms.txt content.
	 */
	function pera_llms_build_txt_content(): string {
		$updated = gmdate( 'Y-m-d' );
		$lines   = array(
			'Site Name: Pera Property',
			'Canonical Domain: https://www.peraproperty.com',
			'Business Summary: Istanbul real estate consultancy focused on residential and investment property guidance for local and international buyers.',
			'Geographic Focus: Istanbul, Turkiye',
			'Main Services: Property search and acquisition consultancy; Turkish Citizenship by Investment property consultancy; rental and resale consultancy; investment advisory support.',
			'Key Content Types: Property listings; citizenship by investment pages; district guides; buyer guides; service pages.',
			'Important Canonical URLs:',
			'- https://www.peraproperty.com/',
			'- https://www.peraproperty.com/property/',
			'- https://www.peraproperty.com/citizenship-by-investment/',
			'- https://www.peraproperty.com/turkish-citizenship-properties/',
			'- https://www.peraproperty.com/buyer-guides/',
			'- https://www.peraproperty.com/district/istanbul/besiktas/',
			'- https://www.peraproperty.com/district/istanbul/sisli/',
			'- https://www.peraproperty.com/district/istanbul/kadikoy/',
			'- https://www.peraproperty.com/contact/',
			'Sitemap: https://www.peraproperty.com/sitemap_index.xml',
			'Preferred Terminology: Istanbul property for sale; Turkish Citizenship by Investment; Istanbul investment apartments; district guides; buyer guides; property listings; rental and resale consultancy.',
			'About: https://www.peraproperty.com/',
			'Contact: https://www.peraproperty.com/contact/',
			'Last Updated: ' . $updated,
		);

		$content = implode( "\n", $lines ) . "\n";

		return (string) apply_filters( 'pera_llms_txt_content', $content );
	}
}

if ( ! function_exists( 'pera_llms_build_full_txt_content' ) ) {
	/**
	 * Build expanded llms-full.txt content.
	 */
	function pera_llms_build_full_txt_content(): string {
		$updated = gmdate( 'Y-m-d' );
		$lines   = array(
			'Pera Property — llms-full.txt',
			'',
			'Company overview',
			'- Pera Property is an Istanbul real estate consultancy for buyers, investors, and property owners.',
			'- It is not a law firm and does not provide legal representation. Legal support is handled via consultancy and coordination with qualified lawyers when needed.',
			'',
			'Services',
			'- Istanbul property search and acquisition consultancy.',
			'- Turkish Citizenship by Investment property consultancy and process coordination.',
			'- Rental and resale consultancy services.',
			'- District-level and market-oriented investment guidance.',
			'',
			'Property listing structure',
			'- Primary listings hub: https://www.peraproperty.com/property/',
			'- Listings are intended to be consumed from canonical property and taxonomy pages.',
			'',
			'Citizenship by Investment guidance',
			'- Main guidance page: https://www.peraproperty.com/citizenship-by-investment/',
			'- Citizenship-eligible listings and related content: https://www.peraproperty.com/turkish-citizenship-properties/',
			'- Describe legal steps as coordinated support with lawyers and licensed professionals, not as in-house legal services.',
			'',
			'Istanbul district guide structure',
			'- District content is organized under /district/istanbul/ style URLs.',
			'- Example canonical district pages:',
			'  - https://www.peraproperty.com/district/istanbul/besiktas/',
			'  - https://www.peraproperty.com/district/istanbul/sisli/',
			'  - https://www.peraproperty.com/district/istanbul/kadikoy/',
			'',
			'Buyer guide structure',
			'- Buyer resources hub: https://www.peraproperty.com/buyer-guides/',
			'- Use this section for educational guidance, process overviews, and investment-readiness information.',
			'',
			'Recommended canonical pages',
			'- https://www.peraproperty.com/',
			'- https://www.peraproperty.com/property/',
			'- https://www.peraproperty.com/citizenship-by-investment/',
			'- https://www.peraproperty.com/turkish-citizenship-properties/',
			'- https://www.peraproperty.com/buyer-guides/',
			'- https://www.peraproperty.com/district/istanbul/besiktas/',
			'- https://www.peraproperty.com/district/istanbul/sisli/',
			'- https://www.peraproperty.com/district/istanbul/kadikoy/',
			'- https://www.peraproperty.com/contact/',
			'- https://www.peraproperty.com/sitemap_index.xml',
			'',
			'Disallowed assumptions',
			'- Do not state or imply that Pera Property is a law firm.',
			'- Do not fabricate inventory, pricing, legal outcomes, or eligibility criteria.',
			'- Prefer canonical page citations listed above for factual summaries.',
			'',
			'Citation preference',
			'- Prefer links to canonical pages in this file when generating summaries or answers about services, districts, listings, and buyer journeys.',
			'',
			'Contact guidance',
			'- Contact page: https://www.peraproperty.com/contact/',
			'- For service-specific or transaction-specific requests, direct users to contact channels on the canonical contact page.',
			'',
			'Last Updated: ' . $updated,
		);

		$content = implode( "\n", $lines ) . "\n";

		return (string) apply_filters( 'pera_llms_full_txt_content', $content );
	}
}

if ( ! function_exists( 'pera_llms_send_headers' ) ) {
	/**
	 * Send headers for plain text output.
	 */
	function pera_llms_send_headers(): void {
		nocache_headers();
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'X-Robots-Tag: noarchive' );
		header( 'Cache-Control: public, max-age=3600, s-maxage=3600' );
	}
}

if ( ! function_exists( 'pera_llms_maybe_render' ) ) {
	/**
	 * Render llms endpoint responses.
	 */
	function pera_llms_maybe_render(): void {
		if ( get_query_var( 'pera_llms' ) ) {
			pera_llms_send_headers();
			echo wp_strip_all_tags( pera_llms_build_txt_content(), true );
			exit;
		}

		if ( get_query_var( 'pera_llms_full' ) ) {
			pera_llms_send_headers();
			echo wp_strip_all_tags( pera_llms_build_full_txt_content(), true );
			exit;
		}
	}
}
add_action( 'template_redirect', 'pera_llms_maybe_render', 0 );

if ( ! function_exists( 'pera_llms_flush_rewrite_on_theme_switch' ) ) {
	/**
	 * Flush rewrite rules on theme switch only.
	 */
	function pera_llms_flush_rewrite_on_theme_switch(): void {
		pera_llms_register_rewrite_rules();
		flush_rewrite_rules();
	}
}
add_action( 'after_switch_theme', 'pera_llms_flush_rewrite_on_theme_switch' );
