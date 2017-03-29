<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       http://dasun.blog
 * @since      1.0.0
 *
 * @package    Gamification_Ceylon
 * @subpackage Gamification_Ceylon/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Gamification_Ceylon
 * @subpackage Gamification_Ceylon/includes
 * @author     Dasun Edirisinghe <dazunj4me@gmail.com>
 */
class Gamification_Ceylon_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'gamification-ceylon',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
