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
            <a href="#" class="nav-tab nav-tab-active" data-tab="whats-news"><?php _e( 'What&#8217;s New', 'wp-sms' ); ?></a>
            <a href="#" class="nav-tab" data-tab="pro"><?php _e( 'Pro Pack', 'wp-sms' ); ?></a>
            <a href="#" class="nav-tab" data-tab="credit"><?php _e( 'Credits', 'wp-sms' ); ?></a>
            <a href="#" class="nav-tab" data-tab="changelog"><?php _e( 'Changelog', 'wp-sms' ); ?></a>
            <a href="https://wp-sms-pro.com/donate/" class="nav-tab donate" data-tab="link" target="_blank"><?php _e( 'Donate', 'wp-sms' ); ?></a>
        </h2>

        <div data-content="whats-news" class="tab-content current">
            <section class="center-section">
                <div class="left">
                    <div class="content-padding">
                        <h2><?php _e( 'Great update for WP-SMS', 'wp-sms' ); ?></h2>
                    </div>
                </div>
            </section>

            <section class="normal-section">
                <div class="left">
                    <div class="content-padding">
                        <h2><?php _e( 'New Integration!', 'wp-sms' ); ?></h2>
                        <p><?php _e( 'Now Ultimate Members plugin supporting for integration.', 'wp-sms' ); ?></p>
                    </div>
                </div>

                <div class="right text-center">
                    <img src="<?php echo plugins_url( 'wp-sms/assets/images/welcome/what-is-new/ultimate-members.png' ); ?>"/>
                </div>
            </section>

            <section class="normal-section">
                <div class="right">
                    <div class="content-padding">
                        <h2><?php _e( 'WooCommerce OTP', 'wp-sms' ); ?></h2>
                        <p><?php _e( 'Mobile verification for submit a new orders with limitation and period time on retries.', 'wp-sms' ); ?></p>
                    </div>
                </div>

                <div class="left text-center">
                    <img src="<?php echo plugins_url( 'wp-sms/assets/images/welcome/what-is-new/otp.png' ); ?>"/>
                </div>
            </section>

            <section class="normal-section" style="border-bottom: 0px none;">
                <div class="left">
                    <div class="content-padding">
                        <h2><?php _e( 'Other changes', 'wp-sms' ); ?></h2>
                    </div>
                </div>

                <div class="right">
                    <ul>
                        <li>Update: dot4all.it gateway now available on free version.</li>
                        <li>Update: gatewayapi.com to support Unicode.</li>
                        <li>Improved: Response status and Credits to do not save result if is object.</li>
                        <li>Improvments some gateways class.</li>
                    </ul>
                </div>
            </section>
        </div>

        <div data-content="pro" class="tab-content">
            <section class="center-section">
				<?php include( WP_SMS_DIR . "includes/admin/welcome/welcome-pro-tab.php" ); ?>
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
                    <a target="_blank" href="https://github.com/jackocnr/intl-tel-input/">International Telephone Input</a>,
                    <a target="_blank" href="https://github.com/qwertypants/jQuery-Word-and-Character-Counter-Plugin/">jQuery Word and character counter</a>,
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
