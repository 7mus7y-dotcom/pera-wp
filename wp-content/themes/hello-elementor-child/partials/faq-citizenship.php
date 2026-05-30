<section class="faq-section section section-soft" id="citizenship-faq">
  <div class="container">

    <header class="section-header section-header--center">
      <h2>Turkish Citizenship by Investment FAQs</h2>
      <p>
        Common questions about the Turkish Citizenship by Investment process,
        documents and practical requirements.
      </p>
    </header>

    <div class="faq-accordion">
      <?php
      $faq_items = function_exists( 'pera_seo_all_citizenship_faq_items' )
        ? pera_seo_all_citizenship_faq_items()
        : array();

      $faq_index = 0;

      foreach ( $faq_items as $faq_item ) :
        if ( empty( $faq_item['question'] ) || empty( $faq_item['answer'] ) ) {
          continue;
        }

        $question = (string) $faq_item['question'];
        $answer   = trim( (string) $faq_item['answer'] );
      ?>
      <details class="faq-item"<?php echo $faq_index === 0 ? ' open' : ''; ?>>
        <summary><?php echo esc_html( $question ); ?></summary>
        <div class="faq-answer">
          <?php echo wp_kses_post( wpautop( $answer ) ); ?>
        </div>
      </details>
      <?php $faq_index++; ?>
      <?php endforeach; ?>
    </div><!-- /.faq-accordion -->


  </div>
</section>
