<?php

/**
 * WP SMS Shortcode Class
 */
class WP_SMS_Shortcode {

	public $sms;
	public $date;
	public $options;

	protected $db;
	protected $tb_prefix;

	/**
	 * WP_SMS_Features constructor.
	 */
	public function __construct() {
		global $wpsms_option, $sms, $wpdb, $table_prefix;

		$this->sms       = $sms;
		$this->db        = $wpdb;
		$this->tb_prefix = $table_prefix;
		$this->date      = WP_SMS_CURRENT_DATE;
		$this->options   = $wpsms_option;

		//add the shortcode [wp-sms-subscriber-form]
		add_shortcode( 'wp-sms-subscriber-form', array( $this, 'register_shortcode' ) );
	}

	/**
	 * Shortcodes plugin
	 *
	 * @param $atts
	 * @param null $content
	 *
	 * @internal param param $Not
	 */
	public function register_shortcode( $atts ) {
		$html = '<div id="wpsms-subscribe">
    <div id="wpsms-result"></div>
    <div id="wpsms-step-1">
        <p><?php echo $instance[\'description\']; ?></p>
        <div class="wpsms-subscribe-form">
            <label><?php _e( \'Your name\', \'wp-sms\' ); ?>:</label>
            <input id="wpsms-name" type="text" placeholder="<?php _e( \'Your name\', \'wp-sms\' ); ?>" class="wpsms-input"/>
        </div>

        <div class="wpsms-subscribe-form">
            <label><?php _e( \'Your mobile\', \'wp-sms\' ); ?>:</label>
            <input id="wpsms-mobile" type="text" placeholder="<?php echo $instance[\'mobile_field_placeholder\']; ?>"
                   class="wpsms-input"/>
        </div>

		<?php if ( $instance[\'show_group\'] ) { ?>
            <div class="wpsms-subscribe-form">
                <label><?php _e( \'Group\', \'wp-sms\' ); ?>:</label>
                <select id="wpsms-groups" class="wpsms-input">
					<?php foreach ( $get_group as $items ): ?>
                        <option value="<?php echo $items->ID; ?>"><?php echo $items->name; ?></option>
					<?php endforeach; ?>
                </select>
            </div>
		<?php } ?>

        <div class="wpsms-subscribe-form">
            <label>
                <input type="radio" name="subscribe_type" id="wpsms-type-subscribe" value="subscribe"
                       checked="checked"/>
				<?php _e( \'Subscribe\', \'wp-sms\' ); ?>
            </label>

            <label>
                <input type="radio" name="subscribe_type" id="wpsms-type-unsubscribe" value="unsubscribe"/>
				<?php _e( \'Unsubscribe\', \'wp-sms\' ); ?>
            </label>
        </div>
		<?php if ( isset( $wpsms_option[\'gdpr_compliance\'] ) and $wpsms_option[\'gdpr_compliance\'] == 1 ) { ?>
			<?php if ( $instance[\'gdpr_compliance\'] ) { ?>
                <div class="wpsms-subscribe-form">
                    <label><input id="wpsms-gdpr-confirmation"
                                  type="checkbox"> <?php echo $instance[\'gdpr_confirmation_text\']; ?></label>
                </div>
			<?php } ?>
		<?php } ?>

        <button class="wpsms-button" id="wpsms-submit"><?php _e( \'Subscribe\', \'wp-sms\' ); ?></button>
    </div>
	<?php if ( empty( $wpsms_option[\'disable_style_in_front\'] ) or ( isset( $wpsms_option[\'disable_style_in_front\'] ) and ! $wpsms_option[\'disable_style_in_front\'] ) ): ?>
    <div id="wpsms-step-2">
		<?php else: ?>
        <div id="wpsms-step-2" style="display: none;">
			<?php endif; ?>

            <div class="wpsms-subscribe-form">
                <label><?php _e( \'Activation code:\', \'wp-sms\' ); ?></label>
                <input type="text" id="wpsms-ativation-code" placeholder="<?php _e( \'Activation code:\', \'wp-sms\' ); ?>"
                       class="wpsms-input"/>
            </div>
            <button class="wpsms-button" id="activation"><?php _e( \'Activation\', \'wp-sms\' ); ?></button>
        </div>
        <input type="hidden" id="wpsms-widget-id" value="<?php echo $widget_id; ?>">
    </div>';

		return $html;
	}
}

new WP_SMS_Shortcode();