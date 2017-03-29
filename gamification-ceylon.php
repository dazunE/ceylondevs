<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://dasun.blog
 * @since             1.0.0
 * @package           Gamification_Ceylon
 *
 * @wordpress-plugin
 * Plugin Name:       Gamification
 * Plugin URI:        http://ceylondevs.com/gamification
 * Description:       This is gamifcation plugin that build on top of <a href="https://wordpress.org/plugins/mycred/">MyCred </a>. This plugin should connect to SAP via special integrator in order to automate the sales gamifcation via SAP
 * Version:           1.0.0
 * Author:            Dasun Edirisinghe
 * Author URI:        http://dasun.blog
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       gamification-ceylon
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-gamification-ceylon-activator.php
 */
function activate_gamification_ceylon() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-gamification-ceylon-activator.php';
	Gamification_Ceylon_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-gamification-ceylon-deactivator.php
 */
function deactivate_gamification_ceylon() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-gamification-ceylon-deactivator.php';
	Gamification_Ceylon_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_gamification_ceylon' );
register_deactivation_hook( __FILE__, 'deactivate_gamification_ceylon' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-gamification-ceylon.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_gamification_ceylon() {

	$plugin = new Gamification_Ceylon();
	$plugin->run();

}
run_gamification_ceylon();
