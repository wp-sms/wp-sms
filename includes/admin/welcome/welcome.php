<div class="wrap wps-wrap about-wrap full-width-layout">
    <div class="wp-sms-welcome">
        <h1><?php printf( __( 'Welcome to WP-SMS&nbsp;%s', 'wp-sms' ), WP_SMS_VERSION ); ?></h1>

        <p class="about-text">
			<?php printf( __( 'Thank you for updating to the latest version! We encourage you to submit a %srating and review%s over at WordPress.org. Your feedback is greatly appreciated!', 'wp-sms' ), '<a href="https://wordpress.org/support/plugin/wp-sms/reviews/?rate=5#new-post" target="_blank">', '</a>' ); ?>
			<?php _e( 'Submit your rating:', 'wp-sms' ); ?>
            <a href="https://wordpress.org/support/plugin/wp-sms/reviews/?rate=5#new-post" target="_blank"><img src="<?php echo plugins_url( 'wp-sms/assets/images/welcome/stars.png' ); ?>"/></a>
        </p>

        <div class="wp-badge"><?php printf( __( 'Version %s', 'wp-sms' ), WP_SMS_VERSION ); ?></div>

        <h2 class="nav-tab-wrapper wp-clearfix">
            <a href="#" class="nav-tab nav-tab-active" data-tab="whats-news"><?php _e( 'New Product', 'wp-sms' ); ?></a>
            <a href="#" class="nav-tab" data-tab="pro"><?php _e( 'WP-SMS Pro', 'wp-sms' ); ?></a>
            <a href="#" class="nav-tab" data-tab="credit"><?php _e( 'Credits', 'wp-sms' ); ?></a>
            <a href="#" class="nav-tab" data-tab="changelog"><?php _e( 'Changelog', 'wp-sms' ); ?></a>
            <a href="https://wp-sms-pro.com/donate/" class="nav-tab donate" data-tab="link" target="_blank"><?php _e( 'Donate', 'wp-sms' ); ?></a>
        </h2>

        <div data-content="whats-news" class="tab-content current">
            <section class="center-section">
                <div class="left">
                    <div class="content-padding">
						<?php if ( get_locale() == 'fa_IR' ) {
							$response      = wp_remote_get( "https://wp-sms-pro.com/wp-json/wp/v2/pages/8247" );
							$response_code = wp_remote_retrieve_response_code( $response );
							if ( isset( $response['body'] ) AND $response_code == 200 ) {
								$result       = json_decode( $response['body'] );
								$page_title   = $result->title->rendered;
								$page_content = strip_tags( $result->content->rendered );
							} else {
								$page_title   = '';
								$page_content = '';
							} ?>
                            <h2><?php echo $page_title; ?></h2>
                            <p><?php echo $page_content; ?></p>
						<?php } ?>
                        <h2><?php _e( 'WP-Telegram Notifications', 'wp-sms' ); ?></h2>
                        <h4><?php _e( 'A new plugin from VeronaLabs', 'wp-sms' ); ?></h4>

                        <a href="https://wp-telegram.com/purchase/" target="_blank">
                            <img src="<?php echo plugins_url( 'wp-sms/assets/images/welcome/what-is-new/coupon-code.png' ); ?>"/>
                        </a>
                    </div>
                </div>
            </section>

            <section class="normal-section">
                <div class="left">
                    <div class="content-padding">
                        <h2><?php _e( 'Connect with customers', 'wp-sms' ); ?></h2>
                        <p><?php _e( 'Your customers can easily send their message using the box placed on your website.', 'wp-sms' ); ?></p>
                    </div>
                </div>

                <style>
                    div#wp-telegram-chatbox img {
                        width: 290px;
                        display: inline-block;
                    }
                </style>

                <div class="right text-center" id="wp-telegram-chatbox">
                    <img src="<?php echo plugins_url( 'wp-sms/assets/images/welcome/what-is-new/wp-telegram-chatbox.gif' ); ?>"/>
                    <img src="<?php echo plugins_url( 'wp-sms/assets/images/welcome/what-is-new/wp-telegram-iphone-2.png' ); ?>"/>
                </div>
            </section>

            <section class="normal-section">
                <div class="right">
                    <div class="content-padding">
                        <h2><?php _e( 'Send messages to your channels/Groups', 'wp-sms' ); ?></h2>
                        <p><?php _e( 'Simply send any message through the WordPress admin panel to your channels or groups on the telegram. No need to add a person as an admin to the channel/group to add send messages.', 'wp-sms' ); ?></p>
                    </div>
                </div>

                <div class="left text-center">
                    <img src="<?php echo plugins_url( 'wp-sms/assets/images/welcome/what-is-new/wp-telegram-send.png' ); ?>"/>
                </div>
            </section>

            <section class="normal-section">
                <div class="left">
                    <div class="content-padding">
                        <h2><?php _e( 'Integrations & Notifications', 'wp-sms' ); ?></h2>
                        <p><?php _e( 'Integrate with famous plugins such as WooCommerce, CF7, GravityForms, Quforms and EDD.', 'wp-sms' ); ?></p>
                        <p><?php _e( 'For example: Receive the text of the messages sent in a form created with GravityForm on a particular channel or group. ', 'wp-sms' ); ?></p>
                    </div>
                </div>

                <div class="right text-center">
                    <img src="<?php echo plugins_url( 'wp-sms/assets/images/welcome/what-is-new/wp-telegram-settings.png' ); ?>"/>
                </div>
            </section>

            <section class="normal-section">
                <div class="right">
                    <div class="content-padding">
                        <h2><?php _e( 'Supports multiple channels or groups', 'wp-sms' ); ?></h2>
                        <p><?php _e( 'You can add as many channels/groups as you want to the panel and arrange different tasks in different channels/groups.', 'wp-sms' ); ?></p>
                        <p><?php _e( 'For example, different groups for sales, marketing, support or technical team.', 'wp-sms' ); ?></p>
                    </div>
                </div>

                <div class="left text-center">
                    <img src="<?php echo plugins_url( 'wp-sms/assets/images/welcome/what-is-new/wp-telegram-channels.png' ); ?>"/>
                </div>
            </section>

            <section class="center-section">
                <div class="left">
                    <div class="content-padding">
                        <a href="https://wp-telegram.com/purchase/" target="_blank">
                            <img src="<?php echo plugins_url( 'wp-sms/assets/images/welcome/what-is-new/coupon-code.png' ); ?>"/>
                        </a>
                    </div>
                </div>
            </section>

            <section class="normal-section" style="border-bottom: 0px none;">
                <div class="left">
                    <div class="content-padding">
                        <h2 style="margin-top: 10px;"><?php _e( 'WP-SMS Change Log', 'wp-sms' ); ?></h2>
                    </div>
                </div>

                <div class="right">
                    <ul>
                        <li>Fixed: WordPress core update notification notice.</li>
                        <li>Fixed: Screen options columns for only Privacy Page.</li>
                        <li>Fixed: Outbox orderby showing records.</li>
                        <li>Added: Oursms.net Gateway.</li>
                        <li>Added: Eurosms.com gateway.</li>
                        <li>Improved: Newsletter Widget/Shortcode.</li>
                        <li>Minor improvements.</li>
                    </ul>
                </div>
            </section>
        </div>

        <div data-content="pro" class="tab-content">
            <section>
                <h2>Comparing versions</h2>
                <table class="wp-list-table widefat">
                    <thead>
                    <tr>
                        <th class="all"></th>
                        <th class="dropdown-sort">Free</th>
                        <th class="dropdown-sort">Pro Pack</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>
                            <a target="_blank" title="See All SMS gateways that support in WP-SMS and WP-SMS-Pro" href="https://wp-sms-pro.com/gateways">Gateways</a>
                        </td>
                        <td>170</td>
                        <td>215</td>
                    </tr>
                    <tr class="bold">
                        <td colspan="3"><strong>Features</strong></td>
                    </tr>

                    <tr>
                        <td>Send SMS</td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>SMS Newsletter</td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>WP Notification</td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>GDPR Compliance</td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>Rest API</td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>Automatic Update</td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>Login With Mobile</td>
                        <td><span class="dashicons dashicons-no-alt"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr class="bold">
                        <td colspan="3"><strong>Integration</strong></td>
                    </tr>
                    <tr>
                        <td>WordPress</td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>Contact Form 7</td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>BuddyPress</td>
                        <td><span class="dashicons dashicons-no-alt"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>Quform</td>
                        <td><span class="dashicons dashicons-no-alt"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>WooCommerce</td>
                        <td><span class="dashicons dashicons-no-alt"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>Easy Digital Downloads</td>
                        <td><span class="dashicons dashicons-no-alt"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>Gravityforms</td>
                        <td><span class="dashicons dashicons-no-alt"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>WP Job Manager</td>
                        <td><span class="dashicons dashicons-no-alt"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>WP Awesome Support</td>
                        <td><span class="dashicons dashicons-no-alt"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr>
                        <td>Ultimate Member</td>
                        <td><span class="dashicons dashicons-no-alt"></span></td>
                        <td><span class="dashicons dashicons-yes"></span></td>
                    </tr>
                    <tr class="bold">
                        <td colspan="3"><strong>Pricing</strong></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td><a href="https://wp-sms-pro.com/purchase/" target="_blank" class="button button-primary">$15
                                - Buy!</a></td>
                    </tr>
                    </tbody>
                </table>
            </section>
        </div>

        <div data-content="credit" class="tab-content">
            <div class="about-wrap-content">
                <p class="about-description"><?php echo sprintf( __( 'WP-SMS is created by some people and is one of the <a href="%s" target="_blank">VeronaLabs.com</a> projects.', 'wp-sms' ), 'https://veronalabs.com' ); ?></p>
                <h3 class="wp-people-group"><?php _e( 'Project Leaders', 'wp-sms' ); ?></h3>
                <ul class="wp-people-group ">
                    <li class="wp-person">
                        <a href="https://profiles.wordpress.org/mostafas1990" class="web"><?php echo get_avatar( 'mst404@gmail.com', 62, '', '', array( 'class' => 'gravatar' ) ); ?><?php _e( 'Mostafa Soufi', 'wp-sms' ); ?></a>
                        <span class="title"><?php _e( 'Original Author', 'wp-sms' ); ?></span>
                    </li>
                </ul>
                <h3 class="wp-people-group"><?php _e( 'Other Contributors', 'wp-sms' ); ?></h3>
                <ul class="wp-people-group">
                    <li class="wp-person">
                        <a href="https://profiles.wordpress.org/ghasemi71ir" class="web"><?php echo get_avatar( 'ghasemi71ir@gmail.com', 62, '', '', array( 'class' => 'gravatar' ) ); ?><?php _e( 'Mohammad Ghasemi', 'wp-sms' ); ?></a>
                        <span class="title"><?php _e( 'Core Contributor', 'wp-sms' ); ?></span>
                    </li>
                    <li class="wp-person">
                        <a href="https://profiles.wordpress.org/mehrshaddarzi" class="web"><?php echo get_avatar( 'mehrshad198@gmail.com', 62, '', '', array( 'class' => 'gravatar' ) ); ?><?php _e( 'Mehrshad Darzi', 'wp-sms' ); ?></a>
                        <span class="title"><?php _e( 'Core Contributor', 'wp-sms' ); ?></span>
                    </li>
                    <li class="wp-person">
                        <a href="https://profiles.wordpress.org/kamrankhorsandi" class="web"><?php echo get_avatar( 'kamran.khorsandi@gmail.com', 62, '', '', array( 'class' => 'gravatar' ) ); ?><?php _e( 'Kamran Khorsandi', 'wp-sms' ); ?></a>
                        <span class="title"><?php _e( 'Core Contributor', 'wp-sms' ); ?></span>
                    </li>
                    <li class="wp-person">
                        <a href="https://profiles.wordpress.org/pedromendonca" class="web"><?php echo get_avatar( 'ped.gaspar@gmail.com', 62, '', '', array( 'class' => 'gravatar' ) ); ?><?php _e( 'Pedro Mendonça', 'wp-sms' ); ?></a>
                        <span class="title"><?php _e( 'Language Contributor', 'wp-sms' ); ?></span>
                    </li>
                </ul>

                <p class="clear"><?php echo sprintf( __( 'WP-SMS is being developed on GitHub, if you’re interested in contributing to the plugin, please look at the <a href="%s" target="_blank">GitHub page</a>.', 'wp-sms' ), 'https://github.com/veronalabs/wp-sms' ); ?></p>
                <h3 class="wp-people-group"><?php _e( 'External Libraries', 'wp-sms' ); ?></h3>
                <p class="wp-credits-list">
                    <a target="_blank" href="https://github.com/econea/nusoap/">NuSOAP</a>,
                    <a target="_blank" href="http://code.google.com/p/php-excel-reader/">Excel Reader</a>,
                    <a target="_blank" href="http://github.com/elidickinson/php-export-data/">Export Data</a>,
                    <a target="_blank" href="https://harvesthq.github.io/chosen/">Chosen</a>,
                    <a target="_blank" href="https://github.com/jackocnr/intl-tel-input/">International Telephone
                        Input</a>,
                    <a target="_blank" href="https://github.com/qwertypants/jQuery-Word-and-Character-Counter-Plugin/">jQuery
                        Word and character counter</a>,
                    <a target="_blank" href="https://craftpip.github.io/jquery-confirm/">jQuery Confirm</a>.</p>
            </div>
        </div>

        <div data-content="changelog" class="tab-content">
			<?php \WP_SMS\Welcome::show_change_log(); ?>
        </div>
        <hr style="clear: both;">
        <div class="wps-return-to-dashboard">
            <a href="admin.php?page=wp-sms-settings"><?php _e( 'Go to WP-SMS &rarr; Settings', 'wp-sms' ); ?></a>
        </div>
    </div>
</div>
