<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Shared read-only property FAQ parser for frontend SEO and WP-CLI audits. */

if ( ! function_exists( 'pera_property_normalize_whitespace' ) ) {
  function pera_property_normalize_whitespace( string $value ): string {
    $value = wp_strip_all_tags( $value );
    $value = preg_replace( '/\s+/u', ' ', $value );
    return trim( (string) $value );
  }
}

if ( ! function_exists( 'pera_property_get_faq_items' ) ) {
  /**
   * Return property FAQ rows from the editable "Question|Answer" field.
   *
   * @param int $post_id Property post ID.
   * @return array<int,array{question:string,answer:string}>
   */
  function pera_property_get_faq_items( int $post_id ): array {
    if ( $post_id < 1 ) {
      return array();
    }

    $faq_text = '';
    if ( function_exists( 'get_field' ) ) {
      $faq_text = (string) get_field( 'property_faq_text', $post_id );
    }

    if ( $faq_text === '' ) {
      $faq_text = (string) get_post_meta( $post_id, 'property_faq_text', true );
    }

    if ( trim( $faq_text ) === '' ) {
      return array();
    }

    $faq_items = array();
    $faq_lines = preg_split( '/\r\n|\r|\n/', $faq_text );

    foreach ( $faq_lines as $faq_line ) {
      $faq_line = trim( (string) $faq_line );

      if ( $faq_line === '' || strpos( $faq_line, '|' ) === false ) {
        continue;
      }

      list( $question, $answer ) = array_map( 'trim', explode( '|', $faq_line, 2 ) );
      $question = pera_property_normalize_whitespace( $question );
      $answer   = pera_property_normalize_whitespace( $answer );

      if ( $question === '' || $answer === '' ) {
        continue;
      }

      $faq_items[] = array(
        'question' => $question,
        'answer'   => $answer,
      );
    }

    return $faq_items;
  }
}

