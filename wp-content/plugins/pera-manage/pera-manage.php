<?php
/**
 * Plugin Name: Pera Manage
 * Description: Private, read-only website administration hub linking to existing WordPress editors.
 * Version: 0.2.0
 */
defined( 'ABSPATH' ) || exit;

final class Pera_Manage {
    private const VERSION = '0.2.0';
    private const ACCESS_CAP = 'pera_access_manage_dashboard';

    public static function grant_access(): void {
        foreach ( array( 'administrator', 'manager', 'employee' ) as $name ) {
            $role = get_role( $name );
            if ( $role && ! $role->has_cap( self::ACCESS_CAP ) ) $role->add_cap( self::ACCESS_CAP );
        }
        update_option( 'pera_manage_role_caps_version', self::VERSION, false );
    }

    public static function boot(): void {
        register_activation_hook( __FILE__, array( __CLASS__, 'grant_access' ) );
        add_action( 'init', static function (): void {
            if ( get_option( 'pera_manage_role_caps_version' ) !== self::VERSION ) self::grant_access();
        }, 30 );
        add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 99, 3 );
        // Bypass WordPress content routing: this is a private, plugin-owned shell.
        add_action( 'template_redirect', array( __CLASS__, 'route' ), -200 );
        add_filter( 'robots_txt', array( __CLASS__, 'robots' ), 10, 2 );
    }

    /** This intentionally grants no editing capability of its own. */
    private static function allowed(): bool {
        // The theme currently redirects non-managers away from wp-admin.
        return current_user_can( self::ACCESS_CAP );
    }

    private static function can_edit_in_admin(): bool {
        return current_user_can( 'manage_options' );
    }

    public static function login_redirect( $redirect_to, $requested_redirect_to, $user ) {
        if ( ! $user instanceof WP_User || ! user_can( $user, self::ACCESS_CAP ) ) return $redirect_to;
        if ( is_string( $requested_redirect_to ) && '' !== trim( $requested_redirect_to ) ) return $redirect_to;
        return home_url( '/manage/' );
    }

    private static function request_section(): string {
        $view = isset( $_GET['view'] ) && is_string( $_GET['view'] )
            ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'overview';
        return in_array( $view, array( 'overview', 'properties', 'content', 'translations', 'tools' ), true )
            ? $view : 'overview';
    }

    private static function canonical_path(): string {
        $home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
        return trailingslashit( $home_path ) . 'manage/';
    }

    private static function matched_path(): bool {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';
        $path = wp_parse_url( $uri, PHP_URL_PATH );
        if ( ! is_string( $path ) ) return false;
        return untrailingslashit( $path ) === untrailingslashit( self::canonical_path() );
    }

    public static function robots( string $output, bool $public ): string {
        return $output . "\nDisallow: /manage/\n";
    }

    public static function route(): void {
        if ( ! self::matched_path() ) return;

        // A path gate is required before any private query is executed.
        if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
        nocache_headers();
        header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
        header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
        header( 'Vary: Cookie', false );

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( home_url( '/manage/' ) ) );
            exit;
        }
        if ( ! self::allowed() ) {
            status_header( 403 );
            wp_die( esc_html__( 'You do not have permission to access website management.', 'pera-manage' ), '', array( 'response' => 403 ) );
        }

        global $wp_query;
        if ( $wp_query instanceof WP_Query ) $wp_query->is_404 = false;
        status_header( 200 );
        self::render();
        exit;
    }

    private static function url( string $view = 'overview', array $args = array() ): string {
        $args = array_merge( $view === 'overview' ? array() : array( 'view' => $view ), $args );
        return add_query_arg( $args, home_url( '/manage/' ) );
    }

    private static function count( string $type, string $status ): int {
        $counts = wp_count_posts( $type, 'readable' );
        return $counts && isset( $counts->$status ) ? (int) $counts->$status : 0;
    }

    private static function properties(): void {
        $search = isset( $_GET['s'] ) && is_string( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $status = isset( $_GET['status'] ) && is_string( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
        if ( ! in_array( $status, array( 'all', 'publish', 'draft', 'pending', 'future' ), true ) ) $status = 'all';
        $kind = isset( $_GET['kind'] ) && is_string( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : 'all';
        if ( ! in_array( $kind, array( 'all', 'project', 'resales' ), true ) ) $kind = 'all';
        $page = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1;
        $args = array(
            'post_type' => 'property',
            'post_status' => ! self::can_edit_in_admin() ? 'publish' : ( 'all' === $status ? array( 'publish', 'draft', 'pending', 'future', 'private' ) : $status ),
            'posts_per_page' => 20,
            'paged' => $page,
            's' => $search,
            'orderby' => 'modified',
            'order' => 'DESC',
        );
        if ( 'all' !== $kind && taxonomy_exists( 'special' ) ) {
            $args['tax_query'] = array( array( 'taxonomy' => 'special', 'field' => 'slug', 'terms' => $kind ) );
        }
        $query = new WP_Query( $args );

        echo '<div class="pm-title-row"><div><h1>Properties</h1><p>Find listings and open their existing WordPress editor.</p></div>';
        if ( self::can_edit_in_admin() ) self::link_button( admin_url( 'post-new.php?post_type=property' ), 'Add property' );
        echo '</div><form class="pm-filters" method="get" action="' . esc_url( self::url() ) . '">';
        echo '<input type="hidden" name="view" value="properties"><label>Search <input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="Name or keyword"></label>';
        echo '<label>Status <select name="status">';
        foreach ( array( 'all' => 'All', 'publish' => 'Published', 'draft' => 'Draft', 'pending' => 'Pending', 'future' => 'Scheduled' ) as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label><label>Type <select name="kind">';
        foreach ( array( 'all' => 'All types', 'project' => 'Projects', 'resales' => 'Resales' ) as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $kind, $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label><button class="btn" type="submit">Filter</button></form>';
        self::table( $query );
        self::pagination( $query, 'properties', array( 's' => $search, 'status' => $status, 'kind' => $kind ) );
        wp_reset_postdata();
    }

    private static function content(): void {
        $type = isset( $_GET['type'] ) && 'page' === sanitize_key( wp_unslash( $_GET['type'] ) ) ? 'page' : 'post';
        $search = isset( $_GET['s'] ) && is_string( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $page = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1;
        $query = new WP_Query( array( 'post_type' => $type, 'post_status' => self::can_edit_in_admin() ? array( 'publish', 'draft', 'pending', 'future', 'private' ) : 'publish', 'posts_per_page' => 20, 'paged' => $page, 's' => $search, 'orderby' => 'modified', 'order' => 'DESC' ) );
        echo '<div class="pm-title-row"><div><h1>Content</h1><p>Articles and pages use the existing WordPress editor.</p></div>';
        if ( self::can_edit_in_admin() ) self::link_button( admin_url( 'post-new.php?post_type=' . $type ), 'Add ' . ( 'post' === $type ? 'article' : 'page' ) );
        echo '</div><form class="pm-filters" method="get" action="' . esc_url( self::url() ) . '"><input type="hidden" name="view" value="content">';
        echo '<label>Content type <select name="type"><option value="post"' . selected( $type, 'post', false ) . '>Articles</option><option value="page"' . selected( $type, 'page', false ) . '>Pages</option></select></label>';
        echo '<label>Search <input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="Title or keyword"></label><button class="btn" type="submit">Filter</button></form>';
        self::table( $query );
        self::pagination( $query, 'content', array( 'type' => $type, 's' => $search ) );
        wp_reset_postdata();
    }

    private static function table( WP_Query $query ): void {
        echo '<div class="pm-table-wrap"><table class="pm-table"><thead><tr><th scope="col">Title</th><th scope="col">Status</th><th scope="col">Updated</th><th scope="col">Actions</th></tr></thead><tbody>';
        if ( ! $query->have_posts() ) echo '<tr><td colspan="4">No matching items.</td></tr>';
        foreach ( $query->posts as $post ) {
            if ( ! self::can_edit_in_admin() && 'publish' !== $post->post_status ) continue;
            $edit = get_edit_post_link( $post->ID, 'raw' );
            $view = get_permalink( $post );
            echo '<tr><td><strong>' . esc_html( get_the_title( $post ) ?: '(Untitled)' ) . '</strong><span class="pm-sub">#' . (int) $post->ID . '</span></td>';
            echo '<td><span class="pill">' . esc_html( ucfirst( $post->post_status ) ) . '</span></td>';
            echo '<td>' . esc_html( get_the_modified_date( 'j M Y', $post ) ) . '</td><td class="pm-row-actions">';
            if ( self::can_edit_in_admin() && current_user_can( 'edit_post', $post->ID ) && $edit ) echo '<a href="' . esc_url( $edit ) . '">Edit</a> ';
            if ( $view ) echo '<a href="' . esc_url( $view ) . '" target="_blank" rel="noopener noreferrer">View</a>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function pagination( WP_Query $query, string $view, array $params ): void {
        if ( $query->max_num_pages < 2 ) return;
        $current = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1;
        echo '<nav class="pm-pagination" aria-label="Pagination">';
        for ( $i = max( 1, $current - 2 ); $i <= min( (int) $query->max_num_pages, $current + 2 ); $i++ ) {
            $args = array_merge( $params, array( 'pg' => $i ) );
            echo '<a ' . ( $i === $current ? 'aria-current="page"' : '' ) . ' href="' . esc_url( self::url( $view, $args ) ) . '">' . (int) $i . '</a>';
        }
        echo '</nav>';
    }

    private static function link_button( string $url, string $text ): void {
        echo '<a class="btn" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';
    }

    private static function overview(): void {
        echo '<div class="pm-title-row"><div><h1>Website overview</h1><p>Quick access to the tools you use every day.</p></div>';
        if ( self::can_edit_in_admin() ) self::link_button( admin_url( 'post-new.php?post_type=property' ), 'Add property' );
        echo '</div><div class="pm-stats">';
        foreach ( array(
            array( 'Published properties', self::count( 'property', 'publish' ), 'properties' ),
            array( 'Draft properties', self::can_edit_in_admin() ? self::count( 'property', 'draft' ) : 0, 'properties' ),
            array( 'Published articles', self::count( 'post', 'publish' ), 'content' ),
            array( 'Draft articles', self::can_edit_in_admin() ? self::count( 'post', 'draft' ) : 0, 'content' ),
        ) as $stat ) {
            echo '<a class="card-shell pm-stat" href="' . esc_url( self::url( $stat[2] ) ) . '"><span>' . esc_html( $stat[0] ) . '</span><strong>' . number_format_i18n( $stat[1] ) . '</strong></a>';
        }
        echo '</div><h2>Quick actions</h2><div class="pm-actions">';
        $actions = array(
            array( 'Find a property', self::url( 'properties' ) ),
            array( 'Add a property', admin_url( 'post-new.php?post_type=property' ) ),
            array( 'Edit articles', self::url( 'content' ) ),
            array( 'New article', admin_url( 'post-new.php' ) ),
            array( 'Translation tools', self::url( 'translations' ) ),
            array( 'Site Performance', admin_url( 'admin.php?page=pera-site-performance' ) ),
        );
        if ( ! self::can_edit_in_admin() ) $actions = array_filter( $actions, static function ( $item ) { return ! in_array( $item[0], array( 'Add a property', 'New article', 'Translation tools', 'Site Performance' ), true ); } );
        if ( self::crm_access() ) $actions[] = array( 'Open CRM', home_url( '/crm/' ) );
        foreach ( $actions as $item ) echo '<a class="card-shell pm-action" href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . ' <span aria-hidden="true">→</span></a>';
        echo '</div><h2>Recently updated properties</h2>';
        $query = new WP_Query( array( 'post_type' => 'property', 'post_status' => self::can_edit_in_admin() ? array( 'publish', 'draft', 'pending' ) : 'publish', 'posts_per_page' => 6, 'orderby' => 'modified', 'order' => 'DESC' ) );
        self::table( $query );
        wp_reset_postdata();
    }

    private static function crm_access(): bool {
        return function_exists( 'peracrm_user_can_access_crm' ) && peracrm_user_can_access_crm();
    }

    private static function translations(): void {
        if ( ! self::can_edit_in_admin() ) { echo '<h1>Translations</h1><p>Only administrators can use the existing WordPress translation tools.</p>'; return; }
        echo '<h1>Translations</h1><p>Translation actions remain in the existing WordPress property and article editors.</p><div class="pm-actions">';
        foreach ( array( array( 'Properties', admin_url( 'edit.php?post_type=property' ) ), array( 'Articles', admin_url( 'edit.php' ) ), array( 'Pages', admin_url( 'edit.php?post_type=page' ) ) ) as $item ) {
            echo '<a class="card-shell pm-action" href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . ' <span aria-hidden="true">→</span></a>';
        }
        echo '</div><p class="pm-note">Open an item and use its existing Pera Multilingual panel. No new translation queues or regeneration actions are introduced here.</p>';
    }

    private static function tools(): void {
        if ( ! self::can_edit_in_admin() ) { echo '<h1>Site tools</h1><p>Only administrators can use WordPress site tools.</p>'; return; }
        echo '<h1>Site tools</h1><p>Links to existing administrative screens; no maintenance actions are executed here.</p><div class="pm-actions">';
        foreach ( array( array( 'WordPress dashboard', admin_url() ), array( 'Media Library', admin_url( 'upload.php' ) ), array( 'Site Performance', admin_url( 'admin.php?page=pera-site-performance' ) ), array( 'Site Health', admin_url( 'site-health.php' ) ), array( 'Menus', admin_url( 'nav-menus.php' ) ) ) as $item ) {
            echo '<a class="card-shell pm-action" href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . ' <span aria-hidden="true">→</span></a>';
        }
        echo '</div>';
    }

    private static function render(): void {
        $view = self::request_section();
        $styles = get_stylesheet_directory() . '/css/main.css';
        wp_enqueue_style( 'pera-main-css', get_stylesheet_directory_uri() . '/css/main.css', array(), file_exists( $styles ) ? (string) filemtime( $styles ) : self::VERSION );
        wp_enqueue_style( 'pera-manage-css', plugin_dir_url( __FILE__ ) . 'assets/manage.css', array( 'pera-main-css' ), self::VERSION );

        ?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive">
<title><?php echo esc_html( 'Website management | ' . get_bloginfo( 'name' ) ); ?></title>
<?php wp_print_styles( array( 'pera-main-css', 'pera-manage-css' ) ); ?>
</head>
<body class="pm-root">
<a class="pm-skip" href="#pm-main">Skip to content</a>
<div class="pm-shell">
<aside class="pm-side" aria-label="Website management navigation">
<a class="pm-brand" href="<?php echo esc_url( self::url() ); ?>"><strong>PERA PROPERTY</strong><small>Website management</small></a>
<nav class="pm-nav" aria-label="Main navigation">
<?php foreach ( array_filter( array( 'overview' => 'Overview', 'properties' => 'Properties', 'content' => 'Articles & pages', 'translations' => self::can_edit_in_admin() ? 'Translations' : null, 'tools' => self::can_edit_in_admin() ? 'Site tools' : null ) ) as $key => $label ) : ?>
<a href="<?php echo esc_url( self::url( $key ) ); ?>" <?php echo $view === $key ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
<?php endforeach; ?>
<?php if ( self::crm_access() ) : ?><a href="<?php echo esc_url( home_url( '/crm/' ) ); ?>">CRM</a><?php endif; ?>
</nav><div class="pm-side-footer"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">View website</a><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Log out</a></div>
</aside>
<div class="pm-body"><header class="pm-top"><span>Website management</span><span><?php echo esc_html( wp_get_current_user()->display_name ); ?></span></header><main id="pm-main" class="pm-content">
<?php
        switch ( $view ) {
            case 'properties': self::properties(); break;
            case 'content': self::content(); break;
            case 'translations': self::translations(); break;
            case 'tools': self::tools(); break;
            default: self::overview(); break;
        }
?>
</main></div></div>
</body></html><?php
    }
}
Pera_Manage::boot();
