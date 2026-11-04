<?php
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

function expect_front_edit_tools( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

$theme           = dirname( __DIR__ );
$tools           = file_get_contents( $theme . '/inc/admin/front-end-edit-tools.php' );
$admin_bootstrap = file_get_contents( $theme . '/inc/bootstrap/admin.php' );
$term_view_link  = file_get_contents( $theme . '/inc/admin/taxonomy-term-view-link.php' );

expect_front_edit_tools( false !== strpos( $tools, '$queried_object instanceof WP_Post' ), 'post edit links are restricted to queried posts' );
expect_front_edit_tools( false !== strpos( $tools, '$queried_object instanceof WP_Term' ), 'taxonomy archives detect their queried term' );
expect_front_edit_tools( false !== strpos( $tools, "current_user_can( 'edit_term', \$queried_object->term_id )" ), 'term edit link respects the edit_term capability' );
expect_front_edit_tools( false !== strpos( $tools, 'get_edit_term_link( $queried_object->term_id, $queried_object->taxonomy )' ), 'term edit link targets the current taxonomy and term' );
expect_front_edit_tools( false !== strpos( $tools, "esc_html__( 'Edit Taxonomy', 'hello-elementor-child' )" ), 'taxonomy shortcut has the requested label' );

$term_link_position = strpos( $tools, 'if ( $edit_term_url )' );
$template_position  = strpos( $tools, 'if ( $github_url && $template_label )' );
expect_front_edit_tools( false !== $term_link_position && false !== $template_position && $term_link_position < $template_position, 'taxonomy shortcut renders to the left of the template shortcut' );

expect_front_edit_tools( false !== strpos( $admin_bootstrap, "/inc/admin/taxonomy-term-view-link.php" ), 'wp-admin bootstrap loads the taxonomy term view shortcut' );
expect_front_edit_tools( false !== strpos( $term_view_link, "add_action( 'admin_notices', 'pera_admin_render_term_view_link' )" ), 'term edit screens register the front-end link notice' );
expect_front_edit_tools( false !== strpos( $term_view_link, "'term.php' !== \$pagenow" ), 'front-end term link is limited to term edit screens' );
expect_front_edit_tools( false !== strpos( $term_view_link, '! $tax_obj->public' ), 'private taxonomies do not expose a front-end shortcut' );
expect_front_edit_tools( false !== strpos( $term_view_link, "current_user_can( 'edit_term', \$term_id )" ), 'admin term shortcut respects the edit_term capability' );
expect_front_edit_tools( false !== strpos( $term_view_link, 'get_term_link( $term )' ), 'front-end shortcut uses the canonical hierarchical term permalink' );
expect_front_edit_tools( false !== strpos( $term_view_link, "esc_html__( 'View on site', 'hello-elementor-child' )" ), 'admin shortcut uses the requested view label' );
expect_front_edit_tools( false === strpos( $tools, "add_action( 'admin_notices'" ), 'wp-admin notice is not registered from the frontend-only helper' );

echo "Front-end edit tools tests passed\n";
