<div class="wrap wpsms-wrap">
    <?php echo \WP_SMS\Helper::loadTemplate('header.php'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <div class="wp-header-end"></div>
    <div id="poststuff" class="wpsms-add-ons">
        <div id="post-body" class="metabox-holder">
            <div class="wp-list-table widefat widefat plugin-install">
                <div id="the-list" class="wpsms-add-ons__grid">
                    <?php
                    foreach ($addOns as $plugin) : if ($plugin->price_html == '') continue; ?>
                        <div class="addon-card">
                            <?php if ($plugin->meta['status'] == 'not-installed' && $plugin->on_sale) : ?>
                                <div class="addon-card__ribbon addon-card__ribbon--top-right">
                                    <span><?php esc_html_e('On Sale!', 'wp-sms'); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="addon-card__header">
                                <a target="_blank" href="<?php echo esc_url($plugin->permalink); ?>?utm_source=wp-sms&utm_medium=link&utm_campaign=add-ons" class="thickbox open-plugin-details-modal">
                                    <?php if ($plugin->images) : ?>
                                        <img src="<?php echo esc_url($plugin->images[0]->src); ?>" class="addon-icon" alt="<?php echo esc_attr($plugin->name); ?>">
                                    <?php endif; ?>
                                    <h3><?php echo esc_html($plugin->name); ?></h3>
                                </a>
                            </div>
                            <div class="addon-card__main">
                                <div class="addon-card__main__desc">
                                    <p><?php echo wp_trim_words($plugin->short_description, 30); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
                                </div>
                            </div>
                            <div class="addon-card__footer">
                                <?php if ($plugin->meta['status'] == 'not-installed') : ?>
                                    <div class="addon-card__footer__price">
                                        <strong><?php echo wp_kses_post($plugin->price_html); ?></strong>
                                    </div>
                                <?php else : ?>
                                    <div class="addon-card__footer__status">
                                        <?php esc_html_e('Status:', 'wp-sms'); ?>
                                        <strong><?php echo wp_kses_post($plugin->meta['status_label']); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <div class="addon-card__footer__action">
                                    <?php if ($plugin->meta['status'] == 'active') : ?>
                                        <a class="button" href="<?php echo esc_url($plugin->meta['deactivate_url']); ?>"><?php esc_html_e('Deactivate Add-On', 'wp-sms'); ?></a>
                                    <?php elseif ($plugin->meta['status'] == 'inactive') : ?>
                                        <a class="button" href="<?php echo esc_url($plugin->meta['activate_url']); ?>"><?php esc_html_e('Activate Add-On', 'wp-sms'); ?></a>
                                    <?php else : ?>
                                        <a class="button-primary" target="_blank" href="<?php echo esc_url($plugin->permalink); ?>?utm_source=wp-sms&utm_medium=link&utm_campaign=add-ons"><?php esc_html_e('Buy Add-On', 'wp-sms'); ?></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>