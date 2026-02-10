<?php
/**
 * Attachment template (media page)
 * Location: /attachment.php
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

get_header();

the_post();

$attachment_id = get_the_ID();
$attachment_title = get_the_title( $attachment_id );
$parent_id = wp_get_post_parent_id( $attachment_id );
$parent_link = $parent_id ? get_permalink( $parent_id ) : '';
$parent_title = $parent_id ? get_the_title( $parent_id ) : '';
$full_url = wp_get_attachment_url( $attachment_id );
$caption = wp_get_attachment_caption( $attachment_id );
$description = get_the_content();
$hero_img_id = wp_attachment_is_image( $attachment_id ) ? $attachment_id : 0;
?>

<main id="primary" class="site-main content-rail">
  <section class="hero hero--left hero--fit">
    
     <div class="hero-content">
      <nav aria-label="Breadcrumb">
        <div class="inline-row" role="list">
          <a class="pill pill--green" href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
          <?php if ( $parent_id && $parent_link ) : ?>
            <a class="pill pill--green" href="<?php echo esc_url( $parent_link ); ?>">
              <?php echo esc_html( $parent_title ); ?>
            </a>
          <?php endif; ?>
          <span class="pill pill--green" aria-current="page">
            <?php echo esc_html( $attachment_title ); ?>
          </span>
        </div>
      </nav>

      <h1><?php echo esc_html( $attachment_title ); ?></h1>

      <div class="hero-actions">
        <?php if ( $parent_id && $parent_link ) : ?>
          <a class="btn btn--solid btn--red" href="<?php echo esc_url( $parent_link ); ?>">
            View property
          </a>
        <?php endif; ?>
        <?php if ( $full_url ) : ?>
          <a class="btn btn--ghost btn--green" href="<?php echo esc_url( $full_url ); ?>" target="_blank" rel="noopener">
            View original
          </a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="section section-soft">
    <div class="content-panel">
      <div class="content-panel-box">
        <div class="img-center">
          <?php if ( wp_attachment_is_image( $attachment_id ) ) : ?>
            <?php
            echo wp_get_attachment_image(
              $attachment_id,
              'large',
              false,
              array(
                'class' => 'rounded',
                'loading' => 'eager',
              )
            );
            ?>
          <?php else : ?>
            <p class="text-sm text-muted">This attachment is not an image.</p>
          <?php endif; ?>
        </div>

        <?php if ( $caption ) : ?>
          <p class="text-sm text-muted">
            <?php echo esc_html( $caption ); ?>
          </p>
        <?php endif; ?>

        <?php if ( $description ) : ?>
          <div>
            <?php echo wp_kses_post( wpautop( $description ) ); ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php
get_footer();
