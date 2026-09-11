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
		'question' => 'What does Pera manage for a development project?',
		'answer'   => 'Pera can manage the connected commercial operation: positioning, launch planning, marketing, lead generation, CRM, buyer qualification, broker coordination, reservations, pipeline management and reporting.',
	),
	array(
		'question' => 'Can Pera work alongside an in-house sales team?',
		'answer'   => 'Yes. We can provide the complete sales-office operation or work alongside your existing team with agreed responsibilities, shared processes and clear reporting.',
	),
	array(
		'question' => 'Which buyer markets can Pera target?',
		'answer'   => 'Depending on the project, we can build routes for Turkish direct buyers, domestic agencies, international digital audiences, overseas brokers, referral partners and investor buyers, including citizenship-focused demand where eligible and relevant.',
	),
	array(
		'question' => 'How is the commercial arrangement structured?',
		'answer'   => 'The structure is tailored to the project scope, stage and sales targets. It may combine defined setup and operating services with performance-based commercial terms agreed before launch.',
	),
	array(
		'question' => 'When should we involve Pera?',
		'answer'   => 'Ideally, involve us before positioning, pricing and launch decisions are final. We can also enter an active project to diagnose gaps, rebuild the pipeline and improve sales control.',
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
	<section class="hero hero--left">
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
			<p><?php echo esc_html( pera_ml_ui( 'For Istanbul developers', 'theme.template.page_developer_sales_office.eyebrow' ) ); ?></p>
			<h1><?php echo esc_html( pera_ml_ui( 'Your project deserves a sales operation — not just a marketing campaign.', 'theme.template.page_developer_sales_office.heading' ) ); ?></h1>
			<p class="lead"><?php echo esc_html( pera_ml_ui( 'Pera plans and operates the commercial function behind a development: positioning, buyer acquisition, sales conversion, broker management and clear reporting from launch to closing.', 'theme.template.page_developer_sales_office.intro' ) ); ?></p>
			<div class="hero-actions">
				<a class="btn btn--solid btn--green" href="#contact"><?php echo esc_html( pera_ml_ui( 'Discuss your project', 'theme.template.page_developer_sales_office.discuss_project' ) ); ?></a>
				<a class="btn btn--solid btn--blue" href="#solution"><?php echo esc_html( pera_ml_ui( 'Explore the solution', 'theme.template.page_developer_sales_office.explore_solution' ) ); ?></a>
			</div>
		</div>
	</section>

	<section class="content-panel content-panel--overlap-hero">
		<div class="content-panel-box">
			<header class="section-header section-header--center">
				<h2>One coordinated commercial operation</h2>
				<p>A development needs a commercial plan that connects the project’s value proposition, pricing logic, marketing activity and sales follow-up.</p>
			</header>
			<div class="feature-grid grid-3">
				<article class="feature-card"><div class="feature-card-header"><h3>Pera Marketing</h3></div><div class="feature-card-body"><p>Brand and product positioning, launch strategy, creative direction, project materials and buyer story.</p></div></article>
				<article class="feature-card"><div class="feature-card-header"><h3>Pera Perform</h3></div><div class="feature-card-body"><p>Paid media, lead generation, retargeting, source-level reporting and optimisation.</p></div></article>
				<article class="feature-card"><div class="feature-card-header"><h3>Pera Digital Sales Office</h3></div><div class="feature-card-body"><p>CRM, qualification, inventory visibility, buyer presentations, broker registration, reservation follow-up and pipeline management.</p></div></article>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<header class="section-header section-header--center"><h2>What developers need to solve</h2><p>Disconnected positioning, marketing, sales follow-up, broker activity, inventory and reporting make revenue harder to predict and control.</p></header>
			<div class="feature-grid grid-3">
				<article class="feature-card"><div class="feature-card-header"><h3>Unclear market position</h3></div><div class="feature-card-body"><p>When the buyer story, product position and pricing logic are disconnected, campaigns struggle to communicate why the project should win.</p></div></article>
				<article class="feature-card"><div class="feature-card-header"><h3>Enquiries without conversion</h3></div><div class="feature-card-body"><p>Marketing activity cannot deliver revenue without rapid follow-up, consistent qualification, persuasive presentations and disciplined broker management.</p></div></article>
				<article class="feature-card"><div class="feature-card-header"><h3>Limited visibility and control</h3></div><div class="feature-card-body"><p>Disconnected inventory, broker activity and reporting leave developers without a reliable view of lead sources, pipeline health or next actions.</p></div></article>
			</div>
		</div>
	</section>

	<section class="section section-soft" id="solution">
		<div class="container grid-2">
			<header class="section-header"><h2>A sales office built around your project</h2><p>We connect the technology, people and commercial routines required to turn buyer demand into measurable sales progress.</p></header>
			<ul class="checklist checklist--circle">
				<li>Dedicated project website and campaign landing pages</li>
				<li>CRM with source-level lead and pipeline tracking</li>
				<li>Digital inventory and apartment-selection tools</li>
				<li>Rapid enquiry response and telephone qualification</li>
				<li>Buyer presentations, sales scripts and objection handling</li>
				<li>Broker registration and partner communication</li>
				<li>Reservation tracking and commercial reporting</li>
			</ul>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<header class="section-header section-header--center"><h2>What we take responsibility for</h2><p>One accountable team aligns strategy, revenue, acquisition and sales execution.</p></header>
			<div class="info-steps">
				<article class="info-step"><div class="info-step-icon"><span class="info-step-number">1</span></div><div class="info-step-body"><h3 class="info-step-title">Positioning and launch strategy</h3><p class="info-step-text">Define the value proposition, buyer story, launch sequence and materials that bring the project to market.</p></div></article>
				<article class="info-step"><div class="info-step-icon"><span class="info-step-number">2</span></div><div class="info-step-body"><h3 class="info-step-title">Pricing and revenue management</h3><p class="info-step-text">Connect pricing logic, inventory releases, demand signals and commercial decisions to protect revenue.</p></div></article>
				<article class="info-step"><div class="info-step-icon"><span class="info-step-number">3</span></div><div class="info-step-body"><h3 class="info-step-title">Demand generation and brokers</h3><p class="info-step-text">Build direct campaigns and active broker routes, then measure every source against qualified demand.</p></div></article>
				<article class="info-step"><div class="info-step-icon"><span class="info-step-number">4</span></div><div class="info-step-body"><h3 class="info-step-title">Sales conversion and closing</h3><p class="info-step-text">Manage response, qualification, presentations, objections, reservations and follow-up through closing.</p></div></article>
			</div>
		</div>
	</section>

	<section class="section section-soft">
		<div class="container">
			<header class="section-header section-header--center"><h2>How a buyer moves through the system</h2><p>Every buyer has a defined route, owner and next action from first touch to signed sale.</p></header>
			<div class="info-steps">
				<article class="info-step"><div class="info-step-icon"><span class="info-step-number">1</span></div><div class="info-step-body"><h3 class="info-step-title">Campaign or broker</h3><p class="info-step-text">Demand enters through a trackable direct campaign, agency or referral source.</p></div></article>
				<article class="info-step"><div class="info-step-icon"><span class="info-step-number">2</span></div><div class="info-step-body"><h3 class="info-step-title">Enquiry and qualification</h3><p class="info-step-text">The sales office responds rapidly and confirms budget, timing, motivation and fit.</p></div></article>
				<article class="info-step"><div class="info-step-icon"><span class="info-step-number">3</span></div><div class="info-step-body"><h3 class="info-step-title">Presentation and appointment</h3><p class="info-step-text">Qualified buyers see the right project story, inventory options and a clear next step.</p></div></article>
				<article class="info-step"><div class="info-step-icon"><span class="info-step-number">4</span></div><div class="info-step-body"><h3 class="info-step-title">Reservation and closing</h3><p class="info-step-text">Structured follow-up moves the selected unit from reservation through contract and closing.</p></div></article>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container grid-2">
			<header class="section-header"><h2>Domestic and international buyer routes</h2><p>We shape the channel mix around the project, rather than forcing every development into the same campaign model.</p></header>
			<ul class="checklist checklist--circle">
				<li>Turkish direct buyers and domestic agencies</li>
				<li>International digital acquisition and retargeting</li>
				<li>Overseas broker and referral partners</li>
				<li>Investor buyers seeking yield or capital growth</li>
				<li>Citizenship-focused routes where eligible and relevant</li>
			</ul>
		</div>
	</section>

	<section class="section faq-section" id="faq">
		<div class="container">
			<header class="section-header section-header--center"><h2>Frequently asked questions</h2><p>Practical answers about appointing Pera as your commercial partner.</p></header>
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
					<header class="section-header"><h2>Let’s build the commercial plan around your project</h2><p>Tell us the location, project stage, unit count, target buyer and launch timing. We will use that context to prepare a focused first conversation.</p></header>
					<div class="hero-actions">
						<a class="btn btn--solid btn--green" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">Request a capability presentation</a>
						<a class="btn btn--solid btn--blue" href="<?php echo esc_url( pera_get_whatsapp_url( 'Hello Pera Property, I would like to discuss a developer sales and marketing operation.' ) ); ?>" target="_blank" rel="noopener" data-whatsapp="1" data-whatsapp-type="service_cta" data-track-channel="whatsapp" data-track-intent="high" data-track-source="template" data-track-context="developer_sales_office" data-track-ga4-event="whatsapp_click" data-track-crm-event="whatsapp_click">Chat on WhatsApp</a>
					</div>
				</div>
				<div>
					<h3>Information to share</h3>
					<ul class="checklist checklist--circle">
						<li>Project location and development stage</li>
						<li>Total and currently available unit count</li>
						<li>Target buyer profiles and priority markets</li>
						<li>Expected launch or relaunch timing</li>
						<li>Current marketing, broker and sales setup</li>
					</ul>
				</div>
			</div>
		</div>
	</section>
</main>

<?php get_footer(); ?>
