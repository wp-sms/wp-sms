</div>
</section>
<section class="c-section--nextstep u-text-center">
    <?php if ($is_last): ?>
        <img src="<?php echo esc_url(WP_SMS_URL . 'assets/images/veronalabs.svg'); ?>" alt="">
    <?php
    else: ?>
        <a class="c-link" href="<?php echo esc_url($next); ?>" title="<?php esc_attr_e('Skip this step', 'wp-sms'); ?>"><?php esc_html_e('Skip this step', 'wp-sms'); ?></a>
    <?php endif;
    ?>
</section>
</div>