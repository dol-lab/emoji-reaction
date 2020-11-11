<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/dol-lab/emoji-reaction
 * @since             0.0.1
 * @package           Emoji_Reaction
 *
 * @wordpress-plugin
 * Plugin Name:       Emoji Reaction
 * Plugin URI:        https://github.com/dol-lab/emoji-reaction
 * Description:       Adds emoji reactions to posts and comments by logged in users.
 * Version:           0.1.6
 * Author:            Digital Open Learning Lab
 * Author URI:        https://spaces.kisd.de
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       emoji-reaction
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'EMOJI_REACTION_VERSION', '0.1.6' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-emoji-reaction-activator.php
 */
function activate_emoji_reaction() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-emoji-reaction-activator.php';
	Emoji_Reaction_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-emoji-reaction-deactivator.php
 */
function deactivate_emoji_reaction() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-emoji-reaction-deactivator.php';
	Emoji_Reaction_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_emoji_reaction' );
register_deactivation_hook( __FILE__, 'deactivate_emoji_reaction' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-emoji-reaction.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    0.0.1
 */
function run_emoji_reaction() {

	$plugin = new Emoji_Reaction();
	$plugin->run();

}
run_emoji_reaction();
