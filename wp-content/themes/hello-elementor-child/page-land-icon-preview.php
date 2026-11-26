<?php
/**
 * Template Name: Land Icon Preview
 * Template Post Type: page
 */
defined( 'ABSPATH' ) || exit;
if ( ! current_user_can( 'edit_pages' ) ) {
    wp_die( 'This preview is restricted to website editors.', '', array( 'response' => 403 ) );
}
nocache_headers();
get_header();
$icons = array(
    array( 'land-plot', 'Plot area', '2,500 m²' ),
    array( 'zoning', 'Zoning', 'Residential' ),
    array( 'far', 'Floor area ratio', '0.50' ),
    array( 'site-coverage', 'Site coverage', '0.25' ),
    array( 'height-limit', 'Height limit', '2 storeys' ),
    array( 'road', 'Road access', 'Public road' ),
    array( 'road-frontage', 'Road frontage', '30 m' ),
    array( 'terrain', 'Terrain', 'Gently sloping' ),
    array( 'electricity', 'Electricity', 'Network nearby' ),
    array( 'water', 'Water supply', 'Network nearby' ),
    array( 'agriculture', 'Agricultural land', 'Orchard' ),
    array( 'setback', 'Building setback', '5 m' ),
);
$sprite = get_stylesheet_directory_uri() . '/logos-icons/icons.svg';
?>
<style>
.pera-land-preview{max-width:1100px;margin:40px auto;padding:24px;color:#043673}
.pera-land-preview-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
.pera-land-preview-card{border:1px solid #dce0e6;padding:16px;border-radius:6px}
.pera-land-preview-sizes{display:flex;align-items:center;gap:20px;margin:16px 0}
.pera-land-preview svg{display:inline-block;flex:none;color:inherit;vertical-align:middle}
.pera-land-preview-spec{display:flex;align-items:center;gap:10px;background:#f5f6fa;padding:12px}
.pera-land-preview-spec small{display:block;color:#536174}
.pera-land-preview code{font-size:12px;overflow-wrap:anywhere}
</style>
<main class="pera-land-preview">
<h1>Land icon preview</h1>
<p>External SVG sprite test at 16, 24 and 40 pixels, plus sample property specifications. All property values are illustrative.</p>
<div class="pera-land-preview-grid">
<?php foreach ( $icons as $item ) :
    $id = 'icon-' . $item[0];
    $href = $sprite . '#' . $id;
?>
<section class="pera-land-preview-card">
<h2 style="font-size:18px"><?php echo esc_html( $item[1] ); ?></h2>
<code><?php echo esc_html( $id ); ?></code>
<div class="pera-land-preview-sizes">
<?php foreach ( array( 16, 24, 40 ) as $size ) : ?>
<svg class="icon" style="width:<?php echo (int) $size; ?>px;height:<?php echo (int) $size; ?>px" aria-hidden="true"><use href="<?php echo esc_url( $href ); ?>"></use></svg>
<?php endforeach; ?>
</div>
<div class="pera-land-preview-spec">
<svg class="icon" style="width:24px;height:24px" aria-hidden="true"><use href="<?php echo esc_url( $href ); ?>"></use></svg>
<span><strong><?php echo esc_html( $item[2] ); ?></strong><small><?php echo esc_html( $item[1] ); ?></small></span>
</div>
</section>
<?php endforeach; ?>
</div>
</main>
<?php get_footer(); ?>
