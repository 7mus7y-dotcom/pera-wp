<?php

function pera_currency_get_supported(): array {
	return Pera_Currency_Rates::supported();
}

function pera_currency_get_selected(): string {
	return Pera_Currency_Preference::selected();
}

function pera_currency_get_rate( string $currency, string $base = 'USD' ): ?float {
	$currency = strtoupper( $currency );
	if ( 'USD' !== strtoupper( $base ) || ! isset( Pera_Currency_Rates::supported()[ $currency ] ) ) return null;
	if ( 'USD' === $currency ) return 1.0;
	$snapshot = Pera_Currency_Rates::snapshot();
	return in_array( Pera_Currency_Rates::state( $snapshot ), array( 'fresh', 'stale' ), true ) ? (float) $snapshot['rates'][ $currency ] : null;
}

function pera_currency_convert( $amount, ?string $currency = null, array $options = array() ) {
	if ( ! Pera_Currency_Formatter::valid_amount( $amount ) ) return null;
	$requested = strtoupper( $currency ?: pera_currency_get_selected() );
	if ( ! isset( Pera_Currency_Rates::supported()[ $requested ] ) ) return null;
	$rate     = pera_currency_get_rate( $requested );
	$fallback = null === $rate && 'USD' !== $requested;
	$effective = $fallback ? 'USD' : $requested;
	$raw       = (float) $amount * ( $fallback ? 1.0 : $rate );
	$increment = isset( $options['rounding'] ) && is_numeric( $options['rounding'] ) && $options['rounding'] > 0
		? (float) $options['rounding'] : (float) Pera_Currency_Rates::supported()[ $effective ]['rounding'];
	$result = array( 'amount' => Pera_Currency_Formatter::round_half_up( $raw, $increment ), 'raw' => $raw, 'currency' => $effective, 'requested_currency' => $requested, 'fallback' => $fallback, 'snapshot_id' => $fallback || 'USD' === $effective ? null : Pera_Currency_Rates::snapshot()['snapshot_id'] );
	return ! empty( $options['details'] ) ? $result : $result['amount'];
}

function pera_currency_format( $amount_usd, ?string $currency = null, array $options = array() ): string {
	$options['details'] = true;
	$result = pera_currency_convert( $amount_usd, $currency, $options );
	return is_array( $result ) ? Pera_Currency_Formatter::text( $result['amount'], $result['currency'] ) : '';
}

function pera_currency_format_range( $min_usd, $max_usd = null, ?string $currency = null, array $options = array() ): array {
	$options['details'] = true;
	$has_max = null !== $max_usd && '' !== $max_usd;
	$min     = pera_currency_convert( $min_usd, $currency, $options );
	$max     = $has_max ? pera_currency_convert( $max_usd, $currency, $options ) : null;
	if ( ! is_array( $min ) || ( $has_max && ! is_array( $max ) ) || ( $has_max && (float) $max_usd < (float) $min_usd ) ) {
		return array( 'min' => '', 'max' => '', 'currency' => 'USD', 'snapshot_id' => null, 'fallback' => true, 'valid' => false );
	}
	if ( null !== $max && $max['amount'] === $min['amount'] ) $max = null;
	return array( 'min' => Pera_Currency_Formatter::text( $min['amount'], $min['currency'] ), 'max' => $max ? Pera_Currency_Formatter::text( $max['amount'], $max['currency'] ) : '', 'currency' => $min['currency'], 'snapshot_id' => $min['snapshot_id'], 'fallback' => $min['fallback'], 'valid' => true );
}
