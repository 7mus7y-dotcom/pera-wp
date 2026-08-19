<?php
defined( 'ABSPATH' ) || exit;

final class Pera_ML_OpenAI_Provider implements Pera_ML_Provider_Interface {
	public function id() { return 'openai'; }
	public function translate( $source, array $context ) {
		$key = defined( 'PERA_ML_OPENAI_API_KEY' ) ? PERA_ML_OPENAI_API_KEY : get_option( 'pera_ml_openai_api_key', '' );
		if ( ! is_string( $key ) || '' === trim( $key ) ) return new WP_Error( 'pera_ml_missing_key', __( 'OpenAI API key is not configured.', 'pera-multilingual' ) );
		$model = defined( 'PERA_ML_OPENAI_MODEL' ) ? PERA_ML_OPENAI_MODEL : get_option( 'pera_ml_openai_model', 'gpt-4.1-mini' );
		$target = isset( $context['target_name'] ) ? $context['target_name'] : $context['target_language'];
		$system = 'You are a professional property and real-estate translator. Translate into ' . $target . '. Preserve every HTML tag and attribute, Gutenberg comment, shortcode, URL, ID, class, price, currency, measurement, phone number, email, placeholder, and factual claim exactly. Do not add or remove claims. Return only the translation.';
		if ( ! empty( $context['instructions'] ) ) $system .= "\nLanguage instructions: " . $context['instructions'];
		if ( ! empty( $context['glossary'] ) ) $system .= "\nTerminology and protected-term rules:\n" . $context['glossary'];
		$response = wp_safe_remote_post( 'https://api.openai.com/v1/chat/completions', array( 'timeout' => 60, 'headers' => array( 'Authorization' => 'Bearer ' . trim( $key ), 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'model' => sanitize_text_field( $model ), 'temperature' => 0, 'messages' => array( array( 'role' => 'system', 'content' => $system ), array( 'role' => 'user', 'content' => $source ) ) ) ) ) );
		if ( is_wp_error( $response ) ) return $response;
		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || empty( $body['choices'][0]['message']['content'] ) ) return new WP_Error( 'pera_ml_provider_error', sprintf( __( 'Translation provider returned HTTP %d.', 'pera-multilingual' ), $code ) );
		return (string) $body['choices'][0]['message']['content'];
	}
}
