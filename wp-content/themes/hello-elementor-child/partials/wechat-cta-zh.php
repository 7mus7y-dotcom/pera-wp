<?php
/**
 * Chinese WeChat contact CTA.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article class="feature-card" aria-label="微信咨询 Pera Property">
	<div class="feature-card-header">
		<h3>微信咨询 Pera Property</h3>
	</div>
	<div class="feature-card-body">
		<p>如果您更方便使用微信，请扫描下方二维码添加我们。我们可以协助您了解土耳其房产、伊斯坦布尔投资机会以及土耳其投资入籍流程。</p>
		<?php
		echo wp_get_attachment_image(
			59510,
			'medium',
			false,
			array(
				'alt'      => 'Pera Property 微信二维码',
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
		?>
	</div>
</article>
