<?php
/**
 * Partial: About Pera Property
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$about_page_url = get_permalink( get_page_by_path( 'about-us' ) );
?>

<section class="section section-soft">
    <div class="content-panel-box border-dm">

        <header class="section-header section-header--center">
            <h2><?php echo esc_html( pera_ml_ui( 'About Pera Property', 'theme.about_pera.about_pera_property' ) ); ?></h2>
            <p><?php echo esc_html( pera_ml_ui( 'Pera Property is an Istanbul-based real estate consultancy specialising in property sales, lettings and property management. Our team has worked in the Istanbul property market since 2016, advising local and international property owners, buyers and investors.', 'theme.about_pera.company_intro' ) ); ?></p>
            <p>
                <em><?php echo esc_html( pera_ml_ui( 'Our independent, whole-of-market approach means our advice is based on each client\'s individual property requirements.', 'theme.about_pera.independent_approach' ) ); ?></em>
            </p>
        </header>

        <div class="signoff-card width-restricter centered">
            <div class="signoff-avatar">
                <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/images/dkd-thumb.jpg' ); ?>" alt="D Koray Dillioglu">
            </div>

            <div class="signoff-text">
                <p class="signoff-name">D Koray Dillioglu</p>
                <p><?php echo esc_html( pera_ml_ui( 'Director @ Pera Property', 'theme.about_pera.director_pera_property' ) ); ?></p>
            </div>
        </div>

        <div class="hero-actions flex-center">
            <a href="<?php echo esc_url( $about_page_url ); ?>" class="btn btn--solid btn--blue">
                <?php echo esc_html( pera_ml_ui( 'Learn more about Pera', 'theme.about_pera.learn_more' ) ); ?>
            </a>
        </div>

    </div>
</section>
