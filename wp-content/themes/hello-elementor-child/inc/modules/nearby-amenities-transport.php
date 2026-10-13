<?php
/** Classification v2: operational facilities and direct, typed route evidence only. */
if ( ! defined( 'ABSPATH' ) ) exit;

function pera_nearby_transport_groups() { return array( 'metro', 'tram', 'funicular', 'marmaray', 'metrobus', 'rail', 'bus', 'ferry' ); }
function pera_nearby_radius( $group ) { return in_array( $group, array( 'marmaray', 'metrobus' ), true ) ? 500 : 3500; }
/** Old generations contain no route proof. Downgrade uncertain BRT; never invent line refs. */
function pera_nearby_legacy_transport( array $place ) {
    if ( ( $place['group'] ?? '' ) === 'metrobus' ) $place['group'] = 'bus';
    if ( ( $place['group'] ?? '' ) === 'rail' && preg_match( '/^(?:Beyoğlu[ -]*)?Tünel(?:[ -]*(?:Beyoğlu|İstasyonu|Station))?$/iuD', $place['names']['name'] ?? '' ) ) $place['group'] = 'funicular';
    return $place;
}
function pera_nearby_transport_tags( array $tags ) {
    $keys = array( 'type', 'railway', 'public_transport', 'highway', 'amenity', 'station', 'subway', 'tram', 'funicular', 'train', 'bus', 'network', 'network:short', 'operator', 'operator:short', 'brand', 'ref', 'route', 'metrobus', 'busway', 'railway:traffic_mode', 'status', 'state', 'proposed', 'planned', 'construction', 'disused', 'abandoned', 'demolished', 'razed', 'removed', 'operational', 'opening_date' );
    return array_filter( $tags, function ( $value, $key ) use ( $keys ) {
        return is_string( $value ) && ( in_array( $key, $keys, true ) || preg_match( '/^(?:(?:proposed|planned|construction|disused|abandoned|demolished|razed|removed):[a-z_]+|(?:railway|public_transport|highway):(?:proposed|planned|construction|disused|abandoned|demolished|razed|removed))$/D', $key ) );
    }, ARRAY_FILTER_USE_BOTH );
}
function pera_nearby_operational( array $tags ) {
    foreach ( $tags as $key => $value ) {
        if ( preg_match( '/^(?:(?:proposed|planned|construction|disused|abandoned|demolished|razed|removed)(?::|$)|(?:railway|public_transport|highway):(?:proposed|planned|construction|disused|abandoned|demolished|razed|removed)$)/D', $key ) && ! in_array( strtolower( trim( $value, " \t\n\r\f\v" ) ), array( '', 'no', 'false', '0' ), true ) ) return false;
        if ( in_array( $key, array( 'railway', 'public_transport', 'highway', 'station', 'route', 'status', 'state' ), true ) && preg_match( '/^(?:proposed|planned|construction|under_construction|disused|abandoned|demolished|razed|removed)$/iD', $value ) ) return false;
    }
    if ( ( $tags['operational'] ?? '' ) === 'no' ) return false;
    if ( preg_match( '/^\d{4}(?:-\d{2}(?:-\d{2})?)?$/D', $tags['opening_date'] ?? '' ) ) {
        $date = $tags['opening_date']; if ( strlen( $date ) === 4 ) $date .= '-01-01'; elseif ( strlen( $date ) === 7 ) $date .= '-01';
        if ( $date > gmdate( 'Y-m-d' ) ) return false;
    }
    return true;
}
function pera_nearby_route_stop( $role ) { return in_array( $role, array( '', 'stop', 'stop_entry_only', 'stop_exit_only', 'platform', 'platform_entry_only', 'platform_exit_only' ), true ); }
function pera_nearby_transport_memberships( $routes ) {
    if ( ! is_array( $routes ) || count( $routes ) > 32 ) throw new RuntimeException( 'Invalid/oversized direct memberships.' );
    $result = array();
    foreach ( $routes as $route ) {
        if ( ! is_array( $route ) || ! is_string( $route['id'] ?? null ) || ! preg_match( '~^relation/[1-9][0-9]{0,18}$~D', $route['id'] ) || ! is_string( $route['role'] ?? null ) || strlen( $route['role'] ) > 32 || ! is_array( $route['tags'] ?? null ) ) throw new RuntimeException( 'Invalid direct membership shape.' );
        $result[] = array( 'id' => $route['id'], 'role' => $route['role'], 'tags' => pera_nearby_transport_tags( $route['tags'] ) );
    }
    return $result;
}
function pera_nearby_system( array $tags, $system ) {
    foreach ( array( 'network', 'network:short', 'operator', 'operator:short', 'brand' ) as $key ) {
        if ( $system === 'metrobus' && preg_match( '/^(?:[İIıi]ETT[ :_-]*)?(?:metrobüs|metrobus)$/iuD', trim( $tags[$key] ?? '', " \t\n\r\f\v" ) ) ) return true;
        if ( $system === 'marmaray' && preg_match( '/^(?:TCDD[ :_-]*)?marmaray$/iuD', trim( $tags[$key] ?? '', " \t\n\r\f\v" ) ) ) return true;
    }
    return false;
}
function pera_nearby_transport_group( array $tags, array $routes = array() ) {
    if ( ! pera_nearby_operational( $tags ) ) return null;
    $rail = in_array( $tags['railway'] ?? '', array( 'station', 'halt', 'tram_stop', 'funicular' ), true ) || in_array( $tags['public_transport'] ?? '', array( 'station', 'platform', 'stop_position' ), true );
    $bus = ( $tags['highway'] ?? '' ) === 'bus_stop' || ( $tags['bus'] ?? '' ) === 'yes';
    $modes = array(); $metrobus = pera_nearby_system( $tags, 'metrobus' ) || ( $tags['metrobus'] ?? '' ) === 'yes'; $marmaray = pera_nearby_system( $tags, 'marmaray' );
    foreach ( $routes as $route ) if ( pera_nearby_route_stop( $route['role'] ?? '' ) && ( $route['tags']['type'] ?? '' ) === 'route' && pera_nearby_operational( $route['tags'] ?? array() ) ) {
        $rt = $route['tags']; $mode = $rt['route'] ?? ''; $modes[] = $mode;
        if ( $mode === 'bus' && pera_nearby_system( $rt, 'metrobus' ) ) $metrobus = true;
        if ( $mode === 'train' && pera_nearby_system( $rt, 'marmaray' ) ) $marmaray = true;
    }
    if ( ( $rail || $bus ) && $metrobus ) return 'metrobus';
    if ( $rail && ( ( $tags['station'] ?? '' ) === 'funicular' || ( $tags['railway'] ?? '' ) === 'funicular' || ( $tags['funicular'] ?? '' ) === 'yes' || in_array( 'funicular', $modes, true ) ) ) return 'funicular';
    if ( $rail && ( ( $tags['railway'] ?? '' ) === 'tram_stop' || ( $tags['tram'] ?? '' ) === 'yes' || in_array( 'tram', $modes, true ) ) ) return 'tram';
    if ( $rail && $marmaray ) return 'marmaray';
    if ( $rail && ( ( $tags['station'] ?? '' ) === 'subway' || ( $tags['subway'] ?? '' ) === 'yes' || in_array( 'subway', $modes, true ) ) ) return 'metro';
    if ( $rail && ( in_array( $tags['railway'] ?? '', array( 'station', 'halt' ), true ) || ( $tags['train'] ?? '' ) === 'yes' || in_array( 'train', $modes, true ) ) ) return 'rail';
    if ( $bus ) return 'bus';
    if ( ( $tags['amenity'] ?? '' ) === 'ferry_terminal' ) return 'ferry';
    return null;
}
function pera_nearby_refs( $ref, $group ) {
    $prefix = array( 'metro' => 'M', 'tram' => 'T', 'funicular' => 'F' )[$group] ?? null;
    if ( ! $prefix || ! is_string( $ref ) ) return array();
    $refs = array(); foreach ( preg_split( '/[;,]/', $ref ) as $part ) {
        $part = strtoupper( trim( $part, " \t\n\r\f\v" ) );
        if ( preg_match( '/^' . $prefix . '[1-9][0-9]?[AB]?$/D', $part ) ) $refs[] = $part;
    }
    $refs = array_values( array_unique( $refs ) ); sort( $refs, SORT_STRING ); return $refs;
}
function pera_nearby_source_lines( array $source, $group ) {
    $tags = $source['tags']; $refs = array();
    $mode = array( 'metro' => 'subway', 'tram' => 'tram', 'funicular' => 'funicular' )[$group] ?? null;
    if ( ! $mode ) return $refs;
    // Station ref is a line ref only with explicit station mode, never just a platform number.
    if ( pera_nearby_transport_group( $tags ) === $group ) $refs = pera_nearby_refs( $tags['ref'] ?? '', $group );
    foreach ( $source['routes'] as $route ) if ( pera_nearby_route_stop( $route['role'] ) && ( $route['tags']['type'] ?? '' ) === 'route' && pera_nearby_operational( $route['tags'] ) && ( $route['tags']['route'] ?? '' ) === $mode ) {
        $refs = array_merge( $refs, pera_nearby_refs( $route['tags']['ref'] ?? '', $group ) );
    }
    $refs = array_values( array_unique( $refs ) ); sort( $refs, SORT_STRING ); return $refs;
}
function pera_nearby_transport_lines( array $transport, $group ) {
    $refs = array(); foreach ( $transport['sources'] ?? array() as $source ) $refs = array_merge( $refs, pera_nearby_source_lines( $source, $group ) );
    $refs = array_values( array_unique( $refs ) ); sort( $refs, SORT_STRING ); return $refs;
}
function pera_nearby_transport_error( $place, $schema = 2 ) {
    $reason = pera_nearby_transport_error_reason( $place, $schema );
    return $reason === null ? null : pera_nearby_text_error( $place['id'] ?? null, 'transport', $reason );
}
function pera_nearby_transport_error_reason( $place, $schema = 2 ) {
    $group = $place['group'] ?? ''; $transport = $place['transport'] ?? null;
    if ( $schema === 1 ) return isset( $place['transport'] ) || in_array( $group, array( 'tram', 'funicular' ), true ) ? 'Schema-v1 record has v2 transport fields.' : null;
    if ( ! in_array( $group, pera_nearby_transport_groups(), true ) ) return $transport !== null ? 'Non-transport record contains transport metadata.' : null;
    if ( ! is_array( $transport ) || count( $transport ) !== 2 || array_diff( array_keys( $transport ), array( 'sources', 'lines' ) ) || ! is_array( $transport['sources'] ) || ! is_array( $transport['lines'] ) || count( $transport['lines'] ) > 16 || count( $transport['sources'] ) < 1 || count( $transport['sources'] ) > 16 || strlen( json_encode( $transport, JSON_UNESCAPED_UNICODE ) ) > 32768 ) return 'Invalid/oversized transport metadata.';
    foreach ( $transport['sources'] as $source ) {
        if ( ! is_array( $source ) || count( $source ) !== 3 || array_diff( array_keys( $source ), array( 'id', 'tags', 'routes' ) ) || ! in_array( $source['id'], array_merge( array( $place['id'] ), $place['aliases'] ?? array() ), true ) || ! is_array( $source['tags'] ) || ! is_array( $source['routes'] ) || count( $source['routes'] ) > 32 ) return 'Invalid transport source/membership.';
        foreach ( array_merge( array( $source ), $source['routes'] ) as $evidence ) {
            if ( ! is_array( $evidence['tags'] ?? null ) || count( $evidence['tags'] ) > 40 || pera_nearby_transport_tags( $evidence['tags'] ) !== $evidence['tags'] ) return 'Invalid transport evidence tags.';
            foreach ( $evidence['tags'] as $value ) if ( strlen( $value ) > 512 || preg_match( '//u', $value ) !== 1 ) return 'Oversized/invalid transport evidence value.';
        }
        foreach ( $source['routes'] as $route ) if ( count( $route ) !== 3 || array_diff( array_keys( $route ), array( 'id', 'role', 'tags' ) ) || ! is_string( $route['id'] ) || ! preg_match( '~^relation/[1-9][0-9]{0,18}$~D', $route['id'] ) || ! is_string( $route['role'] ) || strlen( $route['role'] ) > 32 ) return 'Invalid direct route identity/role.';
        if ( pera_nearby_transport_group( $source['tags'], $source['routes'] ) !== $group ) return 'Transport group lacks operational evidence.';
    }
    if ( pera_nearby_transport_lines( $transport, $group ) !== $transport['lines'] ) return 'Line identifiers differ from verified evidence.';
    return null;
}
