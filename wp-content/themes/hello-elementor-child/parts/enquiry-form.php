<?php
/**
 * Core Enquiry Form (reusable)
 * Location: /parts/enquiry-form.php
 *
 * Args:
 * - context        (string)  'sell' | 'rent' | 'property' | 'general'
 * - heading        (string)
 * - intro          (string)
 * - submit_label   (string)
 * - property_id    (int)
 * - property_title (string)
 * - property_url   (string)
 * - form_context   (string)  optional (e.g. 'sell-page', 'rent-page')
 * - sr_context     (string)  optional (e.g. 'bodrum_property')
 * - show_header    (bool)    optional
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $wp;

$context      = isset( $args['context'] ) ? (string) $args['context'] : 'general';
$heading      = isset( $args['heading'] ) ? (string) $args['heading'] : pera_ml_ui( 'Send an enquiry', 'theme.enquiry_form.heading' );
$intro        = isset( $args['intro'] ) ? (string) $args['intro'] : '';
$submit_label = isset( $args['submit_label'] ) ? (string) $args['submit_label'] : pera_ml_ui( 'Send my details', 'theme.enquiry_form.submit' );

$property_id    = isset( $args['property_id'] ) ? (int) $args['property_id'] : 0;
$property_title = isset( $args['property_title'] ) ? (string) $args['property_title'] : '';
$property_url   = isset( $args['property_url'] ) ? (string) $args['property_url'] : '';
$sr_context     = isset( $args['sr_context'] ) ? (string) $args['sr_context'] : '';
$show_header    = isset( $args['show_header'] ) ? (bool) $args['show_header'] : true;
$sr_status      = isset( $_GET['sr_status'] ) ? sanitize_key( (string) wp_unslash( $_GET['sr_status'] ) ) : '';

$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
$default_phone_country = '+90';
if ( strpos( (string) $locale, 'en_GB' ) === 0 ) {
  $default_phone_country = '+44';
} elseif ( strpos( (string) $locale, 'ar' ) === 0 ) {
  $default_phone_country = '+971';
}

$phone_country_value = isset( $_POST['sr_phone_country'] )
  ? sanitize_text_field( wp_unslash( (string) $_POST['sr_phone_country'] ) )
  : $default_phone_country;
$phone_national_value = isset( $_POST['sr_phone_national'] )
  ? sanitize_text_field( wp_unslash( (string) $_POST['sr_phone_national'] ) )
  : '';

$available_phone_countries = function_exists( 'peracrm_phone_dial_code_options' )
  ? (array) peracrm_phone_dial_code_options()
  : array(
      array( 'iso' => 'TR', 'dial_code' => '+90', 'label' => 'Turkey +90', 'search_tokens' => 'Turkey TR +90' ),
      array( 'iso' => 'GB', 'dial_code' => '+44', 'label' => 'United Kingdom +44', 'search_tokens' => 'United Kingdom UK GB +44' ),
      array( 'iso' => 'AE', 'dial_code' => '+971', 'label' => 'United Arab Emirates +971', 'search_tokens' => 'United Arab Emirates UAE AE +971' ),
      array( 'iso' => 'US', 'dial_code' => '+1', 'label' => 'United States +1', 'search_tokens' => 'United States USA US +1' ),
    );

$country_lookup = array();
foreach ( $available_phone_countries as $country_row ) {
  if ( ! is_array( $country_row ) ) {
    continue;
  }

  $dial_code = isset( $country_row['dial_code'] ) ? (string) $country_row['dial_code'] : '';
  if ( '' === $dial_code ) {
    continue;
  }

  $country_lookup[ $dial_code ] = true;
}

if ( ! isset( $country_lookup[ $phone_country_value ] ) ) {
  $phone_country_value = isset( $country_lookup[ $default_phone_country ] )
    ? $default_phone_country
    : '+90';
}

// For tracking/logging in email body (and your existing redirect logic)
$form_context = ( $context === 'property' )
  ? 'property'
  : ( isset( $args['form_context'] ) ? (string) $args['form_context'] : $context );

$privacy_policy_url = function_exists( 'pera_ml_url' )
  ? pera_ml_url( home_url( '/privacy-policy/' ) )
  : home_url( '/privacy-policy/' );
$privacy_policy_link = sprintf(
  '<a href="%s" target="_blank" rel="noopener">%s</a>',
  esc_url( $privacy_policy_url ),
  esc_html( pera_ml_ui( 'Privacy Policy', 'theme.enquiry_form.privacy_policy' ) )
);
$consent_html = sprintf(
  /* translators: %s: linked Privacy Policy label. */
  pera_ml_ui( 'I agree for Pera Property to contact me regarding this enquiry and to process my personal data in accordance with the %s.', 'theme.enquiry_form.consent_with_privacy_policy' ),
  $privacy_policy_link
);
?>


  <?php if ( $sr_status === 'failed' ) : ?>
    <div class="citizenship-alert citizenship-alert--error">
      <p><?php echo esc_html( pera_ml_ui( 'Sorry, your enquiry could not be submitted. Please try again.', 'theme.parts.enquiry-form.sorry_your_enquiry_could_not_be_submitted_please_try_again' ) ); ?></p>
    </div>
  <?php endif; ?>

  <?php if ( $show_header ) : ?>
    <div class="enquiry-cta-header m-sm">
      <h2><?php echo esc_html( $heading ); ?></h2>
      <?php if ( $intro ) : ?>
        <p><?php echo esc_html( $intro ); ?></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  
    <style>
      .sr-hp-field {
        position: absolute;
        left: -9999px;
        width: 1px;
        height: 1px;
        overflow: hidden;
      }
    </style>


    <form class="enquiry-cta-form m-sm" action="" method="post">
      
        <input type="hidden" name="sr_action" value="1">
        <input type="hidden" name="form_context" value="<?php echo esc_attr( $form_context ); ?>">
        <?php if ( $sr_context ) : ?>
          <input type="hidden" name="sr_context" value="<?php echo esc_attr( $sr_context ); ?>">
        <?php endif; ?>

        <?php wp_nonce_field( 'pera_seller_landlord_enquiry', 'sr_nonce' ); ?>
        <!-- Honeypot field (spam bots only) -->
        <div class="sr-hp-field" aria-hidden="true">
          <label for="sr_company"><?php echo esc_html( pera_ml_ui( 'Company', 'theme.enquiry_form.company' ) ); ?></label>
          <input
            type="text"
            name="sr_company"
            id="sr_company"
            value=""
            autocomplete="off"
            tabindex="-1"
          >
        </div>

    <?php if ( $context === 'property' ) : ?>
      <input type="hidden" name="sr_property_id" value="<?php echo esc_attr( $property_id ); ?>">
      <input type="hidden" name="sr_property_title" value="<?php echo esc_attr( $property_title ); ?>">
      <input type="hidden" name="sr_property_url" value="<?php echo esc_url( $property_url ); ?>">
    <?php endif; ?>

    <?php
    /**
     * INTENT HANDLING RULES:
     * - sell page: no radios; hard-set intent to "sell"
     * - rent page: show radios for "rent" and "short-term" only
     * - general: show full radios (sell/rent/short-term)
     * - property: no radios at all
     */
    if ( $context === 'sell' ) : ?>
      <input type="hidden" name="sr_intent" value="sell">
    <?php endif; ?>

    <div class="cta-fieldset">

      <div class="cta-field">
        <label class="cta-label" for="sr_name"><?php echo esc_html( pera_ml_ui( 'Full name', 'theme.enquiry_form.full_name' ) ); ?></label>
        <input type="text" id="sr_name" name="sr_name" class="cta-control" required placeholder="<?php echo esc_attr( pera_ml_ui( 'Your full name', 'theme.enquiry_form.full_name_placeholder' ) ); ?>">
      </div>

      <div class="cta-field">
        <label class="cta-label" for="sr_email"><?php echo esc_html( pera_ml_ui( 'Email', 'theme.enquiry_form.email' ) ); ?></label>
        <input type="email" id="sr_email" name="sr_email" class="cta-control" required placeholder="name@example.com">
      </div>

      <div class="cta-field">
        <span class="cta-label"><?php echo esc_html( pera_ml_ui( 'Mobile / WhatsApp', 'theme.enquiry_form.mobile_whatsapp' ) ); ?></span>
        <div class="cta-phone-row sr-phone-row">
          <!-- phone helper: <?php echo function_exists( 'peracrm_phone_dial_code_options' ) ? 'yes' : 'no'; ?> -->
          <!-- phone count: <?php echo (int) count( $available_phone_countries ); ?> -->
          <select id="sr_phone_country" name="sr_phone_country" class="cta-control cta-control--phone-country" required aria-label="<?php echo esc_attr( pera_ml_ui( 'Country code', 'theme.enquiry_form.country_code' ) ); ?>" data-phone-country-select="1">
            <?php foreach ( $available_phone_countries as $country_row ) :
              $country_value = isset( $country_row['dial_code'] ) ? (string) $country_row['dial_code'] : '';
              $country_label = isset( $country_row['label'] ) ? (string) $country_row['label'] : $country_value;
              $country_iso   = isset( $country_row['iso'] ) ? (string) $country_row['iso'] : '';
              $country_tokens = isset( $country_row['search_tokens'] ) ? (string) $country_row['search_tokens'] : '';
              if ( '' === $country_value ) {
                continue;
              }
            ?>
              <option value="<?php echo esc_attr( $country_value ); ?>" data-country-iso="<?php echo esc_attr( $country_iso ); ?>" data-country-search="<?php echo esc_attr( $country_tokens ); ?>" <?php selected( $phone_country_value, $country_value ); ?>><?php echo esc_html( $country_label ); ?></option>
            <?php endforeach; ?>
          </select>

          <input
            type="text"
            id="sr_phone_national"
            name="sr_phone_national"
            class="cta-control"
            required
            inputmode="tel"
            autocomplete="tel-national"
            placeholder="<?php echo esc_attr( pera_ml_ui( 'Phone number', 'theme.enquiry_form.phone_placeholder' ) ); ?>"
            aria-label="<?php echo esc_attr( pera_ml_ui( 'Phone number', 'theme.enquiry_form.phone_label' ) ); ?>"
            value="<?php echo esc_attr( $phone_national_value ); ?>"
          >
        </div>
      </div>

      <?php if ( $context === 'rent' ) : ?>

        <div class="cta-field">
          <span class="cta-label"><?php echo esc_html( pera_ml_ui( 'Rental type', 'theme.enquiry_form.rental_type' ) ); ?></span>
          <div class="cta-options">
            <label class="cta-checkbox">
              <input type="radio" name="sr_intent" value="rent" checked>
              <span><?php echo esc_html( pera_ml_ui( 'Long-term rental', 'theme.enquiry_form.long_term_rental' ) ); ?></span>
            </label>
            <label class="cta-checkbox">
              <input type="radio" name="sr_intent" value="short-term">
              <span><?php echo esc_html( pera_ml_ui( 'Short-term rental / Airbnb', 'theme.enquiry_form.short_term_rental' ) ); ?></span>
            </label>
          </div>
        </div>

      <?php elseif ( $context === 'general' ) : ?>

        <div class="cta-field">
          <span class="cta-label"><?php echo esc_html( pera_ml_ui( 'I would like to', 'theme.enquiry_form.intent' ) ); ?></span>
          <div class="cta-options">
            <label class="cta-checkbox">
              <input type="radio" name="sr_intent" value="sell" checked>
              <span><?php echo esc_html( pera_ml_ui( 'Sell my property', 'theme.enquiry_form.sell_property' ) ); ?></span>
            </label>
            <label class="cta-checkbox">
              <input type="radio" name="sr_intent" value="rent">
              <span><?php echo esc_html( pera_ml_ui( 'Rent out my property (long-term)', 'theme.enquiry_form.rent_property' ) ); ?></span>
            </label>
            <label class="cta-checkbox">
              <input type="radio" name="sr_intent" value="short-term">
              <span><?php echo esc_html( pera_ml_ui( 'Short-term rental / Airbnb', 'theme.enquiry_form.short_term_rental' ) ); ?></span>
            </label>
          </div>
        </div>

      <?php endif; ?>

      <?php if ( $context !== 'property' ) : ?>

        <div class="cta-field">
          <label class="cta-label"><?php echo esc_html( pera_ml_ui( 'Property location', 'theme.enquiry_form.property_location' ) ); ?></label>
          <input type="text" name="sr_location" class="cta-control" placeholder="<?php echo esc_attr( pera_ml_ui( 'District / neighbourhood (e.g. Beşiktaş – Dikilitaş)', 'theme.enquiry_form.location_placeholder' ) ); ?>">
        </div>

        <div class="cta-field">
          <label class="cta-label"><?php echo esc_html( pera_ml_ui( 'Property details', 'theme.enquiry_form.property_details' ) ); ?></label>
          <textarea name="sr_details" rows="4" class="cta-control" placeholder="<?php echo esc_attr( pera_ml_ui( 'Apartment or villa, number of bedrooms, approximate size, building age, tenancy status, etc.', 'theme.enquiry_form.details_placeholder' ) ); ?>"></textarea>
        </div>

        <div class="cta-field">
          <label class="cta-label">
            <?php echo esc_html( ( $context === 'rent' ) ? pera_ml_ui( 'Rent expectations (optional)', 'theme.enquiry_form.rent_expectations' ) : pera_ml_ui( 'Price expectations (optional)', 'theme.enquiry_form.price_expectations' ) ); ?>
          </label>
          <input type="text" name="sr_expectations" class="cta-control" placeholder="<?php echo esc_attr( ( $context === 'rent' ) ? pera_ml_ui( 'Your target monthly rent (if you have one)', 'theme.enquiry_form.rent_target_placeholder' ) : pera_ml_ui( 'Your target sale price (if you have one)', 'theme.enquiry_form.sale_target_placeholder' ) ); ?>">
        </div>

        <div class="cta-field">
          <label class="cta-label" for="sr_message"><?php echo esc_html( pera_ml_ui( 'Message (optional)', 'theme.enquiry_form.message_optional' ) ); ?></label>
          <textarea id="sr_message" name="sr_message" rows="4" class="cta-control" placeholder="<?php echo esc_attr( pera_ml_ui( 'Anything else we should know?', 'theme.enquiry_form.message_optional_placeholder' ) ); ?>"></textarea>
        </div>

      <?php else : ?>

        <div class="cta-field">
          <label class="cta-label" for="sr_message"><?php echo esc_html( pera_ml_ui( 'Message', 'theme.enquiry_form.message' ) ); ?></label>
          <textarea id="sr_message" name="sr_message" rows="4" class="cta-control" placeholder="<?php echo esc_attr( pera_ml_ui( 'Tell us what you need (availability, brochure request, viewing, questions, etc.).', 'theme.enquiry_form.message_placeholder' ) ); ?>"></textarea>
        </div>

      <?php endif; ?>

        <div class="enquiry-cta-footer">
            <label class="cta-checkbox">
              <input type="checkbox" name="sr_consent" value="1" required>
              <span>
                <?php echo wp_kses( $consent_html, array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ); ?>
              </span>
            </label>
    
    
          <button type="submit" class="btn btn--solid btn--green">
            <?php echo esc_html( $submit_label ); ?>
          </button>
        </div>

    </div>

    

  </form>
