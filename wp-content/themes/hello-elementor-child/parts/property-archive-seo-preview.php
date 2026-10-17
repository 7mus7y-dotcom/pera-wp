<?php
/** Admin-only presentation preview; never used by the public schema provider. */
if ( ! defined( 'ABSPATH' ) || empty( $seo_archive_preview ) ) {
  return;
}
?>
  <?php
    $archive_bottom_content      = (string) $property_archive_get_field( 'archive_bottom_content' );
    $archive_cta_heading         = (string) $property_archive_get_field( 'archive_cta_heading' );
    $archive_cta_text            = (string) $property_archive_get_field( 'archive_cta_text' );
    $archive_cta_heading = $archive_cta_heading !== ''
      ? $archive_cta_heading
      : pera_ml_ui( 'Get your Istanbul property shortlist', 'theme.template.archive_property.shortlist_heading' );
    $archive_cta_text = $archive_cta_text !== ''
      ? $archive_cta_text
      : pera_ml_ui( 'Tell us your budget, preferred districts, bedroom needs and buying timeframe. We will prepare a tailored shortlist for you to review and help arrange viewings of the homes that suit your plans.', 'theme.template.archive_property.shortlist_text' );
    $archive_whatsapp_message    = (string) $property_archive_get_field( 'archive_whatsapp_message' );
    $archive_whatsapp_message    = $archive_whatsapp_message !== ''
      ? $archive_whatsapp_message
      : pera_ml_ui( 'Hello Pera Property, I am interested in property for sale in Istanbul. My budget, preferred area and requirements are as follows:', 'theme.template.archive_property.whatsapp_fallback_message' );
    $archive_whatsapp_url = pera_get_whatsapp_url( $archive_whatsapp_message );
  ?>
  <section class="archive-seo-content section section-soft">
    <div class="container">
      <?php if ( $archive_bottom_content !== '' ) : ?>
        <?php echo wp_kses_post( wpautop( $archive_bottom_content ) ); ?>
      <?php else : ?>
      <div class="section-header">
        <h2><?php echo esc_html( pera_ml_ui( 'Choosing where to buy in Istanbul', 'theme.template.archive_property.choosing_where_to_buy' ) ); ?></h2>
      </div>
      <p class="text-soft"><?php echo esc_html( pera_ml_ui( 'Start with your daily needs: commute, schools, transport and access to the waterfront. Compare central European-side locations such as Beşiktaş and Şişli with Kadıköy and Üsküdar on the Asian side. Zeytinburnu offers options along the Marmara coast, while Sarıyer includes Bosphorus neighbourhoods and greener residential areas. Building quality, amenities and prices vary within each district, so compare individual homes as well as locations.', 'theme.template.archive_property.compare_locations' ) ); ?></p>
      <p class="text-soft"><?php echo esc_html( pera_ml_ui( 'Before making an offer, review title deed status, building documentation, ongoing fees and the full purchase budget with qualified advisers. For an investment, compare realistic net rental income and resale prospects rather than relying on advertised returns. A shortlist of suitable homes makes it easier to compare these details and arrange viewings.', 'theme.template.archive_property.before_making_an_offer' ) ); ?></p>
      <?php endif; ?>
    </div>
  </section>

  <?php if ( $archive_cta_heading !== '' || $archive_cta_text !== '' ) : ?>
    <section class="archive-cta section section-soft">
      <div class="container">
        <?php if ( $archive_cta_heading !== '' ) : ?>
          <div class="section-header">
            <h2><?php echo esc_html( $archive_cta_heading ); ?></h2>
          </div>
        <?php endif; ?>

        <?php if ( $archive_cta_text !== '' ) : ?>
          <div class="entry-content">
            <?php echo wp_kses_post( wpautop( $archive_cta_text ) ); ?>
          </div>
        <?php endif; ?>

        <div class="btn-group">
          <a class="btn btn--solid btn--green" href="<?php echo esc_url( $archive_whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer">
            <?php echo esc_html( pera_ml_ui( 'Request my shortlist', 'theme.template.archive_property.request_my_shortlist' ) ); ?>
          </a>
          <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( function_exists( 'pera_ml_url' ) ? pera_ml_url( home_url( '/book-a-consultancy/' ) ) : home_url( '/book-a-consultancy/' ) ); ?>">
              <?php echo esc_html( pera_ml_ui( 'Book a Consultancy', 'theme.template.archive_property.book_a_consultancy' ) ); ?>
          </a>
        </div>
      </div>
    </section>
  <?php endif; ?>

<?php
$preview_faq_items = pera_parse_faq_pipe_text( (string) $property_archive_get_field( 'seo_faq_v2' ) );
if ( empty( $preview_faq_items ) ) {
	$preview_faq_items = array(
		array(
			'question' => pera_ml_ui( 'How do I choose the right district?', 'theme.template.archive_property.buyer_faq_1_question' ),
			'answer' => pera_ml_ui( 'Start with your commute, preferred side of the city, transport links and nearby amenities. Compare homes in two or three districts against your budget, building requirements and plans for living or renting.', 'theme.template.archive_property.buyer_faq_1_answer' ),
		),
		array(
			'question' => pera_ml_ui( 'What costs should I budget for beyond the asking price?', 'theme.template.archive_property.buyer_faq_2_question' ),
			'answer' => pera_ml_ui( 'Allow for applicable transfer taxes, legal advice, valuation, agency fees and any currency conversion costs. Ongoing costs may include building service charges, insurance and maintenance. Ask for an itemised estimate for the property before committing.', 'theme.template.archive_property.buyer_faq_2_answer' ),
		),
		array(
			'question' => pera_ml_ui( 'Can foreign buyers purchase a property in Istanbul?', 'theme.template.archive_property.buyer_faq_3_question' ),
			'answer' => pera_ml_ui( 'Many foreign nationals can buy property in Turkey, subject to nationality rules and restrictions on certain locations and properties. Have an independent lawyer confirm your eligibility, title deed status and required documents before paying a deposit.', 'theme.template.archive_property.buyer_faq_3_answer' ),
		),
		array(
			'question' => pera_ml_ui( 'Can I buy a property for Turkish citizenship?', 'theme.template.archive_property.buyer_faq_4_question' ),
			'answer' => pera_ml_ui( 'Some purchases may qualify for citizenship by investment, but not every listing is eligible. Current programme rules, valuation, title restrictions and documentation must be checked for the specific property with qualified legal advice.', 'theme.template.archive_property.buyer_faq_4_answer' ),
		),
		array(
			'question' => pera_ml_ui( 'How can I request a property shortlist?', 'theme.template.archive_property.buyer_faq_5_question' ),
			'answer' => pera_ml_ui( 'Send us your budget, preferred districts, property type, bedroom needs and buying timeframe. Include whether you plan to live in the home or rent it out, and our team will prepare suitable options for you to compare.', 'theme.template.archive_property.buyer_faq_5_answer' ),
		),
	);
}
?>
<section class="archive-faq section">
  <div class="container">
    <?php pera_render_faq_html( $preview_faq_items, pera_ml_ui( 'Frequently Asked Questions About Property in Istanbul', 'theme.template.archive_property.faq_heading' ) ); ?>
  </div>
</section>
