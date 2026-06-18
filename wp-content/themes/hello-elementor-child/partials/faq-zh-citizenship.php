<section class="faq-section section section-soft" id="citizenship-faq">
  <div class="container">
    <header class="section-header section-header--center">
      <h2>土耳其投资入籍常见问题</h2>
      <p>以下问题适用于通过房地产申请土耳其公民身份的常见情况。具体答案需结合申请时规则和个人情况确认。</p>
    </header>

    <div class="faq-accordion">
      <?php
      $faq_items = function_exists( 'pera_seo_all_zh_citizenship_faq_items' )
        ? pera_seo_all_zh_citizenship_faq_items()
        : array();

      foreach ( $faq_items as $faq_index => $faq_item ) :
      ?>
      <details class="faq-item"<?php echo $faq_index === 0 ? ' open' : ''; ?>>
        <summary><?php echo esc_html( $faq_item['question'] ); ?></summary>
        <div class="faq-answer">
          <p><?php echo esc_html( $faq_item['answer'] ); ?></p>
        </div>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
