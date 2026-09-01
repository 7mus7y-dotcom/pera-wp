<?php
/** Deterministic visitor-facing dates for Pera's routed languages. */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'pera_ml_format_property_date' ) ) {
	function pera_ml_format_property_date( $timestamp, ?string $language = null ): string {
		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) return '';
		$language = $language ?: ( function_exists( 'pera_ml_current_language' ) ? pera_ml_current_language() : 'en' );
		$year = wp_date( 'Y', $timestamp );
		$month = (int) wp_date( 'n', $timestamp );
		$day = (int) wp_date( 'j', $timestamp );

		if ( 'zh' === $language ) return sprintf( '%s年%d月%d日', $year, $month, $day );

		$months = array(
			'en' => array( 1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ),
			'de' => array( 1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember' ),
			'ar' => array( 1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر' ),
		);
		$language = isset( $months[ $language ] ) ? $language : 'en';
		return sprintf( '%d %s %s', $day, $months[ $language ][ $month ], $year );
	}
}
