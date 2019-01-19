<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! function_exists( 'wp_subscribes' ) ) {
	function wp_subscribes() {
		Newsletter::loadNewsLetter();
	}
}