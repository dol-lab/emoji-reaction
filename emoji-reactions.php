<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/dol-lab/emoji-reactions
 * @since             0.0.1
 * @package           Emoji_Reactions
 *
 * @wordpress-plugin
 * Plugin Name:       Emoji Reactions
 * Plugin URI:        https://github.com/dol-lab/emoji-reactions
 * Description:       Adds emoji reactions to posts & comments.
 * Version:           0.0.2
 * Author:            Digital Open Learning Lab
 * Author URI:        https://spaces.kisd.de
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       emoji-reactions
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'EMOJI_REACTIONS_VERSION', '0.0.2' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-emoji-reactions-activator.php
 */
function activate_emoji_reactions() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-emoji-reactions-activator.php';
	Emoji_Reactions_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-emoji-reactions-deactivator.php
 */
function deactivate_emoji_reactions() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-emoji-reactions-deactivator.php';
	Emoji_Reactions_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_emoji_reactions' );
register_deactivation_hook( __FILE__, 'deactivate_emoji_reactions' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-emoji-reactions.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    0.0.1
 */
function run_emoji_reactions() {

	$plugin = new Emoji_Reactions();
	$plugin->run();

}
run_emoji_reactions();
