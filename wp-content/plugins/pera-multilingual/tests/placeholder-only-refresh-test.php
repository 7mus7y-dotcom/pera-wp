<?php
/** Prose validation and preservation through the real translator/storage path. */
require __DIR__ . '/storage-test.php';
$source = '<p>Hello {{name}}</p>';
$protected = $translator->protect( $source );
$only_tokens = implode( '', array_keys( $protected['tokens'] ) );
foreach ( array( $only_tokens, $only_tokens . '   ', $only_tokens . '<p></p>', $only_tokens . '&nbsp;', $only_tokens . '...' ) as $response ) {
    $result = $translator->restore( $response, $protected['tokens'], $source );
    expect_same( true, is_wp_error( $result ), 'placeholder-only or empty HTML response rejected' );
    expect_same( 'pera_ml_empty_translation', $result->get_error_code(), 'missing prose reports empty translation' );
}
foreach ( array( 'Да', 'نعم', '好', 'İyi', 'I', '1' ) as $prose ) {
    $response = str_replace( 'Hello', $prose, $protected['text'] );
    expect_same( str_replace( 'Hello', $prose, $source ), $translator->restore( $response, $protected['tokens'], $source ), 'short Unicode translation accepted' );
}
foreach ( array( '<p></p>', '<img src="/image.jpg">', '{{name}}', '%s', 'https://example.com/', '<p>{{name}} $400,000</p>' ) as $token_source ) {
    $protected_only = $translator->protect( $token_source );
    expect_same( $token_source, $translator->restore( $protected_only['text'], $protected_only['tokens'], $token_source ), 'source without prose allows token-only response' );
}
foreach ( array( str_replace( array_keys( $protected['tokens'] )[0], '', $protected['text'] ), $protected['text'] . array_keys( $protected['tokens'] )[0], $protected['text'] . 'PERAMLPROTECTED999TOKEN' ) as $invalid_structure ) {
    $result = $translator->restore( $invalid_structure, $protected['tokens'], $source );
    expect_same( 'pera_ml_structure_changed', $result->get_error_code(), 'existing token structure validation preserved' );
}
foreach ( array( 'current', 'stale' ) as $status ) {
    $storage->put( 'post', 201, 'post_title', 'de', 'Old source', 'Letzte gültige Übersetzung' );
    $wpdb->rows['post|201|post_title|de']['status'] = $status;
    $before_refresh = $storage->get( 'post', 201, 'post_title', 'de', $source );
    $queries_before = count( $wpdb->queries );
    $provider->response = $only_tokens;
    $result = $translator->translate_and_store( 'post', 201, 'post_title', 'de', $source, 'mock' );
    expect_same( true, is_wp_error( $result ), 'placeholder-only refresh fails before write' );
    expect_same( $before_refresh, $storage->get( 'post', 201, 'post_title', 'de', $source ), 'rejection preserves complete cached translation and status' );
    expect_same( $queries_before, count( $wpdb->queries ), 'rejected refresh performs no database write' );
}
echo "Pera ML placeholder-only refresh tests passed\n";

// Empty HTML is also invalid when a plain source supplied no protected tokens.
$provider->response = '<p></p>';
$result = $translator->translate_and_store( 'post', 201, 'post_title', 'de', 'Hello', 'mock' );
expect_same( 'pera_ml_empty_translation', $result->get_error_code(), 'empty HTML cannot replace plain prose' );
expect_same( $before_refresh, $storage->get( 'post', 201, 'post_title', 'de', $source ), 'empty HTML rejection preserves the previous row' );
$provider->response = 'İyi';
expect_same( 'İyi', $translator->translate_and_store( 'post', 201, 'post_title', 'de', 'Hello', 'mock' ), 'valid short prose can replace previous translation' );
expect_same( 'current', $storage->get( 'post', 201, 'post_title', 'de', 'Hello' )['status'], 'valid prose refresh becomes current' );
$token_source = '<p>{{name}}</p>';
$provider->response = $translator->protect( $token_source )['text'];
expect_same( $token_source, $translator->translate_and_store( 'post', 202, 'post_title', 'de', $token_source, 'mock' ), 'legitimate token-only refresh is stored' );
