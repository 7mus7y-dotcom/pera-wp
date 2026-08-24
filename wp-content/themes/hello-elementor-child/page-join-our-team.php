<?php
/**
 * Template Name: Join Our Team
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<main id="primary" class="site-main">

    <!-- =====================================
     HERO – CAREERS
     Canonical structure + fallback background
     ===================================== -->
        <section class="hero hero--center hero--careers" id="careers-hero">
        
          <div class="hero__media" aria-hidden="true">
            <?php
              // Optional featured image support for the future
              $hero_img_id = get_post_thumbnail_id();
        
              if ( $hero_img_id ) {
                echo wp_get_attachment_image(
                  $hero_img_id,
                  'full',
                  false,
                  array(
                    'class'    => 'hero-media',
                    'loading'  => 'eager',
                    'decoding' => 'async',
                  )
                );
              } else {
                // Fallback background (vopbesiktas.svg – attachment ID 55756)
                echo wp_get_attachment_image(
                  55756,
                  'full',
                  false,
                  array(
                    'class'         => 'hero-media',
                    'fetchpriority' => 'high',
                    'loading'       => 'eager',
                    'decoding'      => 'async',
                  )
                );
              }
            ?>
            <div class="hero-overlay" aria-hidden="true"></div>
          </div>
        
          <div class="hero-content">
            <div class="pill pill--brand"><?php echo esc_html( pera_ml_ui( 'Careers at Pera Property', 'theme.template.page_join_our_team.careers_at_pera_property' ) ); ?></div>
        
            <h1><?php echo esc_html( pera_ml_ui( 'Join our team in Istanbul.', 'theme.template.page_join_our_team.join_our_team_in_istanbul' ) ); ?></h1>
        
            <p class="lead">
              <?php echo esc_html( pera_ml_ui( 'We’re always interested in meeting talented people who are passionate about
              Istanbul real estate, client service and building long-term relationships.', 'theme.template.page_join_our_team.we_re_always_interested_in_meeting_talented_people_who_are_passionate_ab' ) ); ?>
            </p>
        
            <div class="hero-actions flex-center">
              <a href="#open-roles" class="btn btn--solid btn--blue">
                <?php echo esc_html( pera_ml_ui( 'View open positions', 'theme.template.page_join_our_team.view_open_positions' ) ); ?>
              </a>
            </div>
          </div>
        
        </section>



    <!-- WHY WORK WITH PERA -->
    <section class="section">
        <div class="content-panel-box">
            <div class="content-panel-grid">

                <div>
                    <header class="section-header">
                        <h2><?php echo esc_html( pera_ml_ui( 'Why work with Pera?', 'theme.template.page_join_our_team.why_work_with_pera' ) ); ?></h2>
                        <p>
                            <?php echo esc_html( pera_ml_ui( 'We combine an entrepreneurial culture with the stability of a well-established
                            Istanbul agency. You’ll work directly with international investors, local owners
                            and developers on meaningful transactions.', 'theme.template.page_join_our_team.we_combine_an_entrepreneurial_culture_with_the_stability_of_a_well_estab' ) ); ?>
                        </p>
                    </header>

                    <ul class="checklist checklist--circle">
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Hands-on experience across sales, lettings and investment.', 'theme.template.page_join_our_team.hands_on_experience_across_sales_lettings_and_investment' ) ); ?>
                        </li>
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Work with international clients every day.', 'theme.template.page_join_our_team.work_with_international_clients_every_day' ) ); ?>
                        </li>
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Training and mentoring from senior consultants.', 'theme.template.page_join_our_team.training_and_mentoring_from_senior_consultants' ) ); ?>
                        </li>
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Central Istanbul location, modern working environment.', 'theme.template.page_join_our_team.central_istanbul_location_modern_working_environment' ) ); ?>
                        </li>
                    </ul>
                </div>

                 <!-- <div>
                    <div class="media-frame">
                        <img class="media-embed"
                             src="<?php echo get_stylesheet_directory_uri(); ?>/images/team-office.jpg"
                             alt="<?php echo esc_attr( pera_ml_ui( 'Pera Property team in Istanbul office', 'theme.template.page_join_our_team.alt.pera_property_team_in_istanbul_office' ) ); ?>">
                    </div>
                </div>-->

            </div>
        </div>
    </section>


    <!-- OPEN ROLES -->
    <section id="open-roles" class="section section-soft">
        <div class="section-header section-header--center">
            <h2><?php echo esc_html( pera_ml_ui( 'Current open positions', 'theme.template.page_join_our_team.current_open_positions' ) ); ?></h2>
            <p><?php echo esc_html( pera_ml_ui( 'If you don’t see the right role, you can still send us your CV using the form below.', 'theme.template.page_join_our_team.if_you_don_t_see_the_right_role_you_can_still_send_us_your_cv_using_the_' ) ); ?></p>
        </div>

        <div class="feature-grid">

            <!-- ROLE 1 -->
            <article class="feature-card">
                <div class="feature-card-header">
                    <h3><?php echo esc_html( pera_ml_ui( 'Senior Property Consultant', 'theme.template.page_join_our_team.senior_property_consultant' ) ); ?></h3>
                    <p class="price-tag"><?php echo esc_html( pera_ml_ui( 'Full-time · Istanbul', 'theme.template.page_join_our_team.full_time_istanbul' ) ); ?></p>
                </div>
                <div class="feature-card-body">
                    <ul class="checklist checklist--circle">
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Advise international and local buyers on Istanbul property.', 'theme.template.page_join_our_team.advise_international_and_local_buyers_on_istanbul_property' ) ); ?>
                        </li>
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Manage a portfolio of new-build and resale listings.', 'theme.template.page_join_our_team.manage_a_portfolio_of_new_build_and_resale_listings' ) ); ?>
                        </li>
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Fluent English required; Turkish a strong advantage.', 'theme.template.page_join_our_team.fluent_english_required_turkish_a_strong_advantage' ) ); ?>
                        </li>
                    </ul>
                </div>
                <div class="feature-card-footer">
                    <a href="#contact" class="btn btn--solid btn--blue"><?php echo esc_html( pera_ml_ui( 'Apply for this role', 'theme.template.page_join_our_team.apply_for_this_role' ) ); ?></a>
                </div>
            </article>

            <!-- ROLE 2 -->
            <article class="feature-card">
                <div class="feature-card-header">
                    <h3><?php echo esc_html( pera_ml_ui( 'Lettings & Property Manager', 'theme.template.page_join_our_team.lettings_and_property_manager' ) ); ?></h3>
                    <p class="price-tag"><?php echo esc_html( pera_ml_ui( 'Full-time · Istanbul', 'theme.template.page_join_our_team.full_time_istanbul' ) ); ?></p>
                </div>
                <div class="feature-card-body">
                    <ul class="checklist checklist--circle">
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Oversee day-to-day management of long-term rentals.', 'theme.template.page_join_our_team.oversee_day_to_day_management_of_long_term_rentals' ) ); ?>
                        </li>
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Coordinate maintenance, inspections and tenant relations.', 'theme.template.page_join_our_team.coordinate_maintenance_inspections_and_tenant_relations' ) ); ?>
                        </li>
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Strong organisational skills and client focus.', 'theme.template.page_join_our_team.strong_organisational_skills_and_client_focus' ) ); ?>
                        </li>
                    </ul>
                </div>
                <div class="feature-card-footer">
                    <a href="#contact" class="btn btn--solid btn--green"><?php echo esc_html( pera_ml_ui( 'Apply for this role', 'theme.template.page_join_our_team.apply_for_this_role' ) ); ?></a>
                </div>
            </article>

        </div>
    </section>


    <!-- ABOUT PERA (PARTIAL) -->
    <?php get_template_part( 'parts/about-pera' ); ?>

</main>

<?php get_footer(); ?>
