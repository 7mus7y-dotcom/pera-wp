<?php
/**
 * Template Name: Sell with Pera
 * Description: Landing page for property owners who want to sell with Pera Property.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


$hero_heading = $args['hero_heading'] ?? 'Talk to Pera about your Istanbul plans';
$hero_intro   = $args['hero_intro']   ?? 'Whether you’re buying, selling, or renting in Istanbul, our team can walk you through the numbers, the legal steps, and the neighbourhoods that fit your strategy.';

$sell_with_pera_faq_items = array(
    array(
        'question' => 'How do I sell my property in Istanbul?',
        'answer'   => 'Start with a valuation and sales strategy. We then prepare marketing, coordinate viewings, negotiate offers and support the title deed transfer process until completion.',
    ),
    array(
        'question' => 'How long does it take to sell a property in Istanbul?',
        'answer'   => 'Timelines vary by district, price point and property condition. Well-priced homes in active areas can attract offers quickly, while premium listings may require a longer marketing window.',
    ),
    array(
        'question' => 'What documents do I need to sell property in Turkey?',
        'answer'   => 'Most sellers need the tapu, ID or passport, tax number and supporting compliance documents such as DASK or iskan where relevant, plus debt and encumbrance checks.',
    ),
    array(
        'question' => 'Can I sell my Istanbul property if I live abroad?',
        'answer'   => 'Yes. We can manage valuation, marketing and viewings remotely, and coordinate power of attorney and lawyer-led documentation so the sale can progress while you are overseas.',
    ),
    array(
        'question' => 'How much does Pera Property charge to sell my property?',
        'answer'   => 'Pera Property\'s standard sales agency fee is 4% unless otherwise agreed in writing.',
    ),
    array(
        'question' => 'How is my Istanbul property valuation calculated?',
        'answer'   => 'We assess recent comparables where available, micro-location, building condition, floor and view quality, layout, demand trends and rental/investment potential.',
    ),
    array(
        'question' => 'Do I need a lawyer to sell property in Turkey?',
        'answer'   => 'A lawyer is not always legally mandatory, but many sellers choose one for contract review, tax coordination and risk management during the transfer process.',
    ),
    array(
        'question' => 'Can you manage viewings if the property is tenanted?',
        'answer'   => 'Yes. We coordinate with tenants or caretakers, schedule qualified buyer visits and keep disruption to occupants as limited as possible.',
    ),
);

add_action( 'wp_head', static function () use ( $sell_with_pera_faq_items ) {
    $faq_entities = array();

    foreach ( $sell_with_pera_faq_items as $faq_item ) {
        $question = isset( $faq_item['question'] ) ? trim( (string) $faq_item['question'] ) : '';
        $answer   = isset( $faq_item['answer'] ) ? trim( (string) $faq_item['answer'] ) : '';

        if ( $question === '' || $answer === '' ) {
            continue;
        }

        $faq_entities[] = array(
            '@type' => 'Question',
            'name'  => $question,
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text'  => $answer,
            ),
        );
    }

    if ( empty( $faq_entities ) ) {
        return;
    }

    $faq_schema = array(
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $faq_entities,
    );

    $GLOBALS['pera_schema_faq_emitted'] = true;
    echo '<script type="application/ld+json">' . wp_json_encode( $faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}, 12 );

get_header();
?>

<main id="primary" class="site-main">

    <!-- =====================================
     HERO (SELL WITH PERA)
     Canonical structure + existing content
     ===================================== -->
        <section class="hero hero--left hero--sell" id="sell-hero">
        
          <div class="hero__media" aria-hidden="true">
            <?php
              // If you later set a featured image for this page, it will be used.
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
                // Fallback background (vopbesiktas.svg uploaded to WP)
                echo wp_get_attachment_image(
                  55756,
                  'full',
                  false,
                  array(
                    'class'    => 'hero-media',
                    'loading'  => 'eager',
                    'decoding' => 'async',
                  )
                );
              }
            ?>
            <div class="hero-overlay" aria-hidden="true"></div>
          </div>
        
          <div class="hero-content">
            <h1>Sell Property in Istanbul with a Trusted Local Agency</h1>
        
            <p class="lead">
              Get a free valuation from Istanbul property market specialists who manage pricing, marketing,
              viewings, negotiation and the title deed process, with dedicated support for owners based abroad.
            </p>
        
            <div class="hero-actions">
              <a href="#contact" class="btn btn--solid btn--green">
                Request a free valuation
              </a>
        
              <a href="#process" class="btn btn--solid btn--blue">
                How our selling process works
              </a>
        
              <a
                href="<?php echo esc_url( pera_get_whatsapp_url( 'Hello I would like to sell my property with Pera Property' ) ); ?>"
                target="_blank"
                rel="noopener"
                class="btn btn-icon-circle btn-whatsapp"
                aria-label="Contact Pera Property via WhatsApp" data-whatsapp="1" data-whatsapp-type="service_cta" data-track-channel="whatsapp" data-track-intent="high" data-track-source="template" data-track-context="sell_with_pera_hero" data-track-ga4-event="whatsapp_click" data-track-crm-event="whatsapp_click"
              >
                <svg class="icon" aria-hidden="true">
                  <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-whatsapp' ); ?>"></use>
                </svg>
              </a>
            </div>
          </div>
        
        </section>


    <!-- CONTENT PANEL (overlapping hero) -->
    <section class="content-panel content-panel--overlap-hero">
        <div class="content-panel-box">
            <div class="content-panel-grid">
                <!-- LEFT: TEXT -->
                <div>
                    <header class="section-header">
                        <h2>Why sell your property with Pera?</h2>
                        <p>
                            We are an Istanbul-focused, data-driven agency that treats every listing
                            like a bespoke investment project. Our goal is simple: secure the right
                            buyer at the right price, on the right terms.
                        </p>
                    </header>

                    <ul class="checklist">
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Honest valuation based on real comparable data
                        </li>
                    
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Access to both local and international buyers
                        </li>
                    
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Professional presentation: photos, videos, floor plans
                        </li>
                    
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Negotiation, paperwork and follow-up handled end-to-end
                        </li>
                    </ul>


                    <div class="signoff-card">
                        <div class="signoff-avatar">
                                        <?php
                                        echo wp_get_attachment_image(
                                            55700,
                                            'full',
                                            false,
                                            array(
                                                'class'   => '',
                                                'alt'     => 'Pera Property Director',
                                                'loading' => 'lazy',
                                                'decoding'=> 'async',
                                            )
                                        );
                                        ?>
                                    </div>
                        <div class="signoff-text">
                            <h5>Your dedicated consultant</h5>
                            <p>
                                One point of contact from first valuation to key handover. Direct,
                                clear and honest communication throughout the process.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: MEDIA / VISUAL -->
                <div>
                    <div class="media-frame media-frame--image-fill">
                        <?php
                        echo wp_get_attachment_image(
                            55704,
                            'full',
                            false,
                            array(
                                'class'    => 'media-image', // IMPORTANT: this class
                                'loading'  => 'lazy',
                                'decoding' => 'async',
                                'alt'      => esc_attr(
                                    'Istanbul real estate market overview by Pera Property'
                                ),
                            )
                        );
                        ?>
                    </div>
                </div>





            </div>
        </div>
    </section>

    <!-- WHY SELL WITH US – FEATURE GRID -->
    <section class="section">
        <div class="section-header section-header--center">
            <h2>What you gain when you sell with Pera</h2>
            <p>
                We combine Istanbul market experience, international investor reach and a
                structured selling process to protect both your price and your time.
            </p>
        </div>

        <div class="feature-grid">
            <!-- FEATURE 1 -->
            <article class="feature-card">
                <div class="feature-card-header">
                    <div class="feature-card-icon">
                        <svg class="icon" aria-hidden="true">
                            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-map' ); ?>"></use>
                        </svg>
                    </div>
                    <h3>Accurate pricing strategy</h3>
                </div>
                <div class="feature-card-body">
                    <p>
                        We benchmark your property against recent sales, active listings and
                        investor demand in your specific micro-location, not just the district
                        average.
                    </p>
                </div>
            </article>

            <!-- FEATURE 2 -->
            <article class="feature-card">
                <div class="feature-card-header">
                    <div class="feature-card-icon">
                        <svg class="icon" aria-hidden="true">
                            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-pdf' ); ?>"></use>
                        </svg>
                    </div>
                    <h3>Professional marketing</h3>
                </div>
                <div class="feature-card-body">
                    <p>
                        Clean photography, clear plans, bilingual presentation and targeted
                        campaigns ensure your property stands out instead of getting lost among
                        generic listings.
                    </p>
                </div>
            </article>

            <!-- FEATURE 3 -->
            <article class="feature-card">
                <div class="feature-card-header">
                    <div class="feature-card-icon">
                        <svg class="icon" aria-hidden="true">
                            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-map' ); ?>"></use>
                        </svg>
                    </div>
                    <h3>Serious buyers only</h3>
                </div>
                <div class="feature-card-body">
                    <p>
                        We pre-qualify buyers, manage viewing schedules and filter out “property
                        tourists”, so the people walking through your door are real prospects.
                    </p>
                </div>
            </article>
        </div>
    </section>

    <!-- OUR PROCESS – INFO STEPS -->
    <section id="process" class="section section-soft">
        <div class="section-header section-header--center">
            <h2>How the selling process works</h2>
            <p>
                A clear, structured roadmap from first chat to completed sale. You always know
                what is happening, and what comes next.
            </p>
        </div>

        <div class="info-steps">
            <!-- STEP 1 -->
            <div class="info-step">
                <div class="info-step-icon">
                    <span class="info-step-number">1</span>
                </div>
                <div class="info-step-body">
                    <h3 class="info-step-title">Initial conversation & property review</h3>
                    <p class="info-step-text">
                        We listen to your goals, review your property details and documents,
                        and advise whether a sale, rental or hold strategy makes most sense.
                    </p>
                </div>
            </div>

            <!-- STEP 2 -->
            <div class="info-step">
                <div class="info-step-icon">
                    <span class="info-step-number">2</span>
                </div>
                <div class="info-step-body">
                    <h3 class="info-step-title">Valuation & pricing strategy</h3>
                    <p class="info-step-text">
                        We prepare a realistic price range backed by comps and demand data,
                        then agree the asking price and negotiation boundaries with you.
                    </p>
                </div>
            </div>

            <!-- STEP 3 -->
            <div class="info-step">
                <div class="info-step-icon">
                    <span class="info-step-number">3</span>
                </div>
                <div class="info-step-body">
                    <h3 class="info-step-title">Marketing & viewings</h3>
                    <p class="info-step-text">
                        Your listing goes live across our channels and direct investor network.
                        We handle enquiries, schedule viewings and keep you updated with
                        feedback.
                    </p>
                </div>
            </div>

            <!-- STEP 4 -->
            <div class="info-step">
                <div class="info-step-icon">
                    <span class="info-step-number">4</span>
                </div>
                <div class="info-step-body">
                    <h3 class="info-step-title">Offer, negotiation & paperwork</h3>
                    <p class="info-step-text">
                        Once offers arrive, we negotiate terms in your favour, coordinate the
                        sales contract, legal checks and tapu process together with your chosen
                        lawyer or our partner firms.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- WHAT WE HANDLE FOR YOU – 2 COL LAYOUT -->
    <section class="section">
        <div class="container grid-2">
            <div>
                <h2>Everything taken care of, from start to finish.</h2>
                <p>
                    Selling a property in Istanbul doesn’t have to be chaotic. We project-manage
                    the entire journey so you can focus on your life, not paperwork and phone calls.
                </p>
            </div>
            <div>
                <ul class="checklist">
                  <li>
                    <svg class="icon icon-tick" aria-hidden="true">
                      <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                    </svg>
                    Pre-sale advice on minor improvements that increase value
                  </li>
                
                  <li>
                    <svg class="icon icon-tick" aria-hidden="true">
                      <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                    </svg>
                    Document check: tapu, plans, iskan, mortgage and encumbrances
                  </li>
                
                  <li>
                    <svg class="icon icon-tick" aria-hidden="true">
                      <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                    </svg>
                    Professional photos and listing preparation
                  </li>
                
                  <li>
                    <svg class="icon icon-tick" aria-hidden="true">
                      <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                    </svg>
                    Coordinating viewings with tenants or caretakers where relevant
                  </li>
                
                  <li>
                    <svg class="icon icon-tick" aria-hidden="true">
                      <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                    </svg>
                    Negotiation strategy and best-offer analysis
                  </li>
                
                  <li>
                    <svg class="icon icon-tick" aria-hidden="true">
                      <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                    </svg>
                    Guidance on tax, fees and timelines together with your advisors
                  </li>
                </ul>

            </div>
        </div>

    <section class="section">
        <div class="container">
            <header class="section-header section-header--center">
                <h2>How to sell your property in Istanbul</h2>
            </header>

            <div class="content-panel-box">
                <p>Our seller journey starts with an initial review of your property, goals and timing, then moves to a realistic valuation based on Istanbul market conditions and your building profile.</p>
                <p>Once we agree the strategy, we help prepare key documents, launch professional marketing, coordinate buyer viewings and report back with feedback that supports pricing decisions.</p>
                <p>When offers arrive, we negotiate terms in line with your priorities, coordinate due diligence and support the title deed transfer process so the sale completes smoothly.</p>
            </div>
        </div>
    </section>

    <section class="section section-soft">
        <div class="container">
            <header class="section-header section-header--center">
                <h2>Free Istanbul property valuation</h2>
            </header>

            <div class="content-panel-box">
                <p>Our free valuation combines recent comparable listings and sales where available with on-the-ground insight from active buyer demand in your micro-location.</p>
                <p>We assess building age and condition, floor level, view quality, layout efficiency and any outdoor space, then adjust for demand from end-users and investors. We also consider rental and investment potential to recommend a defensible asking range for today’s Istanbul market.</p>
            </div>
        </div>
    </section>

    <section class="section section-soft">
        <div class="container">
            <header class="section-header section-header--center">
                <h2>Typical timeline for selling property in Istanbul</h2>
                <p>Every sale moves at a different pace depending on district, price point, property condition and buyer demand. We keep you updated at each stage so decisions stay clear and practical.</p>
            </header>

            <div class="info-steps">
                <div class="info-step">
                    <div class="info-step-icon"><span class="info-step-number">1</span></div>
                    <div class="info-step-body">
                        <h3 class="info-step-title">Initial review and valuation</h3>
                        <p class="info-step-text">We review your property details, location, key documents and seller goals, then agree an indicative valuation range for the current market.</p>
                    </div>
                </div>

                <div class="info-step">
                    <div class="info-step-icon"><span class="info-step-number">2</span></div>
                    <div class="info-step-body">
                        <h3 class="info-step-title">Listing requirements and document check</h3>
                        <p class="info-step-text">Before launch, we usually verify tapu, ID/passport, tax number, DASK where applicable, iskan where relevant, mortgage or debt checks, and power of attorney documentation for overseas owners.</p>
                    </div>
                </div>

                <div class="info-step">
                    <div class="info-step-icon"><span class="info-step-number">3</span></div>
                    <div class="info-step-body">
                        <h3 class="info-step-title">Marketing preparation</h3>
                        <p class="info-step-text">We prepare professional photos, video where suitable, floor plans, bilingual listing copy and a launch pricing strategy aligned with your timeline.</p>
                    </div>
                </div>

                <div class="info-step">
                    <div class="info-step-icon"><span class="info-step-number">4</span></div>
                    <div class="info-step-body">
                        <h3 class="info-step-title">Launch and qualified viewings</h3>
                        <p class="info-step-text">Your listing goes live, buyer enquiries are screened, and viewings are arranged with you, your tenant or your caretaker as needed.</p>
                    </div>
                </div>

                <div class="info-step">
                    <div class="info-step-icon"><span class="info-step-number">5</span></div>
                    <div class="info-step-body">
                        <h3 class="info-step-title">Offer negotiation</h3>
                        <p class="info-step-text">We compare offers by price, payment terms, deposit structure, timing and buyer readiness so you can choose the strongest path to completion.</p>
                    </div>
                </div>

                <div class="info-step">
                    <div class="info-step-icon"><span class="info-step-number">6</span></div>
                    <div class="info-step-body">
                        <h3 class="info-step-title">Contract, legal checks and tapu transfer</h3>
                        <p class="info-step-text">We coordinate with your lawyer through contract checks, title deed office steps, final payment and handover, while keeping communication consistent until completion.</p>
                    </div>
                </div>
            </div>

            <div class="content-panel-box" style="margin-top:16px;">
                <p><strong>Before we list your property, we usually review:</strong></p>
                <ul class="checklist">
                    <li><svg class="icon icon-tick" aria-hidden="true"><use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use></svg>Tapu/title deed and ownership details</li>
                    <li><svg class="icon icon-tick" aria-hidden="true"><use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use></svg>Building age, condition and occupancy/tenant status</li>
                    <li><svg class="icon icon-tick" aria-hidden="true"><use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use></svg>Asking price expectations and launch strategy</li>
                    <li><svg class="icon icon-tick" aria-hidden="true"><use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use></svg>DASK and iskan where relevant</li>
                    <li><svg class="icon icon-tick" aria-hidden="true"><use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use></svg>Debts, mortgages or site-management dues</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container grid-2">
            <div>
                <h2>Documents needed to sell property in Turkey</h2>
                <p>Requirements can vary by property and ownership structure, but sellers should usually prepare the following before launch:</p>
            </div>
            <div>
                <ul class="checklist">
                    <li><svg class="icon icon-tick" aria-hidden="true"><use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use></svg>Tapu (title deed)</li>
                    <li><svg class="icon icon-tick" aria-hidden="true"><use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use></svg>Passport or Turkish ID</li>
                    <li><svg class="icon icon-tick" aria-hidden="true"><use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use></svg>Tax number</li>
                    <li><svg class="icon icon-tick" aria-hidden="true"><use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use></svg>DASK (earthquake insurance), where applicable</li>
                    <li><svg class="icon icon-tick" aria-hidden="true"><use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use></svg>Iskan (habitation certificate), where relevant</li>
                    <li><svg class="icon icon-tick" aria-hidden="true"><use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use></svg>Mortgage, debt and encumbrance checks</li>
                    <li><svg class="icon icon-tick" aria-hidden="true"><use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use></svg>Power of attorney documents if the seller is based abroad</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="section section-soft">
        <div class="container grid-2">
            <div>
                <h2>Seller fees, taxes and commission</h2>
                <p><strong>Pera Property’s standard sales agency fee is 4% unless otherwise agreed in writing.</strong></p>
                <p>Every sale is different, so we recommend confirming your total transaction costs early and reviewing tax points with qualified advisors before signing.</p>
            </div>
            <div>
                <ul class="checklist">
                    <li><svg class="icon icon-tick" aria-hidden="true"><use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use></svg>Title deed expenses and transfer costs</li>
                    <li><svg class="icon icon-tick" aria-hidden="true"><use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use></svg>Your capital gains tax position</li>
                    <li><svg class="icon icon-tick" aria-hidden="true"><use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use></svg>Independent legal and accounting advice</li>
                    <li><svg class="icon icon-tick" aria-hidden="true"><use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use></svg>Outstanding mortgage or site-management debts</li>
                </ul>
                <p>We provide practical sale guidance, but formal tax and legal advice should come from your lawyer or accountant.</p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <header class="section-header section-header--center">
                <h2>Selling your Istanbul property from abroad</h2>
            </header>
            <div class="content-panel-box">
                <p>If you live outside Turkey, we can coordinate valuation remotely, arrange photos and videos, and manage access with tenants or caretakers for buyer viewings.</p>
                <p>Our team handles offer negotiation and can coordinate with your lawyer on power of attorney and transaction documents, while supporting you through each tapu milestone until completion.</p>
                <p>To plan next steps, <a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">contact us</a>, learn more <a href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>">about our team</a>, or explore our <a href="<?php echo esc_url( home_url( '/rent-your-istanbul-real-estate/' ) ); ?>">property management services in Istanbul</a>. You can also read our <a href="<?php echo esc_url( home_url( '/category/regional-guides/' ) ); ?>">Istanbul area guides</a> for district-level demand insights.</p>
            </div>
        </div>
    </section>

    <section class="faq-section section section-soft">
        <div class="container">
            <h2>Frequently asked questions about selling property in Istanbul</h2>

            <div class="faq-accordion">
                <?php
                $faq_index = 0;
                foreach ( $sell_with_pera_faq_items as $faq_item ) :
                    $question = isset( $faq_item['question'] ) ? trim( (string) $faq_item['question'] ) : '';
                    $answer   = isset( $faq_item['answer'] ) ? trim( (string) $faq_item['answer'] ) : '';
                    if ( $question === '' || $answer === '' ) {
                        continue;
                    }
                ?>
                    <details class="faq-item"<?php echo $faq_index === 0 ? ' open' : ''; ?>>
                        <summary><?php echo esc_html( $question ); ?></summary>
                        <div class="faq-answer">
                            <?php echo wp_kses_post( wpautop( $answer ) ); ?>
                        </div>
                    </details>
                    <?php $faq_index++; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>


    <!-- ABOUT PERA -->
    <?php get_template_part( 'parts/about-pera' ); ?>

    
    <section class="section section-soft" id="contact">
        <div class="content-panel-box">
    
            <!-- =========================
                 1) HERO CTA GRID (LEFT TEXT + RIGHT IMAGE)
                 ========================== -->
            <div class="content-panel-grid">
    
                <!-- LEFT COLUMN -->
                <div>
                    <header class="section-header">
                        <h2><?php echo esc_html( $hero_heading ); ?></h2>
                        <p><?php echo esc_html( $hero_intro ); ?></p>
                    </header>
    
                    <ul class="checklist">
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Reliable, data-driven advice.
                        </li>
    
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            On-the-ground Istanbul expertise.
                        </li>
    
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Multi-lingual support.
                        </li>
                    </ul>
                </div>
    
                <!-- RIGHT COLUMN -->
                <div class="media-frame">
    
                    <!-- RESPONSIVE BACKGROUND IMAGE -->
                    <div class="media-frame__bg">
                        <?php
                        echo wp_get_attachment_image(
                            55686,
                            'large',
                            false,
                            array(
                                'class'    => 'media-frame__bg-img',
                                'loading'  => 'lazy',
                                'decoding' => 'async',
                                'alt'      => 'Isometric illustration of Beşiktaş'
                            )
                        );
                        ?>
                    </div>
    
                    <div class="hero-overlay"></div>
    
                    <div class="hero-content section--center">
                        <h3 class="text-light">Speak with a Consultant</h3>
    
                        <div class="hero-actions flex-center">
                            <a href="https://www.peraproperty.com/contact-us/" class="btn btn--solid btn--blue">
                                Book a consultation
                            </a>
    
                            <a href="<?php echo esc_url( pera_get_whatsapp_url( 'Hello Pera Property, I\'d like to discuss Istanbul real estate.' ) ); ?>"
                               class="btn btn--solid btn--green"
                               data-whatsapp="1"
                               data-whatsapp-type="service_cta"
                               data-track-channel="whatsapp"
                               data-track-intent="high"
                               data-track-source="template"
                               data-track-context="sell_with_pera"
                               data-track-ga4-event="whatsapp_click"
                               data-track-crm-event="whatsapp_click">
                                Chat on WhatsApp
                            </a>
                        </div>
                    </div>
    
                </div><!-- .media-frame -->
    
            </div><!-- .content-panel-grid -->

            <div>
    
                <?php if ( isset( $_GET['sr_status'] ) && $_GET['sr_status'] === 'sent' ) : ?>
                    <div class="form-success">
                        Thank you – we have received your details. A Pera consultant will contact you shortly.
                    </div>
                <?php endif; ?>
    

    
                <?php
                get_template_part('parts/enquiry-form', null, array(
                  'context'      => 'sell',
                  'heading'      => 'Request a free appraisal',
                  'intro'        => 'Share a few details and we will prepare an initial sale strategy and price guidance for your property in Istanbul.',
                  'submit_label' => 'Send my details',
                  'form_context' => 'sell-page',
                ));

                ?>
    
    
                
            </div><!-- .enquiry-cta -->
    
    
        </div><!-- .content-panel-box -->
    </section>


</main>

<?php
get_footer();
