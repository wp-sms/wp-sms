<?php

/**
 * Class Privacy
 */
class Privacy {

	public $options;

	public function __construct() {
		global $wpsms_option;

		add_action( 'admin_init', array( $this, 'privacy_policy_template' ) );
	}

	/**
	 *
	 * Register the WP-SMS template for a privacy policy.
	 *
	 * Note, this is just a suggestion and should be customized to meet your businesses needs.
	 *
	 */
	public function privacy_policy_template() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = __( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.', 'wp-sms' ) . "\n";

		wp_add_privacy_policy_content( 'WP SMS', wpautop( $content ) );
	}
}

new Privacy();