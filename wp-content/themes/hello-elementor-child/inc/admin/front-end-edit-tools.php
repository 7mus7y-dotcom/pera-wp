<?php
/**
 * Admin-only front-end edit helper.
 *
 * Shows quick links for editing the current page and opening the resolved
 * PHP template in GitHub.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'template_include', 'pera_front_end_edit_tools_capture_template', 999 );

/**
 * Store the final template path selected by WordPress.
 *
 * @param string $template Resolved template path.
 * @return string
 */
function pera_front_end_edit_tools_capture_template( $template ) {
	$GLOBALS['pera_front_end_edit_tools_template'] = $template;

	return $template;
}

/**
 * Determine whether the front-end edit toolbar should be available.
 *
 * @return bool
 */
function pera_front_end_edit_tools_should_render() {
	return ! is_admin()
		&& is_user_logged_in()
		&& current_user_can( 'manage_options' );
}

add_action( 'wp_enqueue_scripts', 'pera_front_end_edit_tools_enqueue_assets' );

/**
 * Enqueue the toolbar stylesheet only for front-end administrators.
 *
 * @return void
 */
function pera_front_end_edit_tools_enqueue_assets() {
	if ( ! pera_front_end_edit_tools_should_render() ) {
		return;
	}

	$relative_path = '/css/front-end-edit-tools.css';
	$file_path     = get_stylesheet_directory() . $relative_path;

	wp_enqueue_style(
		'pera-front-end-edit-tools',
		get_stylesheet_directory_uri() . $relative_path,
		array(),
		file_exists( $file_path ) ? filemtime( $file_path ) : null
	);
}

add_action( 'wp_footer', 'pera_front_end_edit_tools_render', 100 );

/**
 * Render front-end edit shortcuts for administrators.
 *
 * @return void
 */
function pera_front_end_edit_tools_render() {
	if ( ! pera_front_end_edit_tools_should_render() ) {
		return;
	}

	$post_id       = get_queried_object_id();
	$edit_page_url = '';

	if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
		$edit_page_url = get_edit_post_link( $post_id );
	}

	$github_url     = '';
	$template_label = '';
	$template_path  = isset( $GLOBALS['pera_front_end_edit_tools_template'] )
		? wp_normalize_path( $GLOBALS['pera_front_end_edit_tools_template'] )
		: '';
	$theme_dir      = wp_normalize_path( get_stylesheet_directory() );
	$theme_dir_root = trailingslashit( $theme_dir );

	if ( $template_path && 0 === strpos( $template_path, $theme_dir_root ) ) {
		$relative_template = ltrim( str_replace( $theme_dir, '', $template_path ), '/' );

		if ( $relative_template ) {
			$repo_relative_path = 'wp-content/themes/hello-elementor-child/' . $relative_template;
			$github_url         = 'https://github.com/7mus7y-dotcom/pera-wp/blob/main/' . $repo_relative_path;
			$template_label     = basename( $relative_template );
		}
	}

	if ( ! $edit_page_url && ! $github_url ) {
		return;
	}

	echo '<nav class="pera-front-edit-tools" aria-label="' . esc_attr__( 'Admin edit shortcuts', 'hello-elementor-child' ) . '">';

	if ( $edit_page_url ) {
		echo '<a class="pera-front-edit-tools__link" href="' . esc_url( $edit_page_url ) . '">';
		echo esc_html__( 'Edit Page', 'hello-elementor-child' );
		echo '</a>';
	}

	if ( $github_url && $template_label ) {
		echo '<a class="pera-front-edit-tools__link" href="' . esc_url( $github_url ) . '" target="_blank" rel="noopener noreferrer">';
		echo esc_html( sprintf( 'Template: %s', $template_label ) );
		echo '</a>';
	}

	echo '</nav>';
}
