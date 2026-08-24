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
            <h2><?php echo esc_html( pera_ml_ui( 'About Our Company', 'theme.about_pera.about_our_company' ) ); ?></h2>
            <p><?php echo esc_html( pera_ml_ui( 'Pera Property brings together the most experienced minds of the real estate industry. It is a strategy which has created a large portfolio of new-build as well as unique property in Turkey.', 'theme.about_pera.pera_property_brings_together_the_most_experienced_minds_of_' ) ); ?></p>
            <p>
                <em><?php echo esc_html( pera_ml_ui( 'Our impartial whole-of-market approach ensures our clients achieve the optimal end goal.', 'theme.about_pera.our_impartial_whole_of_market_approach_ensures_our_clients_a' ) ); ?></em>
            </p>
        </header>

        <div class="signoff-card width-restricter centered">
            <div class="signoff-avatar">
                <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/images/dkd-thumb.jpg' ); ?>" alt="D Koray Dillioglu">
            </div>
        
            <div class="signoff-text">
                <h5>D Koray Dillioglu</h5>
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
