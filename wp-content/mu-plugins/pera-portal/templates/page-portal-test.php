<?php

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main id="content" class="site-main">
    <?php echo do_shortcode('[' . PERA_PORTAL_SHORTCODE_TAG . ']'); ?>
</main>
<?php
get_footer();
