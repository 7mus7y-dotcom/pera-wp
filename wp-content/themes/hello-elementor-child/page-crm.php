<?php
/**
 * Front-end CRM template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main crm-page">
  <section class="hero hero--left hero--fit" id="crm-hero">
    <div class="hero-content container">
      <h1>CRM</h1>
      <p class="lead">Staff workspace for daily pipeline, workload, and account visibility.</p>
      <div class="hero-actions hero-pills" aria-label="CRM quick statuses">
        <span class="pill pill--brand">Live</span>
        <span class="pill pill--outline">Internal</span>
        <span class="pill pill--outline">Placeholder data</span>
      </div>
    </div>
  </section>

  <section class="content-panel content-panel--overlap-hero">
    <div class="content-panel-box border-dm">
      <div class="section-header">
        <h2>Overview</h2>
        <p>Initial CRM scaffold using existing theme cards, grids, and panel wrappers.</p>
      </div>

      <section class="section" aria-labelledby="crm-kpi-heading">
        <header class="section-header">
          <h2 id="crm-kpi-heading">KPI Snapshot</h2>
        </header>

        <div class="grid-3 crm-kpi-grid">
          <article class="card-shell">
            <p class="pill pill--outline">Leads</p>
            <h3>124</h3>
            <p class="text-soft">Open this week</p>
          </article>
          <article class="card-shell">
            <p class="pill pill--outline">Qualified</p>
            <h3>38</h3>
            <p class="text-soft">Ready for follow-up</p>
          </article>
          <article class="card-shell">
            <p class="pill pill--outline">Viewings</p>
            <h3>16</h3>
            <p class="text-soft">Booked next 7 days</p>
          </article>
          <article class="card-shell">
            <p class="pill pill--outline">Offers</p>
            <h3>9</h3>
            <p class="text-soft">Pending review</p>
          </article>
          <article class="card-shell">
            <p class="pill pill--outline">Won</p>
            <h3>4</h3>
            <p class="text-soft">Closed this month</p>
          </article>
          <article class="card-shell">
            <p class="pill pill--outline">Tasks</p>
            <h3>27</h3>
            <p class="text-soft">Due today</p>
          </article>
        </div>
      </section>

      <section class="section" aria-labelledby="crm-work-heading">
        <div class="grid-2 crm-work-grid">
          <article class="card-shell">
            <header class="section-header">
              <h2 id="crm-work-heading">Pipeline Preview</h2>
              <p>At-a-glance movement across sales stages.</p>
            </header>
            <ul>
              <li><strong>New enquiry:</strong> 42 contacts awaiting first call.</li>
              <li><strong>Discovery:</strong> 21 active conversations.</li>
              <li><strong>Tour planning:</strong> 11 viewings being scheduled.</li>
              <li><strong>Negotiation:</strong> 7 opportunities in pricing stage.</li>
            </ul>
          </article>

          <article class="card-shell">
            <header class="section-header">
              <h2>Work Queue</h2>
              <p>Priority tasks grouped for team handoff.</p>
            </header>
            <ul>
              <li><span class="pill pill--red">High</span> Follow up on delayed documentation (3)</li>
              <li><span class="pill pill--brand">Today</span> Confirm viewing itineraries (8)</li>
              <li><span class="pill pill--green">Done soon</span> Update buyer preference notes (5)</li>
              <li><span class="pill pill--outline">Backlog</span> Archive stale prospects (11)</li>
            </ul>
          </article>
        </div>
      </section>

      <section class="section" aria-labelledby="crm-activity-heading">
        <article class="card-shell crm-activity-card">
          <header class="section-header">
            <h2 id="crm-activity-heading">Recent Activity</h2>
            <p>Latest timeline events from the CRM feed.</p>
          </header>

          <div class="grid-2--tight">
            <div>
              <p><strong>10:24</strong> — New inbound lead assigned to Istanbul central team.</p>
              <p><strong>09:58</strong> — Offer packet sent for Waterfront Residence Unit 11A.</p>
              <p><strong>09:15</strong> — Client preferences updated: budget and district shortlist.</p>
            </div>
            <div>
              <p><strong>08:43</strong> — Viewing confirmed for Saturday, coordinator notified.</p>
              <p><strong>08:10</strong> — Internal note added after legal pre-screen call.</p>
              <p><strong>07:52</strong> — Stale task reminders auto-generated for account owners.</p>
            </div>
          </div>
        </article>
      </section>
    </div>
  </section>
</main>

<?php
get_footer();
