<div class="wrap wpsms-wrap">
    <?php require_once WP_SMS_DIR . 'includes/templates/header.php'; ?>
    <div id="poststuff" class="wpsms-add-ons">
        <div id="post-body" class="metabox-holder">
            <div class="wp-list-table widefat widefat plugin-install">
                <div id="the-list" class="wpsms-add-ons__grid">
                    <?php
                    foreach ($addOns as $plugin) : if ($plugin->price_html == '') continue; ?>
                        <div class="addon-card">
                            <?php if ($plugin->meta['status'] == 'not-installed' && $plugin->on_sale) : ?>
                                <div class="addon-card__ribbon addon-card__ribbon--top-right">
                                    <span><?php _e('On Sale!', 'wp-sms'); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="addon-card__header">
                                <a target="_blank" href="<?php echo $plugin->permalink; ?>" class="thickbox open-plugin-details-modal">
                                    <?php if ($plugin->images) : ?>
                                        <img src="<?php echo $plugin->images[0]->src; ?>" class="addon-icon" alt="<?php echo $plugin->name; ?>">
                                    <?php endif; ?>
                                    <h3><?php echo $plugin->name; ?></h3>
                                </a>
                            </div>
                            <div class="addon-card__main">
                                <div class="addon-card__main__desc">
                                    <p><?php echo wp_trim_words($plugin->short_description, 30); ?></p>
                                </div>
                            </div>
                            <div class="addon-card__footer">
                                <?php if ($plugin->meta['status'] == 'not-installed') : ?>
                                    <div class="addon-card__footer__price">
                                        <strong><?php echo $plugin->price_html; ?></strong>
                                    </div>
                                <?php else : ?>
                                    <div class="addon-card__footer__status">
                                        <?php _e('Status:', 'wp-sms'); ?>
                                        <strong><?php echo $plugin->meta['status_label']; ?></strong>
                                    </div>
                                <?php endif; ?>
                                <div class="addon-card__footer__action">
                                    <?php if ($plugin->meta['status'] == 'active') : ?>
                                        <a class="button" href="<?php echo $plugin->meta['deactivate_url']; ?>"><?php _e('Deactivate Add-On', 'wp-sms'); ?></a>
                                    <?php elseif ($plugin->meta['status'] == 'inactive') : ?>
                                        <a class="button" href="<?php echo $plugin->meta['activate_url']; ?>"><?php _e('Activate Add-On', 'wp-sms'); ?></a>
                                    <?php else : ?>
                                        <a class="button-primary" target="_blank" href="<?php echo $plugin->permalink; ?>"><?php _e('Buy Add-On', 'wp-sms'); ?></a>
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