<?php

namespace WP_SMS\Admin;

class Helper {

	/**
	 * Show Admin Wordpress Ui Notice
	 *
	 * @param string $text where Show Text Notification
	 * @param string $model Type Of Model from list : error / warning / success / info
	 * @param boolean $close_button Check Show close Button Or false for not
	 * @param  boolean $echo Check Echo or return in function
	 * @param string $style_extra add extra Css Style To Code
	 *
	 * @author Mehrshad Darzi
	 * @return string Wordpress html Notice code
	 */
	public static function notice( $text, $model = "info", $close_button = true, $echo = true, $style_extra = 'padding:12px;' ) {
		$text = '
        <div class="notice notice-' . $model . '' . ( $close_button === true ? " is-dismissible" : "" ) . '">
           <div style="' . $style_extra . '">' . $text . '</div>
        </div>
        ';
		if ( $echo ) {
			echo $text;
		} else {
			return $text;
		}
	}
<<<<<<< HEAD

=======
>>>>>>> bb6e9c7961aaf9df95e2937536124ade80a63f33
}