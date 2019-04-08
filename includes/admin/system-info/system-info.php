<div class="wrap wps-wrap wp-sms-settings">
    <h2 class="wps_title"><?php _e( 'System Info', 'wp-sms' ); ?></h2>
    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">
            <div class="wp-list-table widefat widefat">
                <div class="wp-sms-container">
                    <ul class="tabs">
                        <li class="tab-link current" data-tab="resources"><?php _e( 'Information', 'wp-sms' ); ?></li>
                    </ul>
                    <div id="resources" class="tab-content current">
                        <div class="wrap wps-wrap">
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row" colspan="2">
                                        <h3><?php _e( 'Download information', 'wp-sms' ); ?></h3>
                                        <form method="POST">
                                            <input type="submit" class="button action" name="wpsms_download_info" value="<?php _e( 'Download', 'wp-sms' ); ?>"/>
                                        </form>
										<?php ?>
                                    </th>
                                </tr>
                                <tr valign="top">
                                    <th scope="row" colspan="2"><h3>WordPress</h3>
                                    </th>
                                </tr>
								<?php foreach ( \WP_SMS\SystemInfo::getWordpressInfo() as $var => $info ): ?>
                                    <tr valign="top">
                                        <th scope="row">
											<?php echo $var . ':'; ?>
                                        </th>
                                        <td>
                                            <strong><?php echo $info['status']; ?></strong>
											<?php
											$desc = isset( $info['desc'] ) ? $info['desc'] : '';
											if ( $desc ):
												?>
                                                <p class="description"><?php echo $desc; ?></p>
											<?php endif; ?>
                                        </td>
                                    </tr>
								<?php endforeach; ?>
                                <tr valign="top">
                                    <th scope="row" colspan="2"><h3>PHP</h3>
                                    </th>
                                </tr>
								<?php foreach ( \WP_SMS\SystemInfo::getPHPInfo() as $var => $info ): ?>
                                    <tr valign="top">
                                        <th scope="row">
											<?php echo $var . ':'; ?>
                                        </th>
                                        <td>
                                            <strong><?php echo $info['status']; ?></strong>
											<?php
											$desc = isset( $info['desc'] ) ? $info['desc'] : '';
											if ( $desc ):
												?>
                                                <p class="description"><?php echo $desc; ?></p>
											<?php endif; ?>
                                        </td>
                                    </tr>
								<?php endforeach; ?>
                            </table>
                        </div>
                    </div>
                </div><!-- container -->
            </div>
        </div>
    </div>
</div>