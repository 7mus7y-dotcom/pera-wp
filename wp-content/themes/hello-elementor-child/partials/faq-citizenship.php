<section class="section section-soft" id="citizenship-faq">
  <div class="container">

    <header class="section-header section-header--center">
      <h2>Frequently Asked Questions</h2>
      <p>
        Common questions about the Turkish Citizenship by Investment process,
        documents and practical requirements.
      </p>
    </header>

    <div class="doc-accordion">
      <?php
      $faq_items = function_exists( 'pera_seo_all_citizenship_faq_items' )
        ? pera_seo_all_citizenship_faq_items()
        : array();

      foreach ( $faq_items as $faq_item ) :
        if ( empty( $faq_item['question'] ) || empty( $faq_item['answer'] ) ) {
          continue;
        }

        $question  = (string) $faq_item['question'];
        $answer    = trim( (string) $faq_item['answer'] );
        $paragraphs = preg_split( '/(?<=[.!?])\s+(?=[A-Z0-9(“"\'])/u', $answer ) ?: array( $answer );
      ?>
      <details class="doc-item">
        <summary class="doc-summary">
          <span class="doc-title"><?php echo esc_html( $question ); ?></span>
          <span class="doc-icon" aria-hidden="true">
            <span class="doc-icon-line doc-icon-line--horizontal"></span>
            <span class="doc-icon-line doc-icon-line--vertical"></span>
          </span>
        </summary>
        <div class="doc-panel">
          <?php foreach ( $paragraphs as $paragraph ) : ?>
            <?php $paragraph = trim( (string) $paragraph ); ?>
            <?php if ( $paragraph === '' ) { continue; } ?>
            <p><?php echo esc_html( $paragraph ); ?></p>
          <?php endforeach; ?>
        </div>
      </details>
      <?php endforeach; ?>
    </div><!-- /.doc-accordion -->


  </div>
</section>
