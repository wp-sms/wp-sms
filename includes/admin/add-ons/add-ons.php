<div id="poststuff" class="wpsms-add-ons">
    <div id="post-body" class="metabox-holder">
        <div class="wp-list-table widefat widefat plugin-install">
            <div id="the-list">
                <?php
                foreach ($this->addOns as $plugin) : ?>
                    <div class="plugin-card">
                        <?php if ($plugin->on_sale) : ?>
                            <div class="cover-ribbon">
                                <div class="cover-ribbon-inside"><?php _e('On Sale!', 'wp-sms'); ?></div>
                            </div>
                        <?php endif; ?>

                        <div class="plugin-card-top">
                            <div class="name column-name">
                                <h3>
                                    <a target="_blank" href="<?php echo $plugin->permalink; ?>" class="thickbox open-plugin-details-modal">
                                        <?php echo $plugin->name; ?>
                                        <img src="<?php echo $plugin->images[0]->src; ?>" class="plugin-icon" alt="<?php echo $plugin->name; ?>">
                                    </a>
                                </h3>
                            </div>

                            <div class="desc column-description">
                                <p><?php echo wp_trim_words($plugin->short_description, 15); ?></p>
                            </div>
                        </div>
                        <div class="plugin-card-bottom">
                            <div class="column-downloaded">
                                <p>
                                    <strong><?php _e('Status:', 'wp-sms'); ?></strong>
                                    <?php echo $plugin->meta['status_label']; ?>
                                </p>
                            </div>
                            <div class="column-compatibility">
                                <?php if ($plugin->meta['status'] == 'active') : ?>
                                    <a class="button" href="<?php echo $plugin->meta['deactivate_url']; ?>"><?php _e('Deactivate Add-On', 'wp-sms'); ?></a>
                                <?php elseif ($plugin->meta['status'] == 'inactive') : ?>
                                    <a class="button" href="<?php echo $plugin->meta['activate_url']; ?>"><?php _e('Activate Add-On', 'wp-sms'); ?></a>
                                <?php else : ?>
                                    <div class="column-price">
                                        <strong><?php echo $plugin->price_html; ?></strong>
                                    </div><a target="_blank" href="<?php echo $plugin->permalink; ?>" class="button-primary"><?php _e('Buy Add-On', 'wp-sms'); ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
