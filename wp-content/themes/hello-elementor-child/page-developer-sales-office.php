<?php
/**
 * Template Name: Developer Sales & Marketing Solutions
 * Description: Commercial marketing and sales-office solutions for Istanbul developers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$developer_faq_items = array(
	array(
		'question' => pera_ml_ui( 'What does Pera manage for a development project?', 'theme.template.page_developer_sales_office.faq_manage_question' ),
		'answer' => pera_ml_ui( 'Pera can manage the connected commercial operation: positioning, launch planning, marketing, lead generation, CRM, buyer qualification, broker coordination, reservations, pipeline management and reporting.', 'theme.template.page_developer_sales_office.faq_manage_answer' ),
	),
	array(
		'question' => pera_ml_ui( 'Can Pera work alongside an in-house sales team?', 'theme.template.page_developer_sales_office.faq_in_house_team_question' ),
		'answer' => pera_ml_ui( 'Yes. We can provide the complete sales-office operation or work alongside your existing team with agreed responsibilities, shared processes and clear reporting.', 'theme.template.page_developer_sales_office.faq_in_house_team_answer' ),
	),
	array(
		'question' => pera_ml_ui( 'Which buyer markets can Pera target?', 'theme.template.page_developer_sales_office.faq_buyer_markets_question' ),
		'answer' => pera_ml_ui( 'Depending on the project, we can build routes for Turkish direct buyers, domestic agencies, international digital audiences, overseas brokers, referral partners and investor buyers, including citizenship-focused demand where eligible and relevant.', 'theme.template.page_developer_sales_office.faq_buyer_markets_answer' ),
	),
	array(
		'question' => pera_ml_ui( 'How is the commercial arrangement structured?', 'theme.template.page_developer_sales_office.faq_commercial_arrangement_question' ),
		'answer' => pera_ml_ui( 'The structure is tailored to the project scope, stage and sales targets. It may combine defined setup and operating services with performance-based commercial terms agreed before launch.', 'theme.template.page_developer_sales_office.faq_commercial_arrangement_answer' ),
	),
	array(
		'question' => pera_ml_ui( 'When should we involve Pera?', 'theme.template.page_developer_sales_office.faq_timing_question' ),
		'answer' => pera_ml_ui( 'Ideally, involve us before positioning, pricing and launch decisions are final. We can also enter an active project to diagnose gaps, rebuild the pipeline and improve sales control.', 'theme.template.page_developer_sales_office.faq_timing_answer' ),
	),
);

add_action( 'wp_head', static function () use ( $developer_faq_items ) {
	$entities = array();

	foreach ( $developer_faq_items as $item ) {
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => $item['question'],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $item['answer'],
			),
		);
	}

	$schema = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => $entities,
	);

	$GLOBALS['pera_schema_faq_emitted'] = true;
	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}, 12 );

get_header();
?>

<main id="primary" class="site-main">
	<section class="hero hero--left hero--compact">
		<div class="hero__media" aria-hidden="true">
			<?php
			$hero_image_id = get_post_thumbnail_id() ?: 55756;
			echo wp_get_attachment_image(
				$hero_image_id,
				'full',
				false,
				array(
					'class'    => 'hero-media',
					'loading'  => 'eager',
					'decoding' => 'async',
				)
			);
			?>
			<div class="hero-overlay" aria-hidden="true"></div>
		</div>
		<div class="hero-content">
			<span class="pill pill--brand pill--sm"><?php echo esc_html( pera_ml_ui( 'For developers', 'theme.template.page_developer_sales_office.hero_eyebrow' ) ); ?></span>
			<h1><?php echo esc_html( pera_ml_ui( 'Your project deserves a sales operation — not just a marketing campaign.', 'theme.template.page_developer_sales_office.hero_heading' ) ); ?></h1>
			<p class="lead"><?php echo esc_html( pera_ml_ui( 'Pera plans and operates the commercial function behind a development: positioning, buyer acquisition, sales conversion, broker management and clear reporting from launch to closing.', 'theme.template.page_developer_sales_office.hero_intro' ) ); ?></p>
			<div class="hero-actions">
				<a class="btn btn--solid btn--green" href="#contact"><?php echo esc_html( pera_ml_ui( 'Discuss your project', 'theme.template.page_developer_sales_office.hero_discuss_project' ) ); ?></a>
				<a class="btn btn--solid btn--blue" href="#solution"><?php echo esc_html( pera_ml_ui( 'Explore the solution', 'theme.template.page_developer_sales_office.hero_explore_solution' ) ); ?></a>
			</div>
		</div>
	</section>

	<section class="content-panel content-panel--overlap-hero">
		<div class="content-panel-box">
			<header class="section-header section-header--center">
				<h2><?php echo esc_html( pera_ml_ui( 'One coordinated commercial operation', 'theme.template.page_developer_sales_office.operation_heading' ) ); ?></h2>
				<p><?php echo esc_html( pera_ml_ui( 'A development needs a commercial plan that connects the project’s value proposition, pricing logic, marketing activity and sales follow-up.', 'theme.template.page_developer_sales_office.operation_intro' ) ); ?></p>
			</header>
			<div class="feature-grid grid-3">
				<article class="feature-card"><div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'Pera Marketing', 'theme.template.page_developer_sales_office.pera_marketing_title' ) ); ?></h3></div><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'Brand and product positioning, launch strategy, creative direction, project materials and buyer story.', 'theme.template.page_developer_sales_office.pera_marketing_body' ) ); ?></p></div></article>
				<article class="feature-card"><div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'Pera Perform', 'theme.template.page_developer_sales_office.pera_perform_title' ) ); ?></h3></div><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'Paid media, lead generation, retargeting, source-level reporting and optimisation.', 'theme.template.page_developer_sales_office.pera_perform_body' ) ); ?></p></div></article>
				<article class="feature-card"><div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'Pera Digital Sales Office', 'theme.template.page_developer_sales_office.digital_sales_office_title' ) ); ?></h3></div><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'CRM, qualification, inventory visibility, buyer presentations, broker registration, reservation follow-up and pipeline management.', 'theme.template.page_developer_sales_office.digital_sales_office_body' ) ); ?></p></div></article>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<header class="section-header section-header--center"><h2><?php echo esc_html( pera_ml_ui( 'What developers need to solve', 'theme.template.page_developer_sales_office.developer_challenges_heading' ) ); ?></h2><p><?php echo esc_html( pera_ml_ui( 'Disconnected positioning, marketing, sales follow-up, broker activity, inventory and reporting make revenue harder to predict and control.', 'theme.template.page_developer_sales_office.developer_challenges_intro' ) ); ?></p></header>
			<div class="feature-grid grid-3">
				<article class="feature-card"><div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'Unclear market position', 'theme.template.page_developer_sales_office.unclear_position_title' ) ); ?></h3></div><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'When the buyer story, product position and pricing logic are disconnected, campaigns struggle to communicate why the project should win.', 'theme.template.page_developer_sales_office.unclear_position_body' ) ); ?></p></div></article>
				<article class="feature-card"><div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'Enquiries without conversion', 'theme.template.page_developer_sales_office.enquiries_without_conversion_title' ) ); ?></h3></div><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'Marketing activity cannot deliver revenue without rapid follow-up, consistent qualification, persuasive presentations and disciplined broker management.', 'theme.template.page_developer_sales_office.enquiries_without_conversion_body' ) ); ?></p></div></article>
				<article class="feature-card"><div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'Limited visibility and control', 'theme.template.page_developer_sales_office.limited_visibility_title' ) ); ?></h3></div><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'Disconnected inventory, broker activity and reporting leave developers without a reliable view of lead sources, pipeline health or next actions.', 'theme.template.page_developer_sales_office.limited_visibility_body' ) ); ?></p></div></article>
			</div>
		</div>
	</section>

	<section class="section section-soft" id="solution">
		<div class="container grid-2">
			<header class="section-header"><h2><?php echo esc_html( pera_ml_ui( 'A sales office built around your project', 'theme.template.page_developer_sales_office.solution_heading' ) ); ?></h2><p><?php echo esc_html( pera_ml_ui( 'We connect the technology, people and commercial routines required to turn buyer demand into measurable sales progress.', 'theme.template.page_developer_sales_office.solution_intro' ) ); ?></p></header>
			<ul class="checklist checklist--circle">
				<li><?php echo esc_html( pera_ml_ui( 'Dedicated project website and campaign landing pages', 'theme.template.page_developer_sales_office.solution_project_website' ) ); ?></li>
				<li><?php echo esc_html( pera_ml_ui( 'CRM with source-level lead and pipeline tracking', 'theme.template.page_developer_sales_office.solution_crm_tracking' ) ); ?></li>
				<li><?php echo esc_html( pera_ml_ui( 'Digital inventory and apartment-selection tools', 'theme.template.page_developer_sales_office.solution_inventory_tools' ) ); ?></li>
				<li><?php echo esc_html( pera_ml_ui( 'Rapid enquiry response and telephone qualification', 'theme.template.page_developer_sales_office.solution_enquiry_response' ) ); ?></li>
				<li><?php echo esc_html( pera_ml_ui( 'Buyer presentations, sales scripts and objection handling', 'theme.template.page_developer_sales_office.solution_buyer_presentations' ) ); ?></li>
				<li><?php echo esc_html( pera_ml_ui( 'Broker registration and partner communication', 'theme.template.page_developer_sales_office.solution_broker_registration' ) ); ?></li>
				<li><?php echo esc_html( pera_ml_ui( 'Reservation tracking and commercial reporting', 'theme.template.page_developer_sales_office.solution_reservation_tracking' ) ); ?></li>
			</ul>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<header class="section-header section-header--center"><h2><?php echo esc_html( pera_ml_ui( 'What we take responsibility for', 'theme.template.page_developer_sales_office.responsibilities_heading' ) ); ?></h2><p><?php echo esc_html( pera_ml_ui( 'One accountable team aligns strategy, revenue, acquisition and sales execution.', 'theme.template.page_developer_sales_office.responsibilities_intro' ) ); ?></p></header>
			<div class="info-steps">
				<article class="info-step"><div class="info-step-icon"><span class="info-step-number">1</span></div><div class="info-step-body"><h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Positioning and launch strategy', 'theme.template.page_developer_sales_office.responsibility_positioning_title' ) ); ?></h3><p class="info-step-text"><?php echo esc_html( pera_ml_ui( 'Define the value proposition, buyer story, launch sequence and materials that bring the project to market.', 'theme.template.page_developer_sales_office.responsibility_positioning_body' ) ); ?></p></div></article>
				<article class="info-step"><div class="info-step-icon"><span class="info-step-number">2</span></div><div class="info-step-body"><h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Pricing and revenue management', 'theme.template.page_developer_sales_office.responsibility_pricing_title' ) ); ?></h3><p class="info-step-text"><?php echo esc_html( pera_ml_ui( 'Connect pricing logic, inventory releases, demand signals and commercial decisions to protect revenue.', 'theme.template.page_developer_sales_office.responsibility_pricing_body' ) ); ?></p></div></article>
				<article class="info-step"><div class="info-step-icon"><span class="info-step-number">3</span></div><div class="info-step-body"><h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Demand generation and brokers', 'theme.template.page_developer_sales_office.responsibility_demand_title' ) ); ?></h3><p class="info-step-text"><?php echo esc_html( pera_ml_ui( 'Build direct campaigns and active broker routes, then measure every source against qualified demand.', 'theme.template.page_developer_sales_office.responsibility_demand_body' ) ); ?></p></div></article>
				<article class="info-step"><div class="info-step-icon"><span class="info-step-number">4</span></div><div class="info-step-body"><h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Sales conversion and closing', 'theme.template.page_developer_sales_office.responsibility_conversion_title' ) ); ?></h3><p class="info-step-text"><?php echo esc_html( pera_ml_ui( 'Manage response, qualification, presentations, objections, reservations and follow-up through closing.', 'theme.template.page_developer_sales_office.responsibility_conversion_body' ) ); ?></p></div></article>
			</div>
		</div>
	</section>

	<section class="section section-soft">
		<div class="container">
			<header class="section-header section-header--center"><h2><?php echo esc_html( pera_ml_ui( 'How a buyer moves through the system', 'theme.template.page_developer_sales_office.buyer_journey_heading' ) ); ?></h2><p><?php echo esc_html( pera_ml_ui( 'Every buyer has a defined route, owner and next action from first touch to signed sale.', 'theme.template.page_developer_sales_office.buyer_journey_intro' ) ); ?></p></header>
			<div class="info-steps">
				<article class="info-step"><div class="info-step-icon"><span class="info-step-number">1</span></div><div class="info-step-body"><h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Campaign or broker', 'theme.template.page_developer_sales_office.buyer_journey_campaign_title' ) ); ?></h3><p class="info-step-text"><?php echo esc_html( pera_ml_ui( 'Demand enters through a trackable direct campaign, agency or referral source.', 'theme.template.page_developer_sales_office.buyer_journey_campaign_body' ) ); ?></p></div></article>
				<article class="info-step"><div class="info-step-icon"><span class="info-step-number">2</span></div><div class="info-step-body"><h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Enquiry and qualification', 'theme.template.page_developer_sales_office.buyer_journey_qualification_title' ) ); ?></h3><p class="info-step-text"><?php echo esc_html( pera_ml_ui( 'The sales office responds rapidly and confirms budget, timing, motivation and fit.', 'theme.template.page_developer_sales_office.buyer_journey_qualification_body' ) ); ?></p></div></article>
				<article class="info-step"><div class="info-step-icon"><span class="info-step-number">3</span></div><div class="info-step-body"><h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Presentation and appointment', 'theme.template.page_developer_sales_office.buyer_journey_presentation_title' ) ); ?></h3><p class="info-step-text"><?php echo esc_html( pera_ml_ui( 'Qualified buyers see the right project story, inventory options and a clear next step.', 'theme.template.page_developer_sales_office.buyer_journey_presentation_body' ) ); ?></p></div></article>
				<article class="info-step"><div class="info-step-icon"><span class="info-step-number">4</span></div><div class="info-step-body"><h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Reservation and closing', 'theme.template.page_developer_sales_office.buyer_journey_closing_title' ) ); ?></h3><p class="info-step-text"><?php echo esc_html( pera_ml_ui( 'Structured follow-up moves the selected unit from reservation through contract and closing.', 'theme.template.page_developer_sales_office.buyer_journey_closing_body' ) ); ?></p></div></article>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container grid-2">
			<header class="section-header"><h2><?php echo esc_html( pera_ml_ui( 'Domestic and international buyer routes', 'theme.template.page_developer_sales_office.buyer_routes_heading' ) ); ?></h2><p><?php echo esc_html( pera_ml_ui( 'We shape the channel mix around the project, rather than forcing every development into the same campaign model.', 'theme.template.page_developer_sales_office.buyer_routes_intro' ) ); ?></p></header>
			<ul class="checklist checklist--circle">
				<li><?php echo esc_html( pera_ml_ui( 'Turkish direct buyers and domestic agencies', 'theme.template.page_developer_sales_office.buyer_routes_turkish' ) ); ?></li>
				<li><?php echo esc_html( pera_ml_ui( 'International digital acquisition and retargeting', 'theme.template.page_developer_sales_office.buyer_routes_international_digital' ) ); ?></li>
				<li><?php echo esc_html( pera_ml_ui( 'Overseas broker and referral partners', 'theme.template.page_developer_sales_office.buyer_routes_overseas_brokers' ) ); ?></li>
				<li><?php echo esc_html( pera_ml_ui( 'Investor buyers seeking yield or capital growth', 'theme.template.page_developer_sales_office.buyer_routes_investors' ) ); ?></li>
				<li><?php echo esc_html( pera_ml_ui( 'Citizenship-focused routes where eligible and relevant', 'theme.template.page_developer_sales_office.buyer_routes_citizenship' ) ); ?></li>
			</ul>
		</div>
	</section>

	<section class="section faq-section" id="faq">
		<div class="container">
			<header class="section-header section-header--center"><h2><?php echo esc_html( pera_ml_ui( 'Frequently asked questions', 'theme.template.page_developer_sales_office.faq_heading' ) ); ?></h2><p><?php echo esc_html( pera_ml_ui( 'Practical answers about appointing Pera as your commercial partner.', 'theme.template.page_developer_sales_office.faq_intro' ) ); ?></p></header>
			<div class="faq-accordion">
				<?php foreach ( $developer_faq_items as $index => $item ) : ?>
					<details class="faq-item" <?php echo 0 === $index ? 'open' : ''; ?>>
						<summary><?php echo esc_html( $item['question'] ); ?></summary>
						<div class="faq-answer"><p><?php echo esc_html( $item['answer'] ); ?></p></div>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="content-panel" id="contact">
		<div class="content-panel-box">
			<div class="content-panel-grid">
				<div>
					<header class="section-header"><h2><?php echo esc_html( pera_ml_ui( 'Let’s build the commercial plan around your project', 'theme.template.page_developer_sales_office.contact_heading' ) ); ?></h2><p><?php echo esc_html( pera_ml_ui( 'Tell us the location, project stage, unit count, target buyer and launch timing. We will use that context to prepare a focused first conversation.', 'theme.template.page_developer_sales_office.contact_intro' ) ); ?></p></header>
					<div class="hero-actions">
						<a class="btn btn--solid btn--green" href="<?php echo esc_url( pera_ml_url( home_url( '/contact-us/' ) ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Request a capability presentation', 'theme.template.page_developer_sales_office.contact_capability_cta' ) ); ?></a>
						<a class="btn btn--solid btn--blue" href="<?php echo esc_url( pera_get_whatsapp_url( pera_ml_ui( 'Hello Pera Property, I would like to discuss a developer sales and marketing operation.', 'theme.template.page_developer_sales_office.whatsapp_prefill' ) ) ); ?>" target="_blank" rel="noopener" data-whatsapp="1" data-whatsapp-type="service_cta" data-track-channel="whatsapp" data-track-intent="high" data-track-source="template" data-track-context="developer_sales_office" data-track-ga4-event="whatsapp_click" data-track-crm-event="whatsapp_click"><?php echo esc_html( pera_ml_ui( 'Chat on WhatsApp', 'theme.template.page_developer_sales_office.contact_whatsapp_cta' ) ); ?></a>
					</div>
				</div>
				<div>
					<h3><?php echo esc_html( pera_ml_ui( 'Information to share', 'theme.template.page_developer_sales_office.contact_information_heading' ) ); ?></h3>
					<ul class="checklist checklist--circle">
						<li><?php echo esc_html( pera_ml_ui( 'Project location and development stage', 'theme.template.page_developer_sales_office.contact_location_stage' ) ); ?></li>
						<li><?php echo esc_html( pera_ml_ui( 'Total and currently available unit count', 'theme.template.page_developer_sales_office.contact_unit_count' ) ); ?></li>
						<li><?php echo esc_html( pera_ml_ui( 'Target buyer profiles and priority markets', 'theme.template.page_developer_sales_office.contact_buyer_markets' ) ); ?></li>
						<li><?php echo esc_html( pera_ml_ui( 'Expected launch or relaunch timing', 'theme.template.page_developer_sales_office.contact_launch_timing' ) ); ?></li>
						<li><?php echo esc_html( pera_ml_ui( 'Current marketing, broker and sales setup', 'theme.template.page_developer_sales_office.contact_current_setup' ) ); ?></li>
					</ul>
				</div>
			</div>
		</div>
	</section>
</main>

<?php get_footer(); ?>
